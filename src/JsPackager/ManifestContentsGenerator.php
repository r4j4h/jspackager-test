<?php
namespace JsPackager;

use JsPackager\Annotations\RemoteAnnotationStringService;
use JsPackager\CompiledFileAndManifest\FilenameConverter;
use JsPackager\Exception;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Helpers\Constants;
use JsPackager\Helpers\PathFinder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ManifestContentsGenerator
{
    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * Remote Folder Path represented by remoteSymbol
     *
     * @var string
     */
    public $remoteFolderPath;

    /**
     * Symbol used to represent remote folders.
     *
     * @var string
     */
    public $remoteSymbol;

    /**
     * @param string $remoteSymbol Symbol used to represent remote folders.
     * @param string $remoteFolderPath Remote Folder Path represented by remoteSymbol
     * @param LoggerInterface $logger
     */
    public function __construct($remoteSymbol = '@shared', $remoteFolderPath = 'shared', LoggerInterface $logger)
    {
        $this->remoteSymbol = $remoteSymbol;
        $this->remoteFolderPath = $remoteFolderPath;
        $this->logger = $logger;
    }


    /**
     * Convert a given filename to its compiled equivalent
     *
     * @param string $filename
     * @return string
     */
    protected function getCompiledFilename($filename)
    {
        return preg_replace('/.js$/', '.' . Constants::COMPILED_SUFFIX . '.js', $filename);
    }


    /**
     * Take an array of stylesheet file paths and package file paths and generate a manifest file from them.
     *
     * @param string $basePath The base path of the file this manifest belongs to for making paths relative
     * @param array $packagePaths Array of file paths
     * @param array $stylesheetPaths Array of file paths
     * @param boolean $pathsMarkedNoCompile Array of file paths that are marked `do not compile`
     * @return string Manifest file's contents
     */
    public function generateManifestFileContents( $basePath, $packagePaths, $stylesheetPaths, $pathsMarkedNoCompile = array() )
    {
        $pathFinder = new PathFinder();
        $remoteAnnotationStringService = new RemoteAnnotationStringService(
            $this->remoteSymbol,
            $this->remoteFolderPath
        );
        $manifestFileContents = '';

        $this->logger->debug("Generating manifest file contents...");

        $stylesheetPathsForManifest = array();

        foreach ($stylesheetPaths as $stylesheetPath)
        {
            $stylesheetPath = $this->calculateRelativePathingForStylesheet(
                $stylesheetPath, $basePath, $pathFinder, $remoteAnnotationStringService
            );
            array_push( $stylesheetPathsForManifest, $stylesheetPath );
        }
        $stylesheetPathsForManifest = array_unique( $stylesheetPathsForManifest );
        foreach ($stylesheetPathsForManifest as $stylesheetPath) {
            $manifestFileContents .= $stylesheetPath . PHP_EOL;
        }

        foreach ($packagePaths as $packagePath)
        {

            $packagePath = $this->calculateRelativePathingForPackage(
                $packagePath, $basePath, $pathsMarkedNoCompile, $pathFinder, $remoteAnnotationStringService
            );

            $manifestFileContents .= $packagePath . PHP_EOL;
        }

        $this->logger->debug("Generated manifest file contents.");

        return $manifestFileContents;
    }

    /**
     * Trim out `./` from the beginning of a string if it is present.
     *
     * @param $stringToTrim
     * @return string
     */
    private function trimCwdPath($stringToTrim)
    {
        if (strpos($stringToTrim, './') === 0) {
            $stringToTrim = substr($stringToTrim, 2);
            return $stringToTrim;
        }
        return $stringToTrim;
    }

    /**
     * @param $stylesheetPath
     * @param $basePath
     * @param PathFinder $pathFinder
     * @param RemoteAnnotationStringService $remoteAnnotationStringService
     * @return mixed|string
     */
    private function calculateRelativePathingForStylesheet($stylesheetPath, $basePath, PathFinder $pathFinder, RemoteAnnotationStringService $remoteAnnotationStringService) {

        $pathUsesRemote = $remoteAnnotationStringService->stringContainsRemoteAnnotation( $stylesheetPath );

        if ( !$pathUsesRemote )
        {
            $this->logger->debug( "{$stylesheetPath} is local." );

            $this->logger->debug( "Calculating relative path between '{$basePath}' and '{$stylesheetPath}'..." );
            $stylesheetPath = $pathFinder->getRelativePathFromAbsoluteFiles( $basePath, $stylesheetPath );
            $stylesheetPath = $this->trimCwdPath($stylesheetPath);

            $this->logger->debug( "Calculated relative path to be '{$stylesheetPath}'." );
        }
        else
        {
            $this->logger->debug(
                "Determined {$stylesheetPath} contains {$this->remoteSymbol}, so not converting path to relative."
            );
        }

        $this->logger->debug( "Checking to see if baseUrl ('{$basePath}') needs to be removed..." );
        if ( $basePath !== '' && substr( $stylesheetPath, 0, strlen($basePath) ) === $basePath )
        {
            // If $src already starts with $baseUrl then we want to remove $baseUrl from it.
            // As if we are shared/remote then we may want something in between baseUrl and the real src.
            $pos = strpos($stylesheetPath,$basePath);
            if ($pos !== false) {
                $stylesheetPath = substr_replace($stylesheetPath, '', $pos, strlen($basePath));
                $this->logger->debug( "baseUrl removed, new path is '{$stylesheetPath}'." );
            }
        }

        $this->logger->debug( "Final path for stylesheet in manifest is '{$stylesheetPath}." );
        return $stylesheetPath;
    }

    /**
     * @param $packagePath
     * @param $basePath
     * @param $pathsMarkedNoCompile
     * @param PathFinder $pathFinder
     * @param RemoteAnnotationStringService $remoteAnnotationStringService
     * @return mixed|string
     */
    private function calculateRelativePathingForPackage($packagePath, $basePath, $pathsMarkedNoCompile, PathFinder $pathFinder, RemoteAnnotationStringService $remoteAnnotationStringService) {
        $this->logger->debug( "Determining if should use compiled file or not..." );

        if ( in_array( $packagePath, $pathsMarkedNoCompile ) ) {
            $this->logger->debug( "Using uncompiled filename..." );
            $packagePath = $packagePath;
        } else {
            $this->logger->debug( "Using compiled filename..." );
            $packagePath = FilenameConverter::getCompiledFilename($packagePath);
        }

        $pathUsesRemote = $remoteAnnotationStringService->stringContainsRemoteAnnotation( $packagePath );

        if ( !$pathUsesRemote )
        {
            $this->logger->debug( "{$packagePath} is local." );

            $this->logger->debug( "Calculating relative path between '{$basePath}' and '{$packagePath}'..." );
            $packagePath = $pathFinder->getRelativePathFromAbsoluteFiles( $basePath, $packagePath );
            $packagePath = $this->trimCwdPath($packagePath);

            $this->logger->debug( "Calculated relative path to be '{$packagePath}'." );
        }
        else
        {
            $this->logger->debug(
                "Determined {$packagePath} contains {$this->remoteSymbol}, so not converting path to relative."
            );
        }


        $this->logger->debug( "Checking to see if baseUrl ('{$basePath}') needs to be removed..." );
        if ( $basePath !== '' && substr( $packagePath, 0, strlen($basePath) ) === $basePath )
        {

            // If $src already starts with $baseUrl then we want to remove $baseUrl from it.
            // As if we are shared/remote then we may want something in between baseUrl and the real src.
            $pos = strpos($packagePath,$basePath);
            if ($pos !== false) {
                $this->logger->debug( "baseUrl needs to be removed from '{$packagePath}'." );
                $packagePath = substr_replace($packagePath, '', $pos, strlen($basePath));
                $this->logger->debug( "baseUrl removed, new path is '{$packagePath}'." );

            }
        }

        $this->logger->debug( "Final path for package in manifest is '{$packagePath}." );
        return $packagePath;
    }

}