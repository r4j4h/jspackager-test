<?php

namespace JsPackagerTest;

use JsPackager\Annotations\AnnotationHandlerParameters;
use JsPackager\File;
use JsPackager\Helpers\Reflection;
use JsPackager\Resolver\AnnotationBasedFileResolver;
use JsPackager\ResolverContext;

/**
 * @group      JsPackager
 */
class AnnotationBasedFileResolverTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';



    /******************************************************************
     * getAnnotationsFromFile
     *****************************************************************/

    /**
     * @return \JsPackager\FileHandler
     */
    private function getMockedFileHandler()
    {
        return $this->getMock('JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
    }


    /**
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "// JS code";
        $resolver = new AnnotationBasedFileResolver();


        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

         $resolver->setFileHandler( $mockedFileHandler );

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $this->assertArrayHasKey('annotations', $annotationResponse);
        $this->assertArrayHasKey('orderingMap', $annotationResponse);
        $this->assertEmpty( $annotationResponse['orderingMap'] );


        $annotations = $annotationResponse['annotations'];
        $this->assertArrayHasKey('require', $annotations);
        $this->assertArrayHasKey('requireRemote', $annotations);
        $this->assertArrayHasKey('requireStyle', $annotations);
        $this->assertArrayHasKey('root', $annotations);
        $this->assertArrayHasKey('tests', $annotations);
        $this->assertEmpty( $annotations['require'] );
        $this->assertEmpty( $annotations['requireRemote'] );
        $this->assertEmpty( $annotations['requireStyle'] );
        $this->assertEmpty( $annotations['root'] );

        return $annotationResponse;
    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsReturnsOrderingMap($annotationResponse)
    {
        $this->assertArrayHasKey('orderingMap', $annotationResponse);
    }


    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsRequireAnnotations()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@require bla.js";
        $lineC = "// Some comment";
        $lineD = "@require other/bla.js";
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, $lineD, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $resolver->setFileHandler( $mockedFileHandler );

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertContains( 'bla.js', $annotations['require'] );
        $this->assertContains( 'other/bla.js', $annotations['require'] );
        $this->assertEmpty( $annotations['requireRemote'] );
        $this->assertEmpty( $annotations['requireStyle'] );
        $this->assertEmpty( $annotations['root'] );


        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'require', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'require', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );
    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsRemoteRequires()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@requireRemote bla.js";
        $lineC = "// Some comment";
        $lineD = "@requireRemote other/bla.js";
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, $lineD, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $resolver->setFileHandler( $mockedFileHandler );

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertEmpty( $annotations['require'] );
        $this->assertContains( 'bla.js', $annotations['requireRemote'] );
        $this->assertContains( 'other/bla.js', $annotations['requireRemote'] );
        $this->assertEmpty( $annotations['requireStyle'] );
        $this->assertEmpty( $annotations['root'] );


        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'requireRemote', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireRemote', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );

    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsStylesheetRequires()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@requireStyle bla.css";
        $lineC = "// Some comment";
        $lineD = "@requireStyle other/bla.css";
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, $lineD, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $resolver->setFileHandler( $mockedFileHandler );

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertEmpty( $annotations['require'] );
        $this->assertEmpty( $annotations['requireRemote'] );
        $this->assertContains( 'bla.css', $annotations['requireStyle'] );
        $this->assertContains( 'other/bla.css', $annotations['requireStyle'] );
        $this->assertEmpty( $annotations['root'] );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'requireStyle', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireStyle', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );
    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsRootAnnotation()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@root";
        $lineC = "// Some comment";
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $resolver->setFileHandler( $mockedFileHandler );

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertEmpty( $annotations['require'] );
        $this->assertEmpty( $annotations['requireRemote'] );
        $this->assertEmpty( $annotations['requireStyle'] );
        $this->assertTrue( $annotations['root'] );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'root', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
    }

    /**
     * @depends testGetAnnotationsFromFileGetsRootAnnotation
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsRootAnnotationWithWhitespace()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@root     ";
        $lineC = "// Some comment";
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $resolver->setFileHandler( $mockedFileHandler );

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertTrue( $annotations['root'] );


        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'root', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
    }



    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsNoCompileWithNormalScriptAnnotation()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@nocompile";
        $lineC = "// Some comment";
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $resolver->setFileHandler( $mockedFileHandler );

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertEmpty( $annotations['require'] );
        $this->assertEmpty( $annotations['requireRemote'] );
        $this->assertEmpty( $annotations['requireStyle'] );
        $this->assertEmpty( $annotations['root'] );
        $this->assertTrue( $annotations['nocompile'] );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'nocompile', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );

    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileParsesNoCompileWithRootAnnotation()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@root";
        $lineC = "@nocompile";
        $lineD = "// Some comment";
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, $lineD, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $resolver->setFileHandler( $mockedFileHandler );

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertTrue( $annotations['nocompile'] );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'root', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );

        $this->assertEquals( 'nocompile', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[1]['annotationIndex'], "order is meaningless for boolean flag annotations" );
    }



    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsAllAnnotations()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "// @root ";
        $lineC = "// @require bob.js";
        $lineD = "// @requireRemote common.js ";
        $lineE = "// @requireStyle beautiful.css rawr.css ";
        $lineF = "// Some comment";
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, $lineD, $lineE, $lineF, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $resolver->setFileHandler( $mockedFileHandler );

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertContains( 'bob.js', $annotations['require'] );
        $this->assertContains( 'common.js', $annotations['requireRemote'] );
        $this->assertContains( 'beautiful.css', $annotations['requireStyle'] );
        $this->assertContains( 'rawr.css', $annotations['requireStyle'] );
        $this->assertTrue( $annotations['root'] );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'root', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'require', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireRemote', $annotationOrderingMap[2]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[2]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireStyle', $annotationOrderingMap[3]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[3]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireStyle', $annotationOrderingMap[4]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[4]['annotationIndex'], "Should reflect appropriate order" );
    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileWithMultipleArguments()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "// @root ";
        $lineC = "// /!#5/ttgeag   @require   bob.js billy.js  ";
        $lineD = "// @requireRemote common.js   rare.js";
        $lineE = "// @root "; // For testing ordering map
        $lineF = "// @requireStyle beautiful.css rawr.css ";
        $lineG = "// Some comment";
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, $lineD, $lineE, $lineF, $lineG, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $resolver->setFileHandler( $mockedFileHandler );

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertContains( 'bob.js', $annotations['require'] );
        $this->assertContains( 'billy.js', $annotations['require'] );

        $this->assertContains( 'common.js', $annotations['requireRemote'] );
        $this->assertContains( 'rare.js', $annotations['requireRemote'] );

        $this->assertContains( 'beautiful.css', $annotations['requireStyle'] );
        $this->assertContains( 'rawr.css', $annotations['requireStyle'] );
        $this->assertTrue( $annotations['root'] );


        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'root', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'require', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'require', $annotationOrderingMap[2]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[2]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireRemote', $annotationOrderingMap[3]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[3]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireRemote', $annotationOrderingMap[4]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[4]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'root', $annotationOrderingMap[5]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[5]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireStyle', $annotationOrderingMap[6]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[6]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireStyle', $annotationOrderingMap[7]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[7]['annotationIndex'], "Should reflect appropriate order" );
    }

    public function testGetAnnotationsOnlyUsesMappedAnnotations()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "// @root ";
        $lineC = "// /!#5/ttgeag   @require   bob.js billy.js  ";
        $lineD = "// @requireRemote common.js   rare.js";
        $lineE = "// @root "; // For testing ordering map
        $lineF = "// @requireStyle beautiful.css rawr.css ";
        $lineG = "// Some comment";
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, $lineD, $lineE, $lineF, $lineG, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $resolver->setFileHandler( $mockedFileHandler );

        $resolver->annotationResponseHandlerMapping = array(
            'bogus' => function() {},
            'requireStyle' => function() {}
        );

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertArrayHasKey( 'bogus', $annotations );
        $this->assertArrayNotHasKey( 'root', $annotations );
        $this->assertArrayHasKey( 'requireStyle', $annotations );
        $this->assertEquals( 2, count( $annotations['requireStyle'] ), "Should detect the two requireStyle references" );
        $this->assertArrayNotHasKey( 'require', $annotations );
    }

    public function testGetAnnotationsOnlyUsesMappedAnnotations2()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "// @root ";
        $lineC = "// /!#5/ttgeag   @require   bob.js billy.js  ";
        $lineD = "// @requireRemote common.js   rare.js";
        $lineE = "// @root "; // For testing ordering map
        $lineF = "// @requireStyle beautiful.css rawr.css ";
        $lineG = "// Some comment";
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, $lineD, $lineE, $lineF, $lineG, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $resolver->setFileHandler( $mockedFileHandler );

        $resolver->annotationResponseHandlerMapping = array(
            'bogus' => function() {},
            'root' => function() {}
        );

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertArrayHasKey( 'bogus', $annotations );
        $this->assertArrayHasKey( 'root', $annotations );
        $this->assertArrayNotHasKey( 'requireStyle', $annotations );
        $this->assertArrayNotHasKey( 'require', $annotations );
    }

    /******************************************************************
     * translateMappingIntoExistingFile
     *****************************************************************/

    public function testTranslateMappingUsesMappedAnnotations()
    {
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls('fake file contents @bogus', false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $fileMock = $this->getMock('JsPackager\File', array('getFullPath'), array('mocked', false, $mockedFileHandler));
        $fileMock->expects($this->any())
            ->method('getFullPath')
            ->will($this->returnValue('mocked'));

        $resolver->setFileHandler( $mockedFileHandler );

        $resolver->annotationResponseHandlerMapping = array(
            'bogus' => function(AnnotationHandlerParameters $params) { $params->file->bogus = true; }
        );

        $annotationResponse = array(
            'annotations' => array(
                'bogus' => true,
                'riffraff' => 'skip me'
            ),
            'orderingMap' => array(
                array(
                    'action' => 'bogus',
                    'annotationIndex' => 0
                ),
                array(
                    'action' => 'riffraff',
                    'annotationIndex' => 0
                )
            )
        );

        $context = new ResolverContext();

        $annotationResponse = Reflection::invoke( $resolver, 'translateMappingIntoExistingFile', array( $annotationResponse, $fileMock, $context ) );
        $this->assertEquals(true, $annotationResponse->bogus);
    }

    public function testTranslateMappingToFileMarksRoots()
    {
        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls('fake file contents', false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        /**
         * @var File $fileMock
         */
        $fileMock = $this->getMock('JsPackager\File', array('getFullPath'), array('mocked', false, $mockedFileHandler));
        $fileMock->expects($this->any())
            ->method('getFullPath')
            ->will($this->returnValue('mocked'));

        $resolver->setFileHandler( $mockedFileHandler );


        $annotationResponse = array(
            'annotations' => array(
                'root' => true
            ),
            'orderingMap' => array(
                array(
                    'action' => 'root',
                    'annotationIndex' => 0
                )
            )
        );

        $context = new ResolverContext();

        $this->assertEquals( $fileMock->isRoot, false );

        $annotationResponse = Reflection::invoke( $resolver, 'translateMappingIntoExistingFile', array( $annotationResponse, $fileMock, $context ) );

        $this->assertEquals( $fileMock->isRoot, true );

    }
    public function testTranslateMappingToFileMarksNoCompile()
    {

        $resolver = new AnnotationBasedFileResolver();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls('fake file contents', false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        /**
         * @var File $fileMock
         */
        $fileMock = $this->getMock('JsPackager\File', array('getFullPath'), array('mocked', false, $mockedFileHandler));
        $fileMock->expects($this->any())
            ->method('getFullPath')
            ->will($this->returnValue('mocked'));

        $resolver->setFileHandler( $mockedFileHandler );


        $annotationResponse = array(
            'annotations' => array(
                'nocompile' => true
            ),
            'orderingMap' => array(
                array(
                    'action' => 'nocompile',
                    'annotationIndex' => 0
                )
            )
        );

        $context = new ResolverContext();

        $this->assertEquals( $fileMock->isMarkedNoCompile, false );

        $annotationResponse = Reflection::invoke( $resolver, 'translateMappingIntoExistingFile', array( $annotationResponse, $fileMock, $context ) );

        $this->assertEquals( $fileMock->isMarkedNoCompile, true );
    }

}
