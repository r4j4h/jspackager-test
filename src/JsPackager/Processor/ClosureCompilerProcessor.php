<?php
/**
 * The Compiler class takes an ordered array of files and dependent packages and compiles and minifies them
 * using the Google Closure Compiler and generates an accompanying manifest file.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2013 WebPT, Inc.
 */

namespace JsPackager\Processor;

use JsPackager\Exception\Parsing;
use JsPackager\Helpers\StreamingExecutor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ClosureCompilerProcessor implements SimpleProcessorInterface
{

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var string Optional. Set this to any desired additional GCC .jar parameters and they will be added to the end.
     */
    public $extraCommandParams = '';

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * @param SimpleProcessorParams $params
     * @return ProcessingResult
     * @throws Parsing
     * @throws \Exception
     */
    public function process(SimpleProcessorParams $params)
    {
        $this->logger->debug("Compiling with Google Closure Compiler .jar..."); // todo update these texts
        $results = $this->compileFileListUsingClosureCompilerJar( $params->orderedFilePaths );
        $this->logger->debug("Finished compiling with Google Closure Compiler .jar.");
        return $results;
    }


    const GCC_COMPILATION_LEVEL = 'SIMPLE_OPTIMIZATIONS'; //WHITESPACE_ONLY, SIMPLE_OPTIMIZATIONS, ADVANCED_OPTIMIZATION;
    const GCC_API_OUTPUT_FORMAT = 'json';
    const GCC_API_COMPILER_URL  = 'http://closure-compiler.appspot.com/compile';

    /**
     * Path to Java
     * @var string
     */
    public $javaPath = '/usr/bin/java';

    /**
     * Path to google closure compiler
     * @var string
     */
    public $gccJarPath = 'bin/google/closure_compiler';

    /**
     * Filename of google closure compiler .jar
     * @var string
     */
    public $gccJarFilename = 'compiler.jar';


    /**
     * @param array $fileList
     * @return ProcessingResult
     * @throws \Exception
     */
    public function compileFileListUsingClosureCompilerJar($fileList)
    {
        // Prepare command
        $command = $this->generateClosureCompilerCommandString( $fileList );

        list($stdout, $stderr, $returnCode, $successful) = $this->executeClosureCompilerCommand($command);

        $processingResult = $this->handleClosureCompilerOutput($stdout, $stderr, $returnCode, $successful);

        return $processingResult;

    }

    /**
     * Generate command line arguments for Google Closure Compiler
     *
     * @param array $fileList
     * @return string
     */
    protected function generateClosureCompilerCommandString($fileList)
    {
        $pathStart = dirname( dirname( dirname( dirname(__FILE__) ) ) );
        $compilerPath = $pathStart . '/' . $this->gccJarPath. '/' . $this->gccJarFilename;

        // Prepare command
        $command = $this->javaPath . ' -jar "' . $compilerPath . '" ';
        foreach( $fileList as $file )
        {
            $command .= "--js \"${file}\" ";
        }
//        $command .= '--js_output_file "output.js" ';    // Set output file
        $command .= '--compilation_level=' . self::GCC_COMPILATION_LEVEL . ' ';
//        $command .= '--warning_level=VERBOSE ';
        $command .= '--summary_detail_level=3 ';

        $command .= $this->extraCommandParams;

        $this->logger->debug('Running shell command: ' . $command);

        $command = escapeshellcmd( $command );

        return $command;
    }


    /**
     * @param $command
     * @param $pipes
     * @return array
     * @throws \Exception
     */
    protected function executeClosureCompilerCommand($command)
    {
        // Prepare process pipe file pointers
        $descriptorSpec = array(
            0 => array("pipe", "r"), // stdin
            1 => array("pipe", "w"), // stdout
            2 => array("pipe", "w"), // stderr
        );

        // Open process
        $process = proc_open($command, $descriptorSpec, $pipes, null, null);
        if ($process === FALSE) {
            throw new \Exception('Unable to open java to run the Closure Compiler .jar');
        }

        // Execute process
        list($stdout, $stderr, $returnCode, $successful) = StreamingExecutor::streaming_exec($pipes, $process);
        return array($stdout, $stderr, $returnCode, $successful);
    }

    protected function handleClosureCompilerOutput($stdout, $stderr, $returnCode, $successful)
    {
        $errorCount = 0;
        $warningCount = 0;

        // Google Closure Compiler prints a error/warning count through stderr, so lets grab from that
        $errorLines = explode("\n", $stderr); // Break by newlines
        $numErrorLines = count($errorLines);
        if ( $numErrorLines > 1 ) {
            $lastErrorLine = $errorLines[count($errorLines) - 2]; // Last last line is a newline so we want line before
            $fragments = explode(", ", $lastErrorLine);
            sscanf($fragments[0], '%d error', $errorCount);
            sscanf($fragments[1], '%d warning', $warningCount);

            if ($errorCount > 0) {
                $successful = false;
            }
        }

        return new ProcessingResult(
            $successful,
            $returnCode,
            $stdout,
            $stderr,
            $errorCount
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



}
