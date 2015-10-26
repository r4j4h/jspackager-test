<?php

namespace JsPackager\Annotations\Unit;

use JsPackager\Annotations\AnnotationHandlerParameters;
use JsPackager\Annotations\AnnotationHandlers\IsMarkedNoCompiledHandler;
use JsPackager\Annotations\AnnotationHandlers\RequireRemote;
use JsPackager\Annotations\AnnotationHandlers\RequireRemoteStyleAnnotationHandler;
use JsPackager\Annotations\AnnotationHandlers\RootAnnotationHandler;
use JsPackager\Annotations\AnnotationParser;
use JsPackager\File;
use JsPackager\Helpers\FileHandler;
use JsPackager\Helpers\Reflection;
use JsPackager\PathBasedFile;
use JsPackager\Resolver\AnnotationBasedFileResolver;
use JsPackager\Resolver\DependencyTreeParser;
use JsPackager\AnnotationBasedResolverContext;
use org\bovigo\vfs\vfsStream;
use Psr\Log\NullLogger;

/**
 * @group      JsPackager
 */
class AnnotationParserTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';

    /******************************************************************
     * getAnnotationsFromFile
     *****************************************************************/

    /**
     * @return \JsPackager\Helpers\FileHandler
     */
    private function getMockedFileHandler()
    {
        return $this->getMock('JsPackager\Helpers\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
    }


    /**
     */
    public function testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "// JS code";

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

        // todo there should be a test that tests this case this isnt failing anything
        $logger = new NullLogger();
        $rootHandler = new RootAnnotationHandler();
        $noCompileHandler = new IsMarkedNoCompiledHandler();
        $requireRemoteStyleHandler = new RequireRemoteStyleAnnotationHandler(
            'remote', '@remote', false, $logger
        );
        $requireRemoteHandler = new RequireRemote(
            'remote', '@remote', false, $logger
        );

        $mapping = array(
            'requireRemote'     => array($requireRemoteHandler, 'doAnnotation_requireRemote' ),
            'require'           => array($requireRemoteHandler, 'doAnnotation_require' ),
            'requireRemoteStyle'=> array($requireRemoteStyleHandler, 'doAnnotation_requireRemoteStyle' ),
            'requireStyle'      => array($requireRemoteHandler, 'doAnnotation_requireStyle' ),
            'tests'             => array($requireRemoteHandler, 'doAnnotation_tests' ),
            'testsRemote'       => array($requireRemoteHandler, 'doAnnotation_testsRemote' ),
            'root'              => array($rootHandler, 'doAnnotation_root' ),
            'nocompile'         => array($noCompileHandler, 'doAnnotation_noCompile' )
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

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
     */
    public function testGetAnnotationsReturnsOrderingMap($annotationResponse)
    {
        $this->assertArrayHasKey('orderingMap', $annotationResponse);
    }


    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     */
    public function testGetAnnotationsFromFileGetsRequireAnnotations()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@require bla.js";
        $lineC = "// Some comment";
        $lineD = "@require other/bla.js";

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


        $logger = new NullLogger();
        $requireRemoteHandler = new RequireRemote(
            'remote', '@remote', false, $logger
        );

        $mapping = array(
            'require' => array($requireRemoteHandler, 'doAnnotation_require' ),
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertContains( 'bla.js', $annotations['require'] );
        $this->assertContains( 'other/bla.js', $annotations['require'] );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'require', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'require', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );
    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     */
    public function testGetAnnotationsFromFileGetsRemoteRequires()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@requireRemote bla.js";
        $lineC = "// Some comment";
        $lineD = "@requireRemote other/bla.js";

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

        $logger = new NullLogger();
        $requireRemoteHandler = new RequireRemote(
            'remote', '@remote', false, $logger
        );

        $mapping = array(
            'requireRemote' => array($requireRemoteHandler, 'doAnnotation_requireRemote' )
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertNotContains( 'require', $annotations, 'Does not add require entries' );
        $this->assertContains( 'bla.js', $annotations['requireRemote'] );
        $this->assertContains( 'other/bla.js', $annotations['requireRemote'] );
        $this->assertNotContains( 'requireStyle', $annotations );
        $this->assertNotContains( 'root', $annotations );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'requireRemote', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireRemote', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );

    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     */
    public function testGetAnnotationsFromFileGetsStylesheetRequires()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@requireStyle bla.css";
        $lineC = "// Some comment";
        $lineD = "@requireStyle other/bla.css";

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


        $requireRemoteHandler = new RequireRemote(
            'remote', '@remote', false, new NullLogger()
        );

        $mapping = array(
            'requireStyle'      => array($requireRemoteHandler, 'doAnnotation_requireStyle' ),
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertNotContains( 'require', $annotations );
        $this->assertNotContains( 'requireRemote', $annotations );
        $this->assertContains( 'bla.css', $annotations['requireStyle'] );
        $this->assertContains( 'other/bla.css', $annotations['requireStyle'] );
        $this->assertNotContains( 'root', $annotations );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'requireStyle', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireStyle', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );
    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     */
    public function testGetAnnotationsFromFileGetsRootAnnotation()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@root";
        $lineC = "// Some comment";

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

        $rootHandler = new RootAnnotationHandler();
        $mapping = array(
            'root' => array($rootHandler, 'doAnnotation_root' ),
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertArrayNotHasKey( 'require', $annotations );
        $this->assertArrayNotHasKey( 'requireRemote', $annotations );
        $this->assertArrayNotHasKey( 'requireStyle', $annotations );
        $this->assertTrue( $annotations['root'] );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'root', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
    }

    /**
     * @depends testGetAnnotationsFromFileGetsRootAnnotation
     */
    public function testGetAnnotationsFromFileGetsRootAnnotationWithWhitespace()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@root     ";
        $lineC = "// Some comment";

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

        $rootHandler = new RootAnnotationHandler();
        $mapping = array(
            'root' => array($rootHandler, 'doAnnotation_root' ),
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertTrue( $annotations['root'] );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'root', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
    }


    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     */
    public function testGetAnnotationsFromFileGetsNoCompileWithNormalScriptAnnotation()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@nocompile";
        $lineC = "// Some comment";

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

        $noCompileHandler = new IsMarkedNoCompiledHandler();

        $mapping = array(
            'nocompile' => array($noCompileHandler, 'doAnnotation_noCompile' )
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

        $annotationResponse = Reflection::invoke( $resolver, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertArrayNotHasKey( 'require', $annotations );
        $this->assertArrayNotHasKey( 'requireRemote', $annotations );
        $this->assertArrayNotHasKey( 'requireStyle', $annotations );
        $this->assertArrayNotHasKey( 'root', $annotations );
        $this->assertTrue( $annotations['nocompile'] );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'nocompile', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );

    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     */
    public function testGetAnnotationsFromFileParsesNoCompileWithRootAnnotation()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@root";
        $lineC = "@nocompile";
        $lineD = "// Some comment";

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

        $logger = new NullLogger();
        $rootHandler = new RootAnnotationHandler();
        $noCompileHandler = new IsMarkedNoCompiledHandler();

        $mapping = array(
            'root'              => array($rootHandler, 'doAnnotation_root' ),
            'nocompile'         => array($noCompileHandler, 'doAnnotation_noCompile' )
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

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
     */
    public function testGetAnnotationsFromFileGetsAllAnnotations()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "// @root ";
        $lineC = "// @require bob.js";
        $lineD = "// @requireRemote common.js ";
        $lineE = "// @requireStyle beautiful.css rawr.css ";
        $lineF = "// Some comment";

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


        $logger = new NullLogger();
        $rootHandler = new RootAnnotationHandler();
        $noCompileHandler = new IsMarkedNoCompiledHandler();
        $requireRemoteStyleHandler = new RequireRemoteStyleAnnotationHandler(
            'rem4ote', '@re3mote', false, $logger
        );
        $requireRemoteHandler = new RequireRemote(
            'r5emote', '@r4emote', false, $logger
        );

        $mapping = array(
            'requireRemote'     => array($requireRemoteHandler, 'doAnnotation_requireRemote' ),
            'require'           => array($requireRemoteHandler, 'doAnnotation_require' ),
            'requireRemoteStyle'=> array($requireRemoteStyleHandler, 'doAnnotation_requireRemoteStyle' ),
            'requireStyle'      => array($requireRemoteHandler, 'doAnnotation_requireStyle' ),
            'tests'             => array($requireRemoteHandler, 'doAnnotation_tests' ),
            'testsRemote'       => array($requireRemoteHandler, 'doAnnotation_testsRemote' ),
            'root'              => array($rootHandler, 'doAnnotation_root' ),
            'nocompile'         => array($noCompileHandler, 'doAnnotation_noCompile' )
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

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


        $logger = new NullLogger();
        $rootHandler = new RootAnnotationHandler();
        $noCompileHandler = new IsMarkedNoCompiledHandler();
        $requireRemoteStyleHandler = new RequireRemoteStyleAnnotationHandler(
            'remote', '@remote', false, $logger
        );
        $requireRemoteHandler = new RequireRemote(
            'remote', '@remote', false, $logger
        );

        $mapping = array(
            'requireRemote'     => array($requireRemoteHandler, 'doAnnotation_requireRemote' ),
            'require'           => array($requireRemoteHandler, 'doAnnotation_require' ),
            'requireRemoteStyle'=> array($requireRemoteStyleHandler, 'doAnnotation_requireRemoteStyle' ),
            'requireStyle'      => array($requireRemoteHandler, 'doAnnotation_requireStyle' ),
            'tests'             => array($requireRemoteHandler, 'doAnnotation_tests' ),
            'testsRemote'       => array($requireRemoteHandler, 'doAnnotation_testsRemote' ),
            'root'              => array($rootHandler, 'doAnnotation_root' ),
            'nocompile'         => array($noCompileHandler, 'doAnnotation_noCompile' )
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

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

        $mapping = array(
            'bogus' => function() {},
            'requireStyle' => function() {}
        );
        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

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

        $mapping = array(
            'bogus' => function() {},
            'root' => function() {}
        );
        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

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

        $mapping = array(
            'bogus' => function(AnnotationHandlerParameters $params) { $params->file->addMetaData('bogus', true); }
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

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

        $context = new AnnotationBasedResolverContext();

        $annotationResponse = Reflection::invoke( $resolver, 'translateMappingIntoExistingFile', array( $annotationResponse, $fileMock, $context ) );
        $this->assertEquals(true, $annotationResponse->getMetaDataKey('bogus'));
    }

    public function testRootAnnotationHandlerMarksRoots()
    {

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

        $rootHandler = new RootAnnotationHandler();
        $mapping = array(
            'root' => array($rootHandler, 'doAnnotation_root' ),
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

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

        $context = new AnnotationBasedResolverContext();

        $metaData = $fileMock->getMetaData();
        $this->assertEquals( $metaData['isRoot'], false );

        $annotationResponse = Reflection::invoke( $resolver, 'translateMappingIntoExistingFile', array( $annotationResponse, $fileMock, $context ) );

        $metaData = $fileMock->getMetaData();
        $this->assertEquals( $metaData['isRoot'], true );

    }
    public function testIsMarkedNoCompiledHandlerMarksNoCompile()
    {

        $mockedFileHandler = $this->getSimpleMockedFileHandler();

        /**
         * @var File $fileMock
         */
        $fileMock = $this->getMock('JsPackager\File', array('getFullPath'), array('mocked', false, $mockedFileHandler));
        $fileMock->expects($this->any())
            ->method('getFullPath')
            ->will($this->returnValue('mocked'));

        $noCompileHandler = new IsMarkedNoCompiledHandler();
        $mapping = array(
            'nocompile' => array($noCompileHandler, 'doAnnotation_noCompile')
        );

        $resolver = new AnnotationParser($mapping, null, new NullLogger(), $mockedFileHandler);

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

        $context = new AnnotationBasedResolverContext();

        $this->assertEquals( $fileMock->getMetaDataKey('isMarkedNoCompile'), false );

        $annotationResponse = Reflection::invoke( $resolver, 'translateMappingIntoExistingFile', array( $annotationResponse, $fileMock, $context ) );

        $this->assertEquals( $fileMock->getMetaDataKey('isMarkedNoCompile'), true );
    }

    private function getSimpleMockedFileHandler()
    {
        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls('getSimpleMockedFileHandler fake file contents', false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        return $mockedFileHandler;
    }

}
