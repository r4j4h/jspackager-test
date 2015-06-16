<?php

namespace JsPackager\Helpers;

class StreamingExecutor
{

    /**
     * Execute a process in streaming mode
     *
     * How to use:
     *
     *    $command = '/usr/bin/something someparam -v";
     *
     *    // Prepare process pipe file pointers
     *    $descriptorSpec = array(
     *          0 => array("pipe", "r"), // stdin
     *          1 => array("pipe", "w"), // stdout
     *          2 => array("pipe", "w"), // stderr
     *    );
     *
     *    // Open process
     *    $process = proc_open( $command, $descriptorSpec, $pipes, null, null );
     *    if ( $process === FALSE )
     *    {
     *          throw new \Exception('Unable to open java to run the Closure Compiler .jar');
     *    }
     *
     *    // Execute process
     *    list($stdout, $stderr, $returnCode, $successful) = $this->streaming_exec($pipes, $process);
     *
     * @param array $pipes Valid pipes opened from something like proc_open
     * @param resource $process A valid process from something like proc_open
     * @return array
     */
    public static function streaming_exec($pipes, $process)
    {
        // Grab outputs
        $read_output = $read_error = false;
        $buffer_len = $prev_buffer_len = 0;
        $ms = 10;
        $stdout = '';
        $read_output = true;
        $stderr = '';
        $read_error = true;
        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        $buffer_len = 0;

        // dual reading of STDOUT and STDERR stops one full pipe blocking the other, because the external script is waiting
        while ($read_error != false or $read_output != false) {
            if ($read_output != false) {
                if (feof($pipes[1])) {
                    fclose($pipes[1]);
                    $read_output = false;
                } else {
                    $str = fgets($pipes[1], 1024);
                    $len = strlen($str);
                    if ($len) {
                        $stdout .= $str;
                        $buffer_len += $len;
                    }
                }
            }

            if ($read_error != false) {
                if (feof($pipes[2])) {
                    fclose($pipes[2]);
                    $read_error = false;
                } else {
                    $str = fgets($pipes[2], 1024);
                    $len = strlen($str);
                    if ($len) {
                        $stderr .= $str;
                        $buffer_len += $len;
                    }
                }
            }

            if ($buffer_len > $prev_buffer_len) {
                $prev_buffer_len = $buffer_len;
                $ms = 10;
            } else {
                usleep($ms * 1000); // sleep for $ms milliseconds
                if ($ms < 160) {
                    $ms = $ms * 2;
                }
            }
        }

        // Clean up
        $returnCode = proc_close($process);
        $successful = ($returnCode === 0);
        return array($stdout, $stderr, $returnCode, $successful);
    }

}