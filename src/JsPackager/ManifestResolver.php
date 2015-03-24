<?php
/**
 * Dependency Tree Parser is used to parse through front end files linked together via annotations. Generally
 * this operates on javascript, but it may also include stylesheets and other types of resources.
 *
 * It's primary purpose is for automatic file inclusion for browsers, for collating source files for JS unit tests,
 * and for compiling JS files.
 *
 * Annotation Legend:
 *  @require relativepath/to.js
 *      Parses the given file for dependencies and includes all of them
 *  @requireStyle ../../css/relative/path/to.css
 *      Includes the given stylesheet on the page when this script is also included.
 *  @root
 *      If present, this file will not be merged in if another file @require's it. Instead
 *      this file and its dependencies will be pulled into the page, or when compiling this
 *      file and its dependencies will get compiled and that compiled file included on the page.
 * @tests
 *      This annotation is equal to a @require annotation, but handles the alternate base path.
 *      That occurs because test scripts are located elsewhere.
 *      Without this, @require lines inside spec files would have a mess of ../../'s that would
 *      increase the difficulty of refactoring.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager;

use JsPackager\Exception;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Recursion as RecursionException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ManifestResolver
{
    /**
     * Regexp pattern for detecting annotations with optional space delimited arguments
     * @var string
     */
    const TOKEN_PATTERN = '/@(.*?)(?:\s(.*))*$/i';


    /**
     * @var LoggerInterface
     */
    public $logger;


    /**
     * @var string
     */
    public $baseFolderPath = 'public';

    /**
     * @var string
     */
    public $sharedFolderPath = 'shared';



    /**
     * @var FileHandler
     */
    protected $fileHandler;

    /**
     * @var Compiler
     */
    protected $compiler;




    public function __construct()
    {
        $this->logger = new NullLogger();
    }



    /**
     * Give the path to a file with a manifest and compiled file next to it and it will use those files.
     *
     *
     *
     * @param $filePath
     */
    public function resolveFile($filePath) {

        $paths = $this->reverseResolveFromCompiledFile( $filePath );
        return $paths;

    }


    /**
     * @param $manifestPath
     */
    public function parseManifest($manifestPath) {

        // open manifest
        // read each line

        // follow, tracking paths

        // push out @remotes to shared path

    }

    /**
     * Get the file handler.
     *
     * @return FileHandler
     */
    public function getFileHandler()
    {
//        return $this->serviceLocator->get('EMRCore\JsPackager\FileHandler');
        return ( $this->fileHandler ? $this->fileHandler : new FileHandler() );
    }


    public function getCompiler()
    {
//        return $this->serviceLocator->get('EMRCore\JsPackager\Compiler');
        return ( $this->compiler ? $this->compiler : new Compiler() );
    }

    /**
     * Set the Compiler.
     *
     * @param Compiler $compiler
     * @return ScriptFile
     */
    public function setCompiler($compiler)
    {
        $this->compiler = $compiler;
        return $this;
    }

    /**
     * Set the file handler.
     *
     * @param $fileHandler
     * @return ScriptFile
     */
    public function setFileHandler($fileHandler)
    {
        $this->fileHandler = $fileHandler;
        return $this;
    }




    /**
     * Get the base path to a file.
     *
     * Give it something like '/my/cool/file.jpg' and get '/my/cool/' back.
     * @param $sourceFilePath
     * @return string
     */
    protected function getBasePathFromSourceFile($sourceFilePath) {
        $posOfLastSlash = strrpos($sourceFilePath, '/' );
        $basePathPortion = substr( $sourceFilePath, 0, $posOfLastSlash+1 );
        return $basePathPortion;
    }

    /**
     * Remove sub-string from a string if it is in there.
     *
     * @param $path
     * @return string
     */
    protected function removeSubstringFromString($string, $substringToRemoveIfPresent) {
        $posInString = strpos( $string, $substringToRemoveIfPresent );
        $subStringLength = strlen( $substringToRemoveIfPresent );
        $subStringInString = ( $posInString !== FALSE );
        if ( $subStringInString ) {
            $string = substr( $string, $posInString + $subStringLength );
        }
        return $string;
    }

    /**
     * Remove the current working directory from a string if it is in there.
     *
     * @param $path
     * @return string
     */
    protected function removeCurrentWorkingDirectionFromPath($path) {
        $cwd = getcwd() . '/';
        return $this->removeSubstringFromString($path, $cwd);
    }

    /**
     * Remove the current working directory from a string if it is in there.
     *
     * @param $path
     * @return string
     */
    protected function removeBaseUrlFromPath($path) {
//        $basePath = rtrim($this->baseFolderPath, '/') . '/';
        $basePath = $this->baseFolderPath . '/';
        return $this->removeSubstringFromString($path, $basePath);
    }


    /**
     * Take a file and attempt to open its compiled version and manifest, returning an ordered array of the
     * necessary files to load.
     *
     * @param $sourceFilePath
     * @return array
     * @throws \EMRCore\JsPackager\Exception\MissingFile
     */
    protected function reverseResolveFromCompiledFile($sourceFilePath, $deeper = false)
    {
        $baseUrl = $this->baseFolderPath . '/';
        $files = array();
        $compiler = $this->getCompiler();
        $compiler->sharedFolderPath = $this->sharedFolderPath;
        $fileHandler = $this->getFileHandler();

        // Lets try to always trim out the cwd if we can
//        $sourceFilePath = $this->removeCurrentWorkingDirectionFromPath( $sourceFilePath );

        $sourceFilePath = $compiler->getSourceFilenameFromCompiledFilename( $sourceFilePath );

        $compiledFilePath = $compiler->getCompiledFilename( $sourceFilePath );
        $manifestFilePath = $compiler->getManifestFilename( $sourceFilePath );

        // If we are using absolute paths, we want to trim the duplicative parts here
        $basePathFromSourceFile = $this->getBasePathFromSourceFile( $sourceFilePath );
        $basePathFromSourceFile = $this->removeBaseUrlFromPath( $basePathFromSourceFile );

//        if ( $basePathFromSourceFile === $baseUrl ) {
//            $pathToSourceFile = $basePathFromSourceFile;
//        } else {
            $pathToSourceFile = $baseUrl . $basePathFromSourceFile;
//        }

        $pathToSourceFile = $this->replaceRemoteSymbolIfPresent($pathToSourceFile, $this->sharedFolderPath);
        $sourceFilePath   = $this->replaceRemoteSymbolIfPresent($sourceFilePath,   $this->sharedFolderPath);
        $manifestFilePath = $this->replaceRemoteSymbolIfPresent($manifestFilePath, $this->sharedFolderPath);
        $compiledFilePath = $this->replaceRemoteSymbolIfPresent($compiledFilePath, $this->sharedFolderPath);


        if ( $fileHandler->is_file( $manifestFilePath  ) ) {
            $filesFromManifest = $this->parseManifestFile( $manifestFilePath, $pathToSourceFile );

            foreach( $filesFromManifest['stylesheets'] as $idx => $file ) {
                $filesFromManifest['stylesheets'][$idx] = $this->replaceRemoteSymbolIfPresent($file, $this->sharedFolderPath);
            }
            foreach( $filesFromManifest['packages'] as $idx => $file ) {
                $filesFromManifest['packages'][$idx] = $this->replaceRemoteSymbolIfPresent($file, $this->sharedFolderPath);
            }
            if ( $filesFromManifest ) {
                $files = array_merge( $files, $filesFromManifest['stylesheets'] );
                $files = array_merge( $files, $filesFromManifest['packages'] );
            }

            // Look at each package for further potential manifests
            foreach( $filesFromManifest['packages'] as $package ) {
                $package = $this->replaceRemoteSymbolIfPresent($package, $this->sharedFolderPath);

                $furtherFiles = $this->reverseResolveFromCompiledFile( $package, true );

                $files = array_merge( $files, $furtherFiles );
            }
        }

        // If there is a compiled path, use it
        var_dump( '$sourceFilePath' );
        var_dump( $sourceFilePath );
        var_dump( '$manifestFilePath' );
        var_dump( $manifestFilePath );
        var_dump( '$compiledFilePath' );
        var_dump( $compiledFilePath );

        if ( $fileHandler->is_file( $compiledFilePath  ) ) {
            $sourceFilePath = $compiledFilePath;
        }


        // If we are recursing, we've already handled prepending the baseUrl
        if ( !$deeper ) {
            $files[] = $baseUrl . $sourceFilePath;
        }

        return $files;
    }

    /**
     * Extracts packages and stylesheets from a given a manifest file.
     *
     * Item in manifest are expected to be separated by newlines, with NO other characters or spaces.
     *
     * @param $filePath string File's path
     */
    protected function parseManifestFile($filePath, $basePath = '') {
        $fileHandler = $this->getFileHandler();
        $stylesheets = array();
        $packages = array();

        if ( !$fileHandler->is_file( $filePath ) ) {
            return false;
        }

        // Parse file line by line
        $fh = $fileHandler->fopen( $filePath, 'r' );
        while ( ( $line = $fileHandler->fgets($fh) ) !== false )
        {
            // Strip new line characters
            $line = rtrim( $line, "\r\n" );

            // Pre-pend current basepath extension
            $line = $basePath . $line;

            if ( preg_match('/.js$/i', $line ) ) {
                $packages[] = $line;
            }
            else if ( preg_match('/.css$/i', $line ) ) {
                $stylesheets[] = $line;
            }
            else {
                throw new ParsingException("Malformed manifest entry encountered", null, $line);
            }
        }
        $fileHandler->fclose($fh);

        return array(
            'stylesheets' => $stylesheets,
            'packages' => $packages
        );
    }



    public $remoteSymbol = '@remote';

    public function replaceRemoteSymbolIfPresent($filePath, $browserRelativePathToRemote = '') {

        // Suffix a / if we have something
        if ( $browserRelativePathToRemote !== '' ) {
            $browserRelativePathToRemote = $browserRelativePathToRemote . '/';
        }

        $remoteSymbol = $this->remoteSymbol;
        $remoteSymbolWithSlash = $remoteSymbol . '/';
        $positionOfRemoteSubString = strpos( $filePath, $remoteSymbolWithSlash );
        $lengthOfRemoteSymbolWithSlash = strlen( $remoteSymbolWithSlash );
        $hasRemoteSymbol = ( $positionOfRemoteSubString !== FALSE );
        if ( $hasRemoteSymbol ) {
            $everythingAfterSymbolNSlash = substr($filePath, $positionOfRemoteSubString + $lengthOfRemoteSymbolWithSlash );
            $filePath = $browserRelativePathToRemote . $everythingAfterSymbolNSlash;
        }

        return $filePath;

    }


}