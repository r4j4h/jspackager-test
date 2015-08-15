<?php
/**
 * The FileHandler class is used solely to wrap file i/o PHP globals for unit testing.
 *
 * See DependencyTreeParser class for use.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager\Helpers;

class FileHandler
{

    /**
     * (PHP 4, PHP 5)<br/>
     * Tells whether the filename is a regular file
     * @link http://php.net/manual/en/function.is-file.php
     * @param string $filename <p>
     * Path to the file.
     * </p>
     * @return bool true if the filename exists and is a regular file, false
     * otherwise.
     */
    public function is_file($filename) {
        return is_file($filename);
    }

    public function fopen( $file, $mode = 'r' ) {
        return fopen( $file, $mode );
    }

    public function fgets( $handle ) {
        return fgets( $handle );
    }

    public function fclose( $handle ) {
        return fclose( $handle );
    }

}
