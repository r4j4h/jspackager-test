<?php
namespace JsPackager;

use JsPackager\Exception;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Recursion as RecursionException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ManifestContentsGenerator
{
    /**
     * @var LoggerInterface
     */
    public $logger;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * @param $manifestPath
     */
    public function writeManifest($manifestPath, $files) {

        // open manifest
        // read each line

        // follow, tracking paths

        // push out @remotes to shared path

    }

    protected $remoteSymbol = '@remote';

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


    protected function stringContainsRemoteAnnotation($string) {
        return ( strpos($string, $this->remoteSymbol) !== FALSE );
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
        $manifestFileContents = '';

        $this->logger->debug("Generating manifest file contents...");

        foreach ($stylesheetPaths as $stylesheetPath)
        {

            $pathUsesRemote = $this->stringContainsRemoteAnnotation( $stylesheetPath );

            if ( !$pathUsesRemote )
            {
                $this->logger->debug( "{$stylesheetPath} is local." );

                $this->logger->debug( "Calculating relative path between '{$basePath}' and '{$stylesheetPath}'..." );
                $stylesheetPath = $pathFinder->getRelativePathFromAbsoluteFiles( $basePath, $stylesheetPath );
                // If we start with ./ then trim that out, we aint got time for that business
                if ( strpos($stylesheetPath, './') === 0 ) {
                    $stylesheetPath = substr( $stylesheetPath, 2 );
                }
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
                    $this->logger->debug( "baseUrl needs to be removed from '{$stylesheetPath}'." );
                    $stylesheetPath = substr_replace($stylesheetPath, '', $pos, strlen($basePath));
                    $this->logger->debug( "baseUrl removed, new path is '{$stylesheetPath}'." );
                }
            }

            $this->logger->debug( "Final path for stylesheet in manifest is '{$stylesheetPath}." );

            $manifestFileContents .= $stylesheetPath . PHP_EOL;

        }

        foreach ($packagePaths as $packagePath)
        {

            $this->logger->debug( "Determining if should compile file or not..." );

            if ( in_array( $packagePath, $pathsMarkedNoCompile ) ) {
                $this->logger->debug( "Did not compile, leaving as uncompiled filename..." );
                $packagePath = $packagePath;
            } else {
                $this->logger->debug( "Converted to compiled filename..." );
                $packagePath = $this->getCompiledFilename($packagePath);
            }



            $pathUsesRemote = $this->stringContainsRemoteAnnotation( $packagePath );

            if ( !$pathUsesRemote )
            {
                $this->logger->debug( "{$packagePath} is local." );

                $this->logger->debug( "Calculating relative path between '{$basePath}' and '{$packagePath}'..." );
                $packagePath = $pathFinder->getRelativePathFromAbsoluteFiles( $basePath, $packagePath );
                // If we start with ./ then trim that out, we aint got time for that business
                if ( strpos($packagePath, './') === 0 ) {
                    $packagePath = substr( $packagePath, 2 );
                }
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

            $manifestFileContents .= $packagePath . PHP_EOL;
        }

        $this->logger->debug("Generated manifest file contents.");

        return $manifestFileContents;
    }

}