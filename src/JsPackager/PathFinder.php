<?php
/**
 * PathFinder helps get the relative travel path required to go from one absolute path to another.
 */

namespace JsPackager;

class PathFinder
{

    /**
     *
     * Happily borrowed from http://stackoverflow.com/a/2638272/1347604
     *
     * @param $from
     * @param $to
     * @return string
     */
    public function getRelativePathFromAbsoluteFiles($from, $to)
    {
        // some compatibility fixes for Windows paths
        $from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
        $to   = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
        $from = str_replace('\\', '/', $from);
        $to   = str_replace('\\', '/', $to);

        $from     = explode('/', $from);
        $to       = explode('/', $to);
        $relPath  = $to;

        foreach($from as $depth => $dir) {
            // find first non-matching dir
            if($dir === $to[$depth]) {
                // ignore this directory
                array_shift($relPath);
            } else {
                // get number of remaining dirs to $from
                $remaining = count($from) - $depth;
                if($remaining > 1) {
                    // add traversals up to first matching dir
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    break;
                } else {
                    $relPath[0] = './' . $relPath[0];
                }
            }
        }
        return implode('/', $relPath);
    }

    /**
     * From http://stackoverflow.com/a/14329380/1347604
     *
     *
     * @param $path
     * @param string $from
     * @return string
     */
    public function getRelativePathFromAbsoluteFiles2($from = __FILE__, $path )
    {
        $path = explode(DIRECTORY_SEPARATOR, $path);
        $from = explode(DIRECTORY_SEPARATOR, dirname($from.'.'));
        $common = array_intersect_assoc($path, $from);

        $base = array('.');
        if ( $pre_fill = count( array_diff_assoc($from, $common) ) ) {
            $base = array_fill(0, $pre_fill, '..');
        }
        $path = array_merge( $base, array_diff_assoc($path, $common) );
        return implode(DIRECTORY_SEPARATOR, $path);
    }
}
