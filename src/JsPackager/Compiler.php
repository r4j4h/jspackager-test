<?php
/**
 * The Compiler class takes an ordered array of files and dependent packages and compiles and minifies them
 * using the Google Closure Compiler and generates an accompanying manifest file.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2013 WebPT, Inc.
 */

namespace JsPackager;

use JsPackager\Compiler\CompiledFile;
use JsPackager\Compiler\DependencySet;
use JsPackager\Compiler\FileCompilationResult;
use JsPackager\Exception\CannotWrite as CannotWriteException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\Recursion as RecursionException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileObject;

class Compiler
{
    const COMPILED_SUFFIX = 'compiled';
    const MANIFEST_SUFFIX = 'manifest';

    /**
     * @var LoggerInterface
     */
    public $logger;

    public function __construct()
    {
        $this->logger = new NullLogger();
        $this->rollingPathsMarkedNoCompile = array();
    }

    /**
     * @var array
     */
    private $rollingPathsMarkedNoCompile;

    /**
     * Take an array of files in dependency order and compile them, generating a manifest.
     *
     * @param DependencySet $dependencySet Array containing keys 'stylesheets', 'packages', 'dependencies', and 'pathsMarkedNoCompile'
     * @return CompiledFile containing the contents of the resulting compiled file and its manifest.
     */
    public function compileDependencySet($dependencySet)
    {
        $compiledFileContents = '';
        $compiledFileManifest = '';

        $this->logger->debug("compileDependencySet called. Building manifest...");

        $totalDependencies = count( $dependencySet->dependencies );
        $lastDependency = $dependencySet->dependencies[ $totalDependencies - 1 ];
        $rootFile = new File($lastDependency);

        $rootFilePath = $rootFile->path;
        $rootFilename = $rootFile->filename . '.' . $rootFile->filetype;
        $compiledFilename = $this->getCompiledFilename( $rootFilename );
        $manifestFilename = $this->getManifestFilename( $rootFilename );


        $this->rollingPathsMarkedNoCompile = array_merge(
            $this->rollingPathsMarkedNoCompile,
            $dependencySet->pathsMarkedNoCompile
        );

        $this->logger->debug("Assembling manifest for root file '{$rootFilename}'...");
        $this->logger->debug( '$dependencySet->pathsMarkedNoCompile' );
        $this->logger->debug( print_r($dependencySet->pathsMarkedNoCompile, 1) );
        $this->logger->debug( '$this->rollingPathsMarkedNoCompile' );
        $this->logger->debug( print_r($this->rollingPathsMarkedNoCompile, 1) );




        if ( count( $dependencySet->stylesheets ) > 0 || $totalDependencies > 1 ) {
            // Build manifest first
            $compiledFileManifest = $this->generateManifestFileContents(
                $rootFilePath,
                $dependencySet->packages,
                $dependencySet->stylesheets,
                $this->rollingPathsMarkedNoCompile
            );
        } else {
            $this->logger->debug("Skipping building manifest '{$manifestFilename}' for because file has no other dependencies than itself.");
            $compiledFileManifest = null;
        }

        $this->logger->debug("Built manifest.");


        if ( in_array( $rootFilePath .'/'. $rootFilename, $dependencySet->pathsMarkedNoCompile ) ) {
            $this->logger->debug("File marked as do not compile. Skipping compiling with Google Closure Compiler .jar by pretending it succeeded with no output...");
            $compilationResults = array(
                'returnCode' => 0,
                'err' => null,
                'output' => null
            );

        } else {

            $this->logger->debug("Compiling with Google Closure Compiler .jar...");

            // Compile & Concatenate via Google closure Compiler Jar
            $compilationResults = $this->compileFileListUsingClosureCompilerJar( $dependencySet->dependencies );

            $this->logger->debug("Compiled with Google Closure Compiler .jar.");
        }



        $compiledFileContents = $compilationResults['output'];


        $numberOfErrors = $compilationResults['returnCode'];
        if ( $numberOfErrors > 0 ) {
            throw new ParsingException( "{$numberOfErrors} errors occurred while parsing {$rootFilename}.", null, $compilationResults['err'] );
        }

        if ( $compilationResults['err'] === "0 error(s), 0 warning(s)\n" )
        {
            $compilationResults['err'] = null;
        }

        $this->logger->notice("Compiled dependency set for '" . $rootFilename . "' consisting of " . $totalDependencies . " dependencies.");

        return new CompiledFile(
            $rootFilePath,
            $compiledFilename,
            $compiledFileContents,
            $manifestFilename,
            $compiledFileManifest,
            $compilationResults['err']
        );
    }

    const GCC_COMPILATION_LEVEL = 'SIMPLE_OPTIMIZATIONS'; //WHITESPACE_ONLY, SIMPLE_OPTIMIZATIONS, ADVANCED_OPTIMIZATION;
    const GCC_API_OUTPUT_FORMAT = 'json';
    const GCC_API_COMPILER_URL  = 'http://closure-compiler.appspot.com/compile';

    const GCC_JAR_PATH = 'bin/google/closure_compiler';
    const GCC_JAR_FILENAME = 'compiler.jar';


    /**
     * Generate command line arguments for Google Closure Compiler
     *
     * @param array $fileList
     * @return string
     */
    protected function generateClosureCompilerCommandString($fileList)
    {
        $pathStart = dirname( dirname( dirname(__FILE__) ) );
        $compilerPath = $pathStart . '/' . self::GCC_JAR_PATH . '/' . self::GCC_JAR_FILENAME;

        // Prepare command
        $command = '/usr/bin/java -jar "' . $compilerPath . '" ';
        foreach( $fileList as $file )
        {
            $command .= "--js \"${file}\" ";
        }
//        $command .= '--js_output_file "output.js" ';    // Set output file
        $command .= '--compilation_level=' . self::GCC_COMPILATION_LEVEL . ' ';
//        $command .= '--warning_level=VERBOSE ';
        $command .= '--summary_detail_level=3 ';

        $this->logger->debug('Running shell command: ' . $command);

        $command = escapeshellcmd( $command );

        return $command;
    }



    protected function compileFileListUsingClosureCompilerJar($fileList)
    {
        // Prepare command
        $command = $this->generateClosureCompilerCommandString( $fileList );

        // Prepare process pipe file pointers
        $descriptorSpec = array(
            0 => array("pipe", "r"), // stdin
            1 => array("pipe", "w"), // stdout
            2 => array("pipe", "w"), // stderr
        );

        // Open process
        $process = proc_open( $command, $descriptorSpec, $pipes, null, null );
        if ( $process === FALSE )
        {
            throw new \Exception('Unable to open java to run the Closure Compiler .jar');
        }

        // Grab outputs
        $read_output = $read_error = false;
        $buffer_len  = $prev_buffer_len = 0;
        $ms          = 10;
        $stdout      = '';
        $read_output = true;
        $stderr       = '';
        $read_error  = true;
        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        $buffer_len = 0;

        // dual reading of STDOUT and STDERR stops one full pipe blocking the other, because the external script is waiting
        while ($read_error != false or $read_output != false)
        {
            if ($read_output != false)
            {
                if(feof($pipes[1]))
                {
                    fclose($pipes[1]);
                    $read_output = false;
                }
                else
                {
                    $str = fgets($pipes[1], 1024);
                    $len = strlen($str);
                    if ($len)
                    {
                        $stdout .= $str;
                        $buffer_len += $len;
                    }
                }
            }

            if ($read_error != false)
            {
                if(feof($pipes[2]))
                {
                    fclose($pipes[2]);
                    $read_error = false;
                }
                else
                {
                    $str = fgets($pipes[2], 1024);
                    $len = strlen($str);
                    if ($len)
                    {
                        $stderr .= $str;
                        $buffer_len += $len;
                    }
                }
            }

            if ($buffer_len > $prev_buffer_len)
            {
                $prev_buffer_len = $buffer_len;
                $ms = 10;
            }
            else
            {
                usleep($ms * 1000); // sleep for $ms milliseconds
                if ($ms < 160)
                {
                    $ms = $ms * 2;
                }
            }
        }

        // Clean up
        $returnCode = proc_close( $process );
        $successful = ($returnCode === 0);

        return array(
            'successful' => $successful,
            'returnCode' => $returnCode,
            'output' => $stdout,
            'err' => $stderr,
        );
    }


    protected function compileFileContentsUsingClosureCompilerApi($fileContents)
    {
        $data = array(
            'compilation_level' => self::GCC_COMPILATION_LEVEL,
            'output_format' => self::GCC_API_OUTPUT_FORMAT,
            'js_code' => urlencode($fileContents)
        );

        // Hold the post parameters
        $fields_string = '';

        // Convert $data into params
        $fields_strings = array();
        foreach ($data as $key=>$value) {
            $fields_strings[] = $key.'='.$value;
        }
        $fields_string = implode( '&', $fields_strings );

        // Assemble the parameters
        $fields_string = 'output_info=compiled_code&output_info=warnings&output_info=errors&' . $fields_string;

        $post = curl_init( self::GCC_API_COMPILER_URL );
        curl_setopt($post, CURLOPT_POST, 1);
        curl_setopt($post, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($post, CURLOPT_RETURNTRANSFER, true);

        $this->logger->debug("Running against Closure Compiler Api at url: '" . self::GCC_API_COMPILER_URL . "' with fields: '" . $fields_string . "'.");

        $response = json_decode( curl_exec($post) );
        curl_close($post);

        // @TODO Make exceptions for these cases

        if ( property_exists($response, 'serverErrors') ) {
            $errorMessage = '';
            foreach ($response->serverErrors as $error) {
                $errorMessage .= $error->error . "\n";
            }
            $this->logger->error("Unable to compile code due to server errors: \n" . $errorMessage);
            throw new \Exception("Unable to compile code due to server errors: \n" . $errorMessage , 0);
        }

        if ( property_exists($response, 'warnings') ) {
            foreach ($response->warnings as $warning) {
                $warningString = sprintf("\t\t[WARNING] %s [#%d]`%s`\n", $warning->warning, $warning->lineno, $warning->line);
                $this->logger->warning($warningString);
            }
        }

        if ( property_exists($response, 'errors') ) {
            foreach ($response->errors as $error) {
                $errorString = sprintf("\t\t[ERROR] %s [#%d]`%s`\n", $error->error, $error->lineno, $error->line);
                $this->logger->error($errorString);
            }
            throw new \Exception('Failed to compile JS due to errors', 0);
        } else {
            $this->logger->notice("Successfully compiled against closure Compiler Api");
            return $response->compiledCode;
        }
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
    protected function generateManifestFileContents( $basePath, $packagePaths, $stylesheetPaths, $pathsMarkedNoCompile = array() )
    {
        $manifestFileContents = '';

        $this->logger->debug("Generating manifest file contents...");

        foreach ($stylesheetPaths as $stylesheetPath)
        {
            // TODO Relative-ize this path

            // !!! WARNING: This is not the right logic !!!
            // The right logic makes these relative from the file the manifest is for
            // This logic makes these relative from where the compile command was executed
            // !!! WARNING: This is not the right logic !!!

            // To use this way for now, run from <app>/public
            // ../vendor/bin/jspackager compile-files ../public/js/layouts/emr-layout.js ../public/shared/js/layouts/emr.js

            $cwd = getcwd();

            var_dump( '$cwd is ' . $cwd );
            $basePath = $cwd;

            var_dump( '$basePath is ' . $basePath );
            var_dump( '$stylesheetPath is ' . $stylesheetPath );
            var_dump( 'substr is ' . substr( $stylesheetPath, 0, strlen($basePath) ) );

            // Remove baseUrl if present
            if ( $basePath !== '' && substr( $stylesheetPath, 0, strlen($basePath) ) === $basePath )
            {
                // If $src already starts with $baseUrl then we want to remove $baseUrl from it.
                // As if we are shared then we may want something in between baseUrl and the real src.
                $pos = strpos($stylesheetPath,$basePath);
                if ($pos !== false) {
                    $stylesheetPath = substr_replace($stylesheetPath, '', $pos, strlen($basePath));
                }
            }

            var_dump( '$stylesheetPath is ' . $stylesheetPath );

            $manifestFileContents .= $stylesheetPath . PHP_EOL;

        }

        foreach ($packagePaths as $packagePath)
        {
            $filePath = '';
            var_dump( 'arf');
            var_dump( $packagePath);
            var_dump( $pathsMarkedNoCompile);
            var_dump( in_array( $packagePath, $pathsMarkedNoCompile ) );
            if ( in_array( $packagePath, $pathsMarkedNoCompile ) ) {
                $this->logger->debug( "Did not compile, leaving as uncompiled filename..." );
                $packagePath = $packagePath;
            } else {
                $this->logger->debug( "Converted to compiled filename..." );
                $packagePath = $this->getCompiledFilename($packagePath);
            }
//            $manifestFileContents .= $filePath . PHP_EOL;


            // TODO Relative-ize this path

            // !!! WARNING: This is not the right logic !!!
            // The right logic makes these relative from the file the manifest is for
            // This logic makes these relative from where the compile command was executed
            // !!! WARNING: This is not the right logic !!!

            // To use this way for now, run from <app>/public
            // ../vendor/bin/jspackager compile-files ../public/js/layouts/emr-layout.js ../public/shared/js/layouts/emr.js

            $cwd = getcwd();

            var_dump( '$cwd is ' . $cwd );
            $basePath = $cwd;

            var_dump( '$basePath is ' . $basePath );
            var_dump( '$stylesheetPath is ' . $packagePath );
            var_dump( 'substr is ' . substr( $packagePath, 0, strlen($basePath) ) );

            // Remove baseUrl if present
            if ( $basePath !== '' && substr( $packagePath, 0, strlen($basePath) ) === $basePath )
            {
                // If $src already starts with $baseUrl then we want to remove $baseUrl from it.
                // As if we are shared then we may want something in between baseUrl and the real src.
                $pos = strpos($packagePath,$basePath);
                if ($pos !== false) {
                    $packagePath = substr_replace($packagePath, '', $pos, strlen($basePath));
                }
            }

            var_dump( '$stylesheetPath is ' . $packagePath );

            $this->logger->debug( $packagePath );

            $manifestFileContents .= $packagePath . PHP_EOL;
        }

        $this->logger->debug("Generated manifest file contents.");

        return $manifestFileContents;
    }

    /**
     * Take an array of file paths and concatenate them into one blob
     *
     * @param array $filePathList Array of file paths
     * @return string Concatenated file's contents
     * @throws Exception\MissingFile If a file in the list does not exist
     * @throws Exception\Parsing If file was unable to be parsed
     */
    protected function concatenateFiles($filePathList)
    {
        $output = '';

        $this->logger->debug("Concatenating files...");

        foreach( $filePathList as $thisFilePath )
        {
            if ( is_file( $thisFilePath ) === false )
            {
                throw new MissingFileException($thisFilePath . ' is not a valid file!', 0, null, $thisFilePath);
            }

            $thisFileContents = file_get_contents( $thisFilePath );

            if ( $thisFileContents === FALSE )
            {
                throw new ParsingException($thisFilePath . ' was unable to be parsed. Perhaps permissions problem?');
            }

            $output .= $thisFileContents;
        }

        $this->logger->debug("Concatenated files.");

        return $output;
    }


    /**
     * Convert a given filename to its compiled equivalent
     *
     * @param string $filename
     * @return string
     */
    public function getCompiledFilename($filename)
    {
        return preg_replace('/.js$/', '.' . self::COMPILED_SUFFIX . '.js', $filename);
    }

    /**
     * Convert a given filename to its manifest equivalent
     *
     * @param $filename
     * @return string
     */
    public function getManifestFilename($filename)
    {
        return preg_replace('/.js$/', '.js.' . self::MANIFEST_SUFFIX, $filename);
    }

    /**
     * Compile a given file and any of its dependencies in place.
     *
     * @param string $inputFilename Path to the file to be compiled.
     * @param callable $statusCallback Optional. Callback to update User Interface through progress.
     * @return FileCompilationResult[]
     * @throws Exception\CannotWrite
     */
    public function compileAndWriteFilesAndManifests($inputFilename, $statusCallback = false)
    {
        $compiledFiles = array();
        $dependencyTree = new DependencyTree( $inputFilename, null, false, $this->logger );
        $dependencyTree->logger = $this->logger;
        $dependencySets = $dependencyTree->getDependencySets();

        $this->rollingPathsMarkedNoCompile = array();

        foreach( $dependencySets as $dependencySet )
        {
            try {
                echo 'a';
                $result = $this->compileDependencySet( $dependencySet );
                $this->logger->notice('Successfully compiled Dependency Set: ' . $result->filename);
                if ( is_callable( $statusCallback ) ) {
                    call_user_func( $statusCallback, 'Successfully compiled Dependency Set: ' . $result->filename, 'success' );
                }

                if ( strlen( $result->warnings ) > 0 ) {
                    $this->logger->warning('Warnings: ' . PHP_EOL . $result->warnings);
                    if ( is_callable( $statusCallback ) ) {
                        call_user_func( $statusCallback, 'Warnings: ' . PHP_EOL . $result->warnings, 'warning' );
                    }
                }

                $fileCompilationResult = new FileCompilationResult();

                if ( is_null( $result->contents ) ) {

                    $this->logger->info("No compiled file contents, so not writing compiled file to '" . $result->filename . "'.");
                    $fileCompilationResult->setCompiledPath( 'not_compiled' );

                } else {

                    // Write compiled file
                    $outputFilename = $result->path . '/' . $result->filename;
                    $this->logger->info("Writing compiled file to '" . $result->filename . "'.");
                    $outputFile = file_put_contents( $outputFilename, $result->contents );
                    if ( $outputFile === FALSE )
                    {
                        $this->logger->emergency("Cannot write compiled file to {$outputFilename}");
                        throw new CannotWriteException("Cannot write to {$outputFilename}", null, $outputFilename);
                    }
                    $this->logger->info("Wrote compiled file to '" . $result->filename . "'.");
                    $fileCompilationResult->setCompiledPath( $result->filename );

                }


                if ( is_null( $result->manifestContents ) ) {

                    $this->logger->info("No manifest file contents, so not writing manifest file to '" . $result->manifestFilename . "'.");
                    $fileCompilationResult->setManifestPath( 'not_compiled' );

                } else {

                    // Write manifest
                    $outputFilename = $result->path . '/' .$result->manifestFilename;
                    $this->logger->info("Writing compiled file manifest to '" . $result->manifestFilename . "'.");
                    $outputFile = file_put_contents( $outputFilename, $result->manifestContents );
                    if ( $outputFile === FALSE )
                    {
                        $this->logger->critical("Cannot write manifest to {$outputFilename}");
                        throw new CannotWriteException("Cannot write to {$outputFilename}", null, $outputFilename);
                    }
                    $this->logger->info("Wrote compiled file manifest to '" . $result->manifestFilename . "'.");

                    $fileCompilationResult->setManifestPath( $result->manifestFilename );

                }

                $fileCompilationResult->setSourcePath( $result->path );

                $compiledFiles[] = $fileCompilationResult;
            }
            catch ( ParsingException $e )
            {
                $this->logger->error($e->getMessage() . $e->getErrors());
                if ( is_callable( $statusCallback ) ) {
                    call_user_func(
                        $statusCallback,
                        'VVV ' . $e->getMessage() . ' VVV' . PHP_EOL .
                        $e->getErrors() . PHP_EOL .
                        '^^^ ' . $e->getMessage() . ' ^^^',
                        'error' );

                    throw $e;
                }
                else {
                    throw $e;
                }

            }
        }

        $this->logger->info("Finishing compiling and writing manifests.");

        return $compiledFiles;
    }

    /**
     * Delete (unlink) any files with *.compiled.js in the filename across the given path.
     *
     * @param string $directory Directory path.
     * @return bool
     */
    public function clearPackages($directory)
    {
        /** @var $file SplFileObject */

        $dirIterator = new RecursiveDirectoryIterator( $directory );
        $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);
        $packageCount = 0;
        $fileCount = 0;
        $success = true;

        $sourceFileCount = 0;
        $sourceFiles = array();

        foreach ($iterator as $file) {
            if ( $file->isFile() && preg_match( '/.js$/', $file->getFilename() ) && !preg_match( '/.compiled.js$/', $file->getFilename() ) ) {

                $this->logger->notice("[Clearing] Detected package {$file->getFilename()}");
                $sourceFileCount++;

                $compiledFilename = $this->getCompiledFilename( $file->getRealPath() );
                $manifestFilename = $this->getManifestFilename( $file->getRealPath() );

                if ( is_file( $compiledFilename ) ) {
                    $this->logger->notice('[Clearing] Removing ' . $compiledFilename);
                    $unlinkSuccess = unlink( $compiledFilename );
                    if ( !$unlinkSuccess )
                    {
                        $this->logger->error('[Clearing] Failed to remove ' . $compiledFilename);
                        $success = false;
                        continue;
                    }
                    $fileCount++;
                }
                if ( is_file( $manifestFilename ) ) {
                    $this->logger->notice('[Clearing] Removing ' . $manifestFilename);
                    $unlinkSuccess = unlink( $manifestFilename );
                    if ( !$unlinkSuccess )
                    {
                        $this->logger->error('[Clearing] Failed to remove ' . $manifestFilename);
                        $success = false;
                        continue;
                    }
                    $fileCount++;
                }

            }
        }

        $this->logger->notice("[Clearing] Detected $sourceFileCount packages, resulting in $fileCount files being removed.");
        return $success;
    }

    public function parseFolderForSourceFiles($folderPath)
    {
        /** @var $file SplFileObject */

        $dirIterator = new RecursiveDirectoryIterator( $folderPath );
        $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);
        $fileCount = 0;
        $files = array();

        $this->logger->debug("Parsing folder '".$folderPath."' for source files...");
        foreach ($iterator as $file) {
            if ( $file->isFile() && preg_match( '/.js$/', $file->getFilename() ) && !preg_match( '/.compiled.js$/', $file->getFilename() ) ) {
                $files[] = $file->getRealPath();
                $fileCount++;
            }
        }
        $this->logger->debug("Finished parsing folder. Found ".$fileCount." source files.");

        return $files;
    }


}
