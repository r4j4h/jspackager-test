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
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileObject;

class Compiler
{
    const COMPILED_SUFFIX = 'compiled';
    const MANIFEST_SUFFIX = 'manifest';

    /**
     * Take an array of files in dependency order and compile them, generating a manifest.
     *
     * @param DependencySet $dependencySet Array containing keys 'stylesheets', 'packages', and 'dependencies'
     * @return CompiledFile containing the contents of the resulting compiled file and its manifest.
     */
    public function compileDependencySet($dependencySet)
    {
        $compiledFileContents = '';
        $compiledFileManifest = '';

        // Build manifest first
        $compiledFileManifest = $this->generateManifestFileContents(
            $dependencySet->packages,
            $dependencySet->stylesheets
        );

        // Compile & Concatenate via Google closure Compiler Jar
        $compilationResults = $this->compileFileListUsingClosureCompilerJar( $dependencySet->dependencies );


        $totalDependencies = count( $dependencySet->dependencies );
        $lastDependency = $dependencySet->dependencies[ $totalDependencies - 1 ];
        $rootFile = new File($lastDependency);
        $rootFilename = $rootFile->filename . '.' . $rootFile->filetype;
        $compiledFilename = $this->getCompiledFilename( $rootFilename );
        $manifestFilename = $this->getManifestFilename( $rootFilename );

        $numberOfErrors = $compilationResults['returnCode'];
        if ( $numberOfErrors > 0 ) {
            throw new ParsingException( "{$numberOfErrors} errors occurred while parsing {$rootFilename}.", null, $compilationResults['err'] );
        }

        if ( $compilationResults['err'] === "0 error(s), 0 warning(s)\n" )
        {
            $compilationResults['err'] = null;
        }


        return new CompiledFile(
            $rootFile->path,
            $compiledFilename,
            $compilationResults['output'],
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
        $response = json_decode( curl_exec($post) );
        curl_close($post);

        // @TODO Make exceptions for these cases
        // @TODO Handle the output results

        if ( property_exists($response, 'serverErrors') ) {
            $errorMessage = '';
            foreach ($response->serverErrors as $error) {
                $errorMessage .= $error->error . "\n";
            }
            throw new \Exception("Unable to compile code due to server errors: \n" . $errorMessage , 0);
        }

        if ( property_exists($response, 'warnings') ) {
            foreach ($response->warnings as $warning) {
                echo sprintf("\t\t[WARNING] %s [#%d]`%s`\n", $warning->warning, $warning->lineno, $warning->line);
            }
        }

        if ( property_exists($response, 'errors') ) {
            foreach ($response->errors as $error) {
                echo sprintf("\t\t[ERROR] %s [#%d]`%s`\n", $error->error, $error->lineno, $error->line);
            }
            throw new \Exception('Failed to compile JS due to errors', 0);
        } else {
            return $response->compiledCode;
        }
    }



    /**
     * Take an array of stylesheet file paths and package file paths and generate a manifest file from them.
     *
     * @param array $packagePaths Array of file paths
     * @param array $stylesheetPaths Array of file paths
     * @return string Manifest file's contents
     */
    protected function generateManifestFileContents( $packagePaths, $stylesheetPaths )
    {
        $manifestFileContents = '';

        foreach ($stylesheetPaths as $stylesheetPath)
        {
            $manifestFileContents .= $stylesheetPath . PHP_EOL;
        }

        foreach ($packagePaths as $packagePath)
        {
            $manifestFileContents .= $this->getCompiledFilename( $packagePath ) . PHP_EOL;
        }

        return $manifestFileContents;
    }

    /**
     * Take an array of file paths and concatenate them into one blob
     *
     * @param array $filePathList Array of file paths
     * @return string Concatenated file's contents
     * @throw Exception\MissingFile If a file in the list does not exist
     * @throws Exception\Parsing If file was unable to be parsed
     */
    protected function concatenateFiles($filePathList)
    {
        $output = '';

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
        $dependencyTree = new DependencyTree( $inputFilename );
        $dependencySets = $dependencyTree->getDependencySets();

        foreach( $dependencySets as $dependencySet )
        {
            try {
                $result = $this->compileDependencySet( $dependencySet );
                if ( is_callable( $statusCallback ) ) {
                    call_user_func( $statusCallback, 'Successfully compiled Dependency Set: ' . $result->filename, 'success' );
                }

                if ( strlen( $result->warnings ) > 0 ) {
                    if ( is_callable( $statusCallback ) ) {
                        call_user_func( $statusCallback, 'Warnings: ' . PHP_EOL . $result->warnings, 'warning' );
                    }
                }

                $fileCompilationResult = new FileCompilationResult();

                // Write compiled file
                $outputFilename = $result->path . '/' . $result->filename;
                $outputFile = file_put_contents( $outputFilename, $result->contents );
                if ( $outputFile === FALSE )
                {
                    throw new CannotWriteException("Cannot write to {$outputFilename}", null, $outputFilename);
                }
                $fileCompilationResult->setCompiledPath( $result->filename );

                // Write manifest
                $outputFilename = $result->path . '/' .$result->manifestFilename;
                $outputFile = file_put_contents( $outputFilename, $result->manifestContents );
                if ( $outputFile === FALSE )
                {
                    throw new CannotWriteException("Cannot write to {$outputFilename}", null, $outputFilename);
                }
                $fileCompilationResult->setManifestPath( $result->manifestFilename );

                $fileCompilationResult->setSourcePath( $result->path );

                $compiledFiles[] = $fileCompilationResult;
            }
            catch ( ParsingException $e )
            {
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

                echo "[Clearing] Detected package {$file->getFilename()}\n";
                $sourceFileCount++;

                $compiledFilename = $this->getCompiledFilename( $file->getRealPath() );
                $manifestFilename = $this->getManifestFilename( $file->getRealPath() );

                if ( is_file( $compiledFilename ) ) {
                    echo '[Clearing] Removing ' . $compiledFilename . "\n";
                    $unlinkSuccess = unlink( $compiledFilename );
                    if ( !$unlinkSuccess )
                    {
                        echo '[Clearing] Failed to remove ' . $compiledFilename . "\n";
                        $success = false;
                        continue;
                    }
                    $fileCount++;
                }
                if ( is_file( $manifestFilename ) ) {
                    echo '[Clearing] Removing ' . $manifestFilename . "\n";
                    $unlinkSuccess = unlink( $manifestFilename );
                    if ( !$unlinkSuccess )
                    {
                        echo '[Clearing] Failed to remove ' . $manifestFilename . "\n";
                        $success = false;
                        continue;
                    }
                    $fileCount++;
                }

            }
        }

        echo "[Clearing] Detected $sourceFileCount packages, resulting in $fileCount files being removed.\n";
        return $success;
    }

    public function parseFolderForSourceFiles($folderPath)
    {
        /** @var $file SplFileObject */

        $dirIterator = new RecursiveDirectoryIterator( $folderPath );
        $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);
        $fileCount = 0;
        $files = array();

        foreach ($iterator as $file) {
            if ( $file->isFile() && preg_match( '/.js$/', $file->getFilename() ) && !preg_match( '/.compiled.js$/', $file->getFilename() ) ) {
                $files[] = $file->getRealPath();
                $fileCount++;
            }
        }

        return $files;
    }


}