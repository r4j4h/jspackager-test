<?php
/**
 * @category JsPackager
 */
namespace JsPackager\Helpers;


class ArrayTraversalService
{

    /**
     * Grab the last item in an array.
     *
     * Thanks, http://stackoverflow.com/a/8205332/1347604
     *
     * @param $array
     * @return null
     */
    public function array_last($array) {
        if (count($array) < 1)
            return null;

        $keys = array_keys($array);
        return $array[$keys[sizeof($keys) - 1]];
    }

}