<?php

namespace JsPackager\Processor\Unit;

class SassProcessorTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessFeedsEachFileInOrderToSass()
    {

    }

    public function testProcessPassesResultThroughTempFileFactory()
    {

    }

    public function testProcessAddsSourcemapAsDependency()
    {
        // [src.scss] -> [src.sourcemap.css, src.css]
    }
}