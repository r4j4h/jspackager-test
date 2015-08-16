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
    public $extraCommandParams;

    /**
     * @param LoggerInterface $logger
     * @param string [$extraCommandParams] Optional. Set this to any desired additional GCC .jar parameters and they will
     * be added to the end. Default: ''.
     */
    public function __construct(LoggerInterface $logger, $extraCommandParams = '')
    {
        $this->logger = $logger;
        $this->extraCommandParams = $extraCommandParams;
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


    public $gccCompilationLevel = 'SIMPLE_OPTIMIZATIONS'; //WHITESPACE_ONLY, SIMPLE_OPTIMIZATIONS, ADVANCED_OPTIMIZATION;

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
        $command .= '--compilation_level=' . $this->gccCompilationLevel . ' ';
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

}
