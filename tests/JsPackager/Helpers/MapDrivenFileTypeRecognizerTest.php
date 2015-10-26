<?php

namespace JsPackager\Helpers\Unit;

use JsPackager\Helpers\MapDrivenFileTypeRecognizer;

class MapDrivenFileTypeRecognizerTest extends \PHPUnit_Framework_TestCase
{

    public function testFoo()
    {
        $hein = "bobby.js";
        $hoo = "bubba.compiled.js";
        $lars = "button.tag";

        $re = new MapDrivenFileTypeRecognizer();
        $re->addRecognition('javascript',  array('JsPackager\Helpers\FileTypeRecognizer', 'isJavaScriptFile'));
        $re->addRecognition('stylesheet',  array('JsPackager\Helpers\FileTypeRecognizer', 'isStylesheetFile'));
        $re->addRecognition('source-file', array('JsPackager\Helpers\FileTypeRecognizer', 'isSourceFile'));

        $heinAs = $re->isRecognizedAs( $hein );
        $hooAs = $re->isRecognizedAs( $hoo );
        $larsAs = $re->isRecognizedAs( $lars );
        $heinisScript = $re->recognize('javascript', $hein);
        $heinisStyle = $re->recognize('stylesheet', $hein);
        $heinisSource = $re->recognize('source-file', $hein);

        try {
            $heinisTag = $re->recognize('tag', $hein);
            $this->fail('Should throw on unknown tag');
        } catch (\Exception $e) {

        }

        $this->assertContains('javascript', $heinAs);
        $this->assertContains('source-file', $heinAs);
        $this->assertNotContains('stylesheet', $heinAs);

        $this->assertContains('javascript', $hooAs);
        $this->assertNotContains('source-file', $hooAs);
        $this->assertNotContains('stylesheet', $hooAs);

        $this->assertNotContains('javascript', $larsAs);
        $this->assertNotContains('source-file', $larsAs);
        $this->assertNotContains('stylesheet', $larsAs);

        $this->assertTrue( $heinisScript );
        $this->assertFalse( $heinisStyle );
        $this->assertTrue( $heinisSource );

    }

}