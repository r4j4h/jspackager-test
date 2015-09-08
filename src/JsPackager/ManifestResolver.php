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

use JsPackager\CompiledFileAndManifest\FilenameConverter;
use JsPackager\Exception;
use JsPackager\Exception\MissingFile;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Helpers\FileHandler;
use JsPackager\Helpers\MapDrivenFileTypeRecognizer;
use Psr\Log\LoggerInterface;

class ManifestResolver
{

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var string
     */
    public $baseFolderPath;

    /**
     * @var string
     */
    public $remoteFolderPath;

    /**
     * @var string
     */
    public $remoteSymbol;

    /**
     * @var FileHandler
     */
    protected $fileHandler;

    /**
     * @var MapDrivenFileTypeRecognizer
     */
    private $recognizer;

    /**
     * @param string $baseFolderPath
     * @param string $remoteFolderPath
     * @param string $remoteSymbol
     * @param LoggerInterface $logger
     */
    public function __construct($baseFolderPath = 'public', $remoteFolderPath = 'shared', $remoteSymbol = '@remote', LoggerInterface $logger)
    {
        $this->baseFolderPath = $baseFolderPath;
        $this->remoteFolderPath = $remoteFolderPath;
        $this->remoteSymbol = $remoteSymbol;
        $this->logger = $logger;


        $this->recognizer = new MapDrivenFileTypeRecognizer();
        $this->recognizer->addRecognition(
            'javascript',  array('JsPackager\Helpers\FileTypeRecognizer', 'isJavaScriptFile')
        );
        $this->recognizer->addRecognition(
            'stylesheet',  array('JsPackager\Helpers\FileTypeRecognizer', 'isStylesheetFile')
        );

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
     * Get the file handler.
     *
     * @return FileHandler
     */
    public function getFileHandler()
    {
//        return $this->serviceLocator->get('JsPackager\Helpers\FileHandler');
        return ( $this->fileHandler ? $this->fileHandler : new FileHandler() );
    }



    /**
     * Set the file handler.
     *
     * @param $fileHandler
     * @return ManifestResolver
     */
    public function setFileHandler($fileHandler)
    {
        $this->fileHandler = $fileHandler;
        return $this;
    }




    /**
     * todo move to Helpers\PathTools
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
     * todo this should be a helper, maybe Helpers\PathFinder maybe Helpers\String?
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
        if ( $string === false ) {
            $string = '';
        }
        return $string;
    }

    /**
     * Remove the current working directory from a string if it is in there.
     *
     * @param $path
     * @return string
     */
    protected function removeCurrentWorkingDirectoryFromPath($path) {
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
     * @return array Array of file paths, with @remote annotations hydrated out
     * @throws MissingFile
     */
    protected function reverseResolveFromCompiledFile($sourceFilePath, $deeper = false)
    {
        $this->logger->info('reverseResolveFromCompiledFile given ' . $sourceFilePath );

        // Lets try to always trim out the cwd if we can
        // todo the end user should do this, cli script or whatever, this is affecting our object's state too
        $this->baseFolderPath = $this->removeCurrentWorkingDirectoryFromPath( $this->baseFolderPath );
        $this->remoteFolderPath = $this->removeCurrentWorkingDirectoryFromPath( $this->remoteFolderPath );

        if ( $this->baseFolderPath !== '' ) {
            $baseUrl = rtrim( $this->baseFolderPath, '/' ) . '/';
        } else {
            $baseUrl = '';
        }

        $files = array();
        $fileHandler = $this->getFileHandler();

        // Lets try to always trim out the cwd if we can
        $sourceFilePath = $this->removeCurrentWorkingDirectoryFromPath( $sourceFilePath );
        $filenameConverter = new FilenameConverter('compiled', 'manifest');

        $sourceFilePath = $filenameConverter->getSourceFilenameFromCompiledFilename( $sourceFilePath );
        $this->logger->info('Reverse resolving for source file ' . $sourceFilePath );

        $compiledFilePath = $filenameConverter->getCompiledFilename( $sourceFilePath );
        $manifestFilePath = $filenameConverter->getManifestFilename( $sourceFilePath );

        // If we are using absolute paths, we want to trim the duplicative parts here
        $basePathFromSourceFile = $this->getBasePathFromSourceFile( $sourceFilePath );

        if ( $this->baseFolderPath !== '' ) {
            $basePathFromSourceFile = $this->removeBaseUrlFromPath($basePathFromSourceFile);
        }

        if ( $basePathFromSourceFile === $baseUrl ) {
            $pathToSourceFile = $basePathFromSourceFile;
        } else {
            $pathToSourceFile = $baseUrl . $basePathFromSourceFile;
        }

        $pathToSourceFile = $this->replaceRemoteSymbolIfPresent($pathToSourceFile, $this->remoteFolderPath);
        $sourceFilePath   = $this->replaceRemoteSymbolIfPresent($sourceFilePath,   $this->remoteFolderPath);
        $manifestFilePath = $this->replaceRemoteSymbolIfPresent($manifestFilePath, $this->remoteFolderPath);
        $compiledFilePath = $this->replaceRemoteSymbolIfPresent($compiledFilePath, $this->remoteFolderPath);



        if ( $fileHandler->is_file( $compiledFilePath  ) ) {

            $sourceFilePath = $compiledFilePath;

            $this->logger->info('Compiled version detected. Using compiled file ' . $sourceFilePath );

            if ( $fileHandler->is_file( $manifestFilePath  ) ) {

                $this->logger->info('Manifest detected at ' . $manifestFilePath);
                $this->logger->info('Parsing manifest for files...');

                $this->parseParsedFilesFromManifest($manifestFilePath, $pathToSourceFile, $files);

            } else {

                $this->logger->notice('Manifest not present. No big deal. Moving on.' );

            }

        } else {

            throw new MissingFile("Compiled file \"{$compiledFilePath}\" is missing!", 0, null, $compiledFilePath );

        }


        // If we are recursing, we've already handled prepending the baseUrl
        if ( !$deeper ) {

            // Prevent redundant baseUrls
            if ( $this->baseFolderPath !== '' ) {
                $sourceFilePath = $this->removeBaseUrlFromPath($sourceFilePath);
            }

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
            $this->logger->error("Failed parsing manifest file: '{$filePath}''");
            return false;
        }

        // Parse file line by line
        $fh = $fileHandler->fopen( $filePath, 'r' );
        while ( ( $line = $fileHandler->fgets($fh) ) !== false )
        {
            // Strip new line characters
            $line = rtrim( $line, "\r\n" );

            $lineStartsWithRemote = ( strpos($line, $this->remoteSymbol) === 0 );

            // Pre-pend current basepath extension
            if ( !$lineStartsWithRemote ) {
                $line = $basePath . $line;
            }

            if ( $this->recognizer->recognize('javascript', $line ) ) {
                $packages[] = $line;
            }
            else if ( $this->recognizer->recognize('stylesheet', $line ) ) {
                $stylesheets[] = $line;
            }
            else {
                throw new ParsingException("Malformed manifest entry encountered while reading '{$filePath}''", null, $line);
            }
        }
        $fileHandler->fclose($fh);

        return array(
            'stylesheets' => $stylesheets,
            'packages' => $packages
        );
    }



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

    /**
     * @param $manifestFilePath
     * @param $pathToSourceFile
     * @param $files
     * @throws ParsingException
     */
    protected function parseParsedFilesFromManifest($manifestFilePath, $pathToSourceFile, &$files)
    {
        $filesFromManifest = $this->parseManifestFile($manifestFilePath, $pathToSourceFile);

        foreach ($filesFromManifest['stylesheets'] as $idx => $file) {
            $filesFromManifest['stylesheets'][$idx] = $this->replaceRemoteSymbolIfPresent($file, $this->remoteFolderPath);
            $this->logger->info('Parsing stylesheet from manifest: ' . $filesFromManifest['stylesheets'][$idx]);
        }

        foreach ($filesFromManifest['packages'] as $idx => $file) {
            $filesFromManifest['packages'][$idx] = $this->replaceRemoteSymbolIfPresent($file, $this->remoteFolderPath);
            $this->logger->info('Parsing package from manifest: ' . $filesFromManifest['packages'][$idx]);
        }

        if ($filesFromManifest) {
            $files = array_merge($files, $filesFromManifest['stylesheets']);
            $files = array_merge($files, $filesFromManifest['packages']);
        }
    }


}