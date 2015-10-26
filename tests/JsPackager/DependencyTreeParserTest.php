<?php

namespace JsPackager\Unit;

use JsPackager\Annotations\AnnotationHandlers\IsMarkedNoCompiledHandler;
use JsPackager\Annotations\AnnotationHandlers\RequireRemote;
use JsPackager\Annotations\AnnotationHandlers\RequireRemoteStyleAnnotationHandler;
use JsPackager\Annotations\AnnotationHandlers\RootAnnotationHandler;
use JsPackager\Annotations\AnnotationOrderMapping;
use JsPackager\Annotations\AnnotationParser;
use JsPackager\Helpers\FileHandler;
use JsPackager\Resolver\AnnotationBasedFileResolver;
use JsPackager\Resolver\DependencyTreeParser;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Exception\MissingFile as MissingFileException;
use org\bovigo\vfs\vfsStream;
use Psr\Log\NullLogger;

/**
 * @group      JsPackager
 */
class DependencyTreeParserTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';




    /******************************************************************
     * parseFile > Fundamentals
     *****************************************************************/


    public function testParseFileReturnsFileObject()
    {
        $basePath = self::fixturesBasePath . '0_deps';
        $filePath = $basePath . '/main.js';

        $parser = new AnnotationParser( array(), null, new NullLogger(), new FileHandler());
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertInstanceOf(
            'JsPackager\DependencyFileInterface',
            $dependencyTree,
            "parseFile's return value should be a DependencyFileInterface based object"
        );
    }

    /**
     * @depends testParseFileReturnsFileObject
     */
    public function testParseFileReturnsGivenFile()
    {
        $basePath = self::fixturesBasePath . '0_deps';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();

        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename, "Main file should be named main" );
        $this->assertEquals( 'js', $dependencyTree->filetype, "Main file should be of js filetype" );
        $this->assertEquals( $basePath, $dependencyTree->path, "Main file should have base path" );
        $this->assertFalse( $dependencyTree->getMetaDataKey('isRoot'), 'Main file should not be marked isRoot' );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('scripts'), "Main.js should have no dependent scripts" );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('stylesheets'), "Main.js should have no dependent stylesheets" );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('packages'), "Main.js should have no dependent packages" );
        $this->assertEmpty(
            $dependencyTree->getMetaDataKey('annotationOrderMap')->getAnnotationMappings(),
            "Main.js should have no annotations in its ordering map"
        );
    }

    /**
     * @depends testParseFileReturnsGivenFile
     */
    public function testParseFileReturnsGivenRootFile()
    {
        $basePath = self::fixturesBasePath . '0_deps_root';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertTrue( $dependencyTree->getMetaDataKey('isRoot'), 'Main.js should be marked isRoot' );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('scripts'));
        $this->assertEmpty( $dependencyTree->getMetaDataKey('stylesheets'));
        $this->assertEmpty( $dependencyTree->getMetaDataKey('packages'));
        $this->assertEmpty(
            $dependencyTree->getMetaDataKey('annotationOrderMap')->getAnnotationMappings(),
            "Main.js should have no annotations in its ordering map"
        );
    }

    /**
     * @depends testParseFileReturnsGivenFile
     */
    public function testParseFileLoadsDependentFile()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertFalse( $dependencyTree->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->getMetaDataKey('scripts') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('stylesheets') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('packages') );

        $scripts = $dependencyTree->getMetaDataKey('scripts');
        $this->assertCount(1, $scripts, 'Should have a dependent script' );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[0] );

        $this->assertEquals( 'dep_1', $scripts[0]->filename );
        $this->assertFalse( $scripts[0]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );

        $annotations = $dependencyTree->getMetaDataKey('annotationOrderMap')->getAnnotationMappings();
        $this->assertEquals(
            'require',
            $annotations[0]->getAnnotationName(),
            "Should reflect appropriate bucket"
        );
        $this->assertEquals(
            0,
            $annotations[0]->getAnnotationIndex(),
            "Should reflect appropriate order"
        );
    }

    /**
     * @depends testParseFileReturnsGivenRootFile
     */
    public function testParseFileLoadsDependentRootFile()
    {
        $basePath = self::fixturesBasePath . '1_dep_root';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertFalse( $dependencyTree->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->getMetaDataKey('scripts') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('stylesheets') );
        $this->assertNotEmpty( $dependencyTree->getMetaDataKey('packages') );

        $this->assertCount(1, $dependencyTree->getMetaDataKey('scripts'), 'Should have a dependent script' );
        $this->assertCount(1, $dependencyTree->getMetaDataKey('packages'), 'Should have a dependent script package entry' );

        $scripts = $dependencyTree->getMetaDataKey('scripts');
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[0] );
        $this->assertEquals( 'dep_1', $scripts[0]->filename );
        $this->assertTrue( $scripts[0]->getMetaDataKey('isRoot'), 'Dependent script should be marked isRoot');

        $packages = $dependencyTree->getMetaDataKey('packages');
        $this->assertEquals( $basePath . '/somePackage/dep_1.js', $packages[0] );

        $annotations = $dependencyTree->getMetaDataKey('annotationOrderMap')->getAnnotationMappings();
        $this->assertEquals(
            'require',
            $annotations[0]->getAnnotationName(),
            "Should reflect appropriate bucket"
        );
        $this->assertEquals(
            0,
            $annotations[0]->getAnnotationIndex(),
            "Should reflect appropriate order"
        );
    }

    private function getCommonAnnotationParser($remotePath = 'shared', $remoteSymbol = '@remote', $mutingMissingFileExceptions = false)
    {
        $logger = new NullLogger();

        $rootHandler = new RootAnnotationHandler();
        $noCompileHandler = new IsMarkedNoCompiledHandler();
        $requireRemoteStyleHandler = new RequireRemoteStyleAnnotationHandler(
            $remotePath, $remoteSymbol, $mutingMissingFileExceptions, $logger
        );
        $requireRemoteHandler = new RequireRemote(
            $remotePath, $remoteSymbol, $mutingMissingFileExceptions, $logger
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

        $parser = new AnnotationParser( $mapping, null, new NullLogger(), new FileHandler());

        return $parser;
    }
    /**
     * Leaving this test for clarity, stylesheets used to be File objects instead of behaving
     * like packages with full paths.
     *
     * Now this test verifies it by just seeing that the dependency tree in total does not have any
     * of the things that the stylesheet is annotated with.
     */
    public function testParseFileIgnoresAnnotationsInStylesheets()
    {
        $basePath = self::fixturesBasePath . 'css_with_annotations';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename, "main should be base file's name" );
        $this->assertEquals( 'js', $dependencyTree->filetype, "main.js should be a javascript file" );
        $this->assertEquals( $basePath, $dependencyTree->path, "main.js should be in the css_with_annotations fixture" );
        $this->assertFalse( $dependencyTree->getMetaDataKey('isRoot'), 'main.js should not be marked isRoot' );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('scripts'), "main.js should have no script dependencies" );
        $this->assertNotEmpty( $dependencyTree->getMetaDataKey('stylesheets'), "main.js should have 1 dependent stylesheet" );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('packages'), "main.js should have no packaged dependencies" );

        $stylesheets = $dependencyTree->getMetaDataKey('stylesheets');
        $this->assertCount(1, $stylesheets, 'Should have one dependent stylesheet' );
        $this->assertEquals( $basePath . '/' . 'main.css', $stylesheets[0] );
    }

    public function testParseFileThrowsMissingFileExceptionOnBrokenReferencesIfNotMuted()
    {
        // Test JavaScript files

        $basePath = self::fixturesBasePath . '1_broken_js_reference';
        $filePath = $basePath . '/main.js';
        $parser = $this->getCommonAnnotationParser();

        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());

        try {
            $dependencyTree = $treeParser->parseFile( $filePath );
            $this->fail('Set should throw a missing file exception');
        } catch (ParsingException $e) {
            $this->assertEquals(
                'tests/JsPackager/fixtures/1_broken_js_reference/heeper.js',
                $e->getErrors(),
                'Exception should contain failed file\'s path information'
            );

            $this->assertEquals(
                ParsingException::ERROR_CODE,
                $e->getCode(),
                'Exception should contain proper error code'
            );
        }


        // Test Stylesheet files

        $basePath = self::fixturesBasePath . '1_broken_css_reference';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());

        try {
            $dependencyTree = $treeParser->parseFile( $filePath );
            $this->fail('Set should throw a missing file exception');
        } catch (MissingFileException $e) {
            $this->assertEquals(
                'tests/JsPackager/fixtures/1_broken_css_reference/heeper.css',
                $e->getMissingFilePath(),
                'Exception should contain failed file\'s path information'
            );

            $this->assertEquals(
                MissingFileException::ERROR_CODE,
                $e->getCode(),
                'Exception should contain proper error code'
            );
        }
    }

    public function testParseFileDoesNotThrowMissingFileExceptionOnBrokenReferencesIfMuted()
    {
        // Test JavaScript files

        $basePath = self::fixturesBasePath . '1_broken_js_reference';
        $filePath = $basePath . '/main.js';
        $parser = $this->getCommonAnnotationParser();

        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), true, new FileHandler());

        $dependencyTree = $treeParser->parseFile( $filePath );
        $this->assertEquals(
            $dependencyTree->filename,
            "main",
            "Dependency Tree should have completed with main as the filename"
        );


        // Test Stylesheet files
        $basePath = self::fixturesBasePath . '1_broken_css_reference';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser('shared', '@remote', true);
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), true, new FileHandler());

        $dependencyTree = $treeParser->parseFile( $filePath );
        $this->assertEquals(
            $dependencyTree->filename,
            "main",
            "Dependency Tree should have completed with main as the filename"
        );
    }

    public function testParseFileThrowsParsingExceptionOnMissingFileDuringRecursionIntoFile()
    {
        // Test JavaScript files

        $basePath = self::fixturesBasePath . '1_broken_js_reference_recursive';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());

        try {
            $dependencyTree = $treeParser->parseFile( $filePath );
            $this->fail('Set should throw a missing file exception');
        } catch (ParsingException $e) {
            $this->assertEquals(
                'Failed to include missing file ' .
                '"tests/JsPackager/fixtures/1_broken_js_reference_recursive/heeper.js"'.
                ' while trying to parse ' .
                '"tests/JsPackager/fixtures/1_broken_js_reference_recursive/helper.js"',
                $e->getMessage(),
                'Exception should contain failed file\'s path information'
            );

            $this->assertEquals(
                'tests/JsPackager/fixtures/1_broken_js_reference_recursive/heeper.js',
                $e->getErrors(),
                'Exception should contain failed file\'s path information'
            );

            $this->assertEquals(
                ParsingException::ERROR_CODE,
                $e->getCode(),
                'Exception should contain proper error code'
            );
        }

    }


    public function testParseFileThrowsParsingExceptionOnMissingFileDuringRecursionIntoRemoteFile()
    {
        // Test JavaScript files

        $basePath = self::fixturesBasePath . '1_broken_js_reference_remote';
        $filePath = $basePath . '/main.js';


        $parser = $this->getCommonAnnotationParser( self::fixturesBasePath . '1_broken_js_reference_remote-remote' );

        $treeParser = new DependencyTreeParser(
            $parser, '@remote', self::fixturesBasePath . '1_broken_js_reference_remote-remote', null, new NullLogger(), false, new FileHandler()
        );

        try {
            $dependencyTree = $treeParser->parseFile( $filePath );
            $this->fail('Set should throw a missing file exception');
        } catch (ParsingException $e) {
            $this->assertEquals(
                'Failed to include missing file ' .
                '"tests/JsPackager/fixtures/1_broken_js_reference_remote-remote/heeper.js"'.
                ' while trying to parse ' .
                '"tests/JsPackager/fixtures/1_broken_js_reference_remote-remote/main.js"',
                $e->getMessage(),
                'Exception should contain failed file\'s path information'
            );

            $this->assertEquals(
                'tests/JsPackager/fixtures/1_broken_js_reference_remote-remote/heeper.js',
                $e->getErrors(),
                'Exception should contain failed file\'s path information'
            );

            $this->assertEquals(
                ParsingException::ERROR_CODE,
                $e->getCode(),
                'Exception should contain proper error code'
            );
        }

    }

    public function testParseFileMarksIsRemote()
    {
        // Test JavaScript files

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $filePath = $basePath . '/main.js';
        $remoteFolderPath = self::fixturesBasePath . 'remote_annotation-remote';

        $parser = $this->getCommonAnnotationParser($remoteFolderPath);
        $treeParser = new DependencyTreeParser($parser, '@remote', $remoteFolderPath, null, new NullLogger(), false, new FileHandler());

        $dependencyTree = $treeParser->parseFile( $filePath );
        $metaData = $dependencyTree->getMetaData();
        $this->assertFalse(
            $dependencyTree->getMetaDataKey('isRemote'),
            'Main file should not be marked remote'
        );

        $scripts = $dependencyTree->getMetaDataKey('scripts');
        $this->assertFalse(
            $scripts[0]->getMetaDataKey('isRemote'),
            'Local file before remotes in main file should not be marked remote'
        );
        $this->assertFalse(
            $scripts[1]->getMetaDataKey('isRemote'),
            'Local file in subfolder before remotes in main file should not be marked remote'
        );

        $this->assertTrue(
            $scripts[2]->getMetaDataKey('isRemote'),
            'Remote script file should be marked remote'
        );

        $rScripts = $scripts[2]->getMetaDataKey('scripts');
        $this->assertTrue(
            $rScripts[0]->getMetaDataKey('isRemote'),
            'Remote script\'s locally required file should be marked remote'
        );

        $this->assertTrue(
            $rScripts[1]->getMetaDataKey('isRemote'),
            'Remote script\'s remotely required file should be marked remote'
        );

        $this->assertTrue(
            $scripts[3]->getMetaDataKey('isRemote'),
            'Remote package file should be marked remote'
        );

        $this->assertTrue(
            $scripts[3]->getMetaDataKey('isRemote'),
            'Remote package file should be marked remote'
        );

        $rScripts = $scripts[3]->getMetaDataKey('scripts');
        $this->assertTrue(
            $rScripts[0]->getMetaDataKey('isRemote'),
            'Remote script\'s locally required file should be marked remote'
        );

        $this->assertTrue(
            $rScripts[1]->getMetaDataKey('isRemote'),
            'Remote script\'s remotely required file should be marked remote'
        );

        $this->assertFalse(
            $scripts[4]->getMetaDataKey('isRemote'),
            'Local file  in subfolder after remotes in main file should not be marked remote'
        );

        $this->assertFalse(
            $scripts[5]->getMetaDataKey('isRemote'),
            'Local file after remotes in main file should not be marked remote'
        );

    }


    /******************************************************************
     * parseFile > 2 Dependencies, #1 and #2
     * Fixture folder: 2_indep_deps
     *****************************************************************/
    public function testParseFile_2IndepDeps()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps';
        $filePath = $basePath . '/main.js';

        $remoteFolder = 'shared';
        $remoteSymbol = '@remote';
        $mutingMissingFileExceptions = false;
        $logger = new NullLogger();

        $rootHandler = new RootAnnotationHandler();
        $noCompileHandler = new IsMarkedNoCompiledHandler();
        $requireRemoteStyleHandler = new RequireRemoteStyleAnnotationHandler(
            $remoteFolder, $remoteSymbol, $mutingMissingFileExceptions, $logger
        );
        $requireRemoteHandler = new RequireRemote(
            $remoteFolder, $remoteSymbol, $mutingMissingFileExceptions, $logger
        );

        $annotationResponseHandlerMapping = array(
            'requireRemote'     => array($requireRemoteHandler, 'doAnnotation_requireRemote' ),
            'require'           => array($requireRemoteHandler, 'doAnnotation_require' ),
            'requireRemoteStyle'=> array($requireRemoteStyleHandler, 'doAnnotation_requireRemoteStyle' ),
            'requireStyle'      => array($requireRemoteHandler, 'doAnnotation_requireStyle' ),
            'tests'             => array($requireRemoteHandler, 'doAnnotation_tests' ),
            'testsRemote'       => array($requireRemoteHandler, 'doAnnotation_testsRemote' ),
            'root'              => array($rootHandler, 'doAnnotation_root' ),
            'nocompile'         => array($noCompileHandler, 'doAnnotation_noCompile' )
        );

        $parser = new AnnotationParser($annotationResponseHandlerMapping, null, new NullLogger(), new FileHandler());
        $treeParser = new DependencyTreeParser($parser, $remoteSymbol, $remoteFolder, null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertFalse( $dependencyTree->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->getMetaDataKey('scripts') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('stylesheets') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('packages') );

        $scripts = $dependencyTree->getMetaDataKey('scripts');
        $this->assertCount(2, $scripts, 'Should have two dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[0] );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[1] );

        $this->assertEquals( 'comp_a', $scripts[0]->filename );
        $this->assertEquals( 'comp_b', $scripts[1]->filename );
        $this->assertFalse( $scripts[0]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertFalse( $scripts[1]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
    }

    /******************************************************************
     * parseFile > 2 Dependencies, #1 and #2, #2 is root
     * Fixture folder: 2_indep_deps_1_root
     *****************************************************************/
    public function testParseFile_2IndepDeps1Root()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps_1_root';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertFalse( $dependencyTree->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->getMetaDataKey('scripts') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('stylesheets') );
        $this->assertNotEmpty( $dependencyTree->getMetaDataKey('packages') );

        $scripts = $dependencyTree->getMetaDataKey('scripts');
        $this->assertCount(2, $scripts, 'Should have two dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[0] );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[1] );

        $this->assertEquals( 'comp_a', $scripts[0]->filename );
        $this->assertEquals( 'comp_b', $scripts[1]->filename );
        $this->assertFalse( $scripts[0]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertTrue(  $scripts[1]->getMetaDataKey('isRoot'), 'File should be marked isRoot' );

        $packages = $dependencyTree->getMetaDataKey('packages');
        $this->assertEquals( $basePath . '/ComponentB/comp_b.js', $packages[0], "Should have comp_b package" );
    }


    /******************************************************************
     * parseFile > 2 Dependencies, #1 and #2
     *      #1 with its own dependency (#3)
     *      #2 with its own dependency (#4)
     * Fixture folder: 2_indep_deps_individ_deps
     *****************************************************************/

    public function testParseFile_2IndepDepsIndividDeps()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps_individ_deps';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertFalse( $dependencyTree->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->getMetaDataKey('scripts') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('stylesheets') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('packages') );

        $scripts = $dependencyTree->getMetaDataKey('scripts');
        $this->assertCount(2, $scripts, 'Should have two dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[0] );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[1] );

        $this->assertEquals( 'dep_1', $scripts[0]->filename );
        $this->assertEquals( 'dep_2', $scripts[1]->filename );
        $this->assertFalse( $scripts[0]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertFalse( $scripts[1]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );

        $dep1 = $scripts[0];
        $dep2 = $scripts[1];

        $scripts = $dep1->getMetaDataKey('scripts');
        $this->assertCount(1, $scripts, 'Should have one dependent script');
        $this->assertEquals( 'dep_3', $scripts[0]->filename );
        $this->assertFalse( $scripts[0]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertEmpty( $scripts[0]->getMetaDataKey('packages') );

        $scripts = $dep2->getMetaDataKey('scripts');
        $this->assertCount(1, $scripts, 'Should have one dependent script');
        $this->assertEquals( 'dep_4', $scripts[0]->filename );
        $this->assertFalse( $scripts[0]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertEmpty( $scripts[0]->getMetaDataKey('packages') );
    }

    /******************************************************************
     * parseFile > 2 Dependencies, #1 and #2
     *      #1 with a dependency (#3)
     *      #2 with same dependency (#3)
     * Fixture folder: 2_indep_deps_shared_deps
     *****************************************************************/

    public function testParseFile_2IndepDepsSharedDeps()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps_shared_deps';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertFalse( $dependencyTree->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->getMetaDataKey('scripts') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('stylesheets') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('packages') );

        $scripts = $dependencyTree->getMetaDataKey('scripts');
        $this->assertCount(2, $scripts, 'Should have two dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[0] );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[1] );

        $this->assertEquals( 'dep_1', $scripts[0]->filename );
        $this->assertEquals( 'dep_2', $scripts[1]->filename );
        $this->assertFalse( $scripts[0]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertFalse( $scripts[1]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );

        $dep1 = $scripts[0];
        $dep2 = $scripts[1];

        $dep1Scripts = $dep1->getMetaDataKey('scripts');
        $this->assertCount(1, $dep1Scripts, 'Should have one dependent script');
        $this->assertEquals( 'dep_3', $dep1Scripts[0]->filename );
        $this->assertFalse( $dep1Scripts[0]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertEmpty( $dep1Scripts[0]->getMetaDataKey('packages') );

        $dep2Scripts = $dep2->getMetaDataKey('scripts');
        $this->assertCount(1,$dep2Scripts, 'Should have one dependent script');
        $this->assertEquals( 'dep_3',$dep2Scripts[0]->filename );
        $this->assertFalse($dep2Scripts[0]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertEmpty($dep2Scripts[0]->getMetaDataKey('packages') );

        $this->assertEquals( $dep1Scripts[0]->filename, $dep2Scripts[0]->filename );
    }

    /******************************************************************
     * parseFile > 2 Dependencies, #1 and #2
     *      #1 with a dependency (#3)
     *      #2 with same dependency (#3)
     *      #3 is a root package
     * Fixture folder: 2_indep_deps_shared_package
     *****************************************************************/

    public function testParseFile_2IndepDepsSharedPackage()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps_shared_package';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        // Ensure root file was scanned properly
        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );

        // Ensure root file (main.js) has #1 and #2 scripts but no packages (should it have a package?)
        $this->assertFalse( $dependencyTree->getMetaDataKey('isRoot'), 'Main file should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->getMetaDataKey('scripts') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('stylesheets') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('packages') );

        // Ensure it has #1 and #2
        $scripts = $dependencyTree->getMetaDataKey('scripts');
        $this->assertCount(2, $scripts, 'Main file should have two dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[0] );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[1] );
        $this->assertEquals( 'dep_1', $scripts[0]->filename );
        $this->assertEquals( 'dep_2', $scripts[1]->filename );
// todo left off fixing unit tests to support getMetaDataKey - it's a bit gross on these tests with the [0]s and whatnot but gotta do it at least for now!
        // Shortcut for easier access and readability
        $dep1 = $scripts[0];
        $dep2 = $scripts[1];

        // Ensure #1 and #2 are not root packages themselves
        $this->assertFalse( $dep1->getMetaDataKey('isRoot'), 'Dep #1 should not be marked isRoot' );
        $this->assertFalse( $dep2->getMetaDataKey('isRoot'), 'Dep #2 should not be marked isRoot' );


        // Ensure dep #1 has #3 as a package
        $dep1scripts = $dep1->getMetaDataKey('scripts');
        $this->assertCount(1, $dep1scripts, 'Dep #1 should have one dependent script');
        $this->assertEquals( 'dep_3', $dep1scripts[0]->filename );
        $this->assertTrue( $dep1scripts[0]->getMetaDataKey('isRoot'), 'Dep #3 through Dep #1 should be marked isRoot' );
        $this->assertEmpty( $dep1scripts[0]->getMetaDataKey('packages') );

        // Ensure dep #2 has #3 as a package
        $scripts = $dep2->getMetaDataKey('scripts');
        $this->assertCount(1, $scripts, 'Dep #2 should have one dependent script');
        $this->assertEquals( 'dep_3', $scripts[0]->filename );
        $this->assertTrue( $scripts[0]->getMetaDataKey('isRoot'), 'Dep #3 through Dep #2 should be marked isRoot' );
        $this->assertEmpty( $scripts[0]->getMetaDataKey('packages') );

        // Ensure dep #1's dependency and dep #2's dependency is the same
        $this->assertEquals( $dep1scripts[0]->filename, $scripts[0]->filename );
    }

    /******************************************************************
     * parseFile > 3 Dependencies, #1, #2, and #3
     *      #1 with no dependency
     *      #3 with a dependency on #2
     *      #2 with no dependency
     * Fixture folder: 3_deps_1_feedback
     *****************************************************************/

    public function testParseFile_3Deps1Feedback()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        // Ensure root file was scanned properly
        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );

        // Ensure root file (main.js) has #1 and #2 scripts but no packages
        $this->assertFalse( $dependencyTree->getMetaDataKey('isRoot'), 'Root file should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->getMetaDataKey('scripts') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('stylesheets') );
        $this->assertEmpty( $dependencyTree->getMetaDataKey('packages') );

        // Ensure it has #1 and #2 and #3
        $scripts = $dependencyTree->getMetaDataKey('scripts');
        $this->assertCount(3, $scripts, 'Root file should have three dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[0] );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[1] );
        $this->assertInstanceOf( 'JsPackager\DependencyFileInterface', $scripts[2] );
        $this->assertEquals( 'dep_1', $scripts[0]->filename );
        $this->assertEquals( 'dep_3', $scripts[1]->filename );
        $this->assertEquals( 'dep_2', $scripts[2]->filename );

        // Shortcut for easier access and readability
        $dep1 = $scripts[0];
        $dep3 = $scripts[1];
        $dep2 = $scripts[2];

        // Ensure dependencies are not root packages themselves
        $this->assertFalse( $dep1->getMetaDataKey('isRoot'), 'Dep #1 should not be marked isRoot' );
        $this->assertFalse( $dep2->getMetaDataKey('isRoot'), 'Dep #2 should not be marked isRoot' );
        $this->assertFalse( $dep3->getMetaDataKey('isRoot'), 'Dep #3 should not be marked isRoot' );


        // Ensure dep #1 and #2 have no dependencies
        $this->assertCount(0, $dep1->getMetaDataKey('scripts'), 'Dep #1 should have no dependent script');
        $this->assertCount(0, $dep2->getMetaDataKey('scripts'), 'Dep #2 should have no dependent script');

        // Ensure dep #3 has #2 as a dependency
        $scripts = $dep3->getMetaDataKey('scripts');
        $this->assertCount(1, $scripts, 'Dep #3 should have one dependent script');
        $this->assertEquals( 'dep_2', $scripts[0]->filename );
        $this->assertFalse( $scripts[0]->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertEmpty( $scripts[0]->getMetaDataKey('packages'));

        // Ensure dep #3's dependency is dependency #2
        $this->assertEquals( $scripts[0]->filename, $dep2->filename );
    }

    /******************************************************************
     * parseFile > 3 Dependencies, #1, #2, and #3
     *      #1 with own dependency (#4)
     *      #2 & #3 both share a third dependency (#5)
     *      The shared third dependency (#5) has a dependency on #1's dependency (#4)
     * Fixture folder: 3_deps_all_on_one
     *****************************************************************/

    public function testParseFile_3DepsAllOnOne()
    {
        $basePath = self::fixturesBasePath . '3_deps_all_on_one';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        // Ensure root file was scanned properly
        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );

        $this->assertCount( 3, $dependencyTree->getMetaDataKey('scripts') );
        $this->assertCount( 0, $dependencyTree->getMetaDataKey('stylesheets') );
        $this->assertCount( 3, $dependencyTree->getMetaDataKey('scripts') );

        $this->assertFalse( $dependencyTree->getMetaDataKey('isRoot') );

        // Shortcut for easier access and readability
        $scripts = $dependencyTree->getMetaDataKey('scripts');
        $dep1 = $scripts[0];
        $dep1Scripts = $dep1->getMetaDataKey('scripts');
        $dep2 = $scripts[1];
        $dep2Scripts = $dep2->getMetaDataKey('scripts');
        $dep3 = $scripts[2];
        $dep3Scripts = $dep3->getMetaDataKey('scripts');

        // Ensure dependencies are not root packages themselves
        $this->assertFalse( $dep1->getMetaDataKey('isRoot'), 'Dep #1 should not be marked isRoot' );
        $this->assertFalse( $dep2->getMetaDataKey('isRoot'), 'Dep #2 should not be marked isRoot' );
        $this->assertFalse( $dep3->getMetaDataKey('isRoot'), 'Dep #3 should not be marked isRoot' );

        // #1 & #5 depends on #4
        $this->assertEquals( 'dep_4', $dep1Scripts[0]->filename );

        $dep4 = $dep1Scripts[0];
        $this->assertFalse( $dep4->getMetaDataKey('isRoot'), 'Dep #4 should not be marked isRoot' );

        // #2 & #3 depend on #5
        $this->assertEquals( 'dep_5', $dep2Scripts[0]->filename, "Dep #2 should depend on Dep #5" );
        $this->assertEquals( 'dep_5', $dep3Scripts[0]->filename, "Dep #3 should depend on Dep #5" );

        $dep5 = $dep2Scripts[0];
        $dep5Scripts = $dep5->getMetaDataKey('scripts');
        $this->assertFalse( $dep5->getMetaDataKey('isRoot'), 'Dep #5 should not be marked isRoot' );

        // #5 depends on #4
        $this->assertEquals( 'dep_4', $dep5Scripts[0]->filename );

        /**
         * TODO update these tests!
         * @var $orderMapEntry AnnotationOrderMapping
         */
        $annotations = $dependencyTree->getMetaDataKey('annotationOrderMap')->getAnnotationMappings();
        $orderMapEntry = $annotations[0];
        $this->assertInstanceOf('JsPackager\Annotations\AnnotationOrderMapping', $orderMapEntry, 'Returns array of AnnotationOrderMapping value objects');
        $this->assertEquals( 'require', $orderMapEntry->getAnnotationName(), "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $orderMapEntry->getAnnotationIndex(), "Should reflect appropriate order" );
        $orderMapEntry = $annotations[1];
        $this->assertEquals( 'require', $orderMapEntry->getAnnotationName(), "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $orderMapEntry->getAnnotationIndex(), "Should reflect appropriate order" );
        $orderMapEntry = $annotations[2];
        $this->assertEquals( 'require', $orderMapEntry->getAnnotationName(), "Should reflect appropriate bucket" );
        $this->assertEquals( 2, $orderMapEntry->getAnnotationIndex(), "Should reflect appropriate order" );

        $annotations = $dep1->getMetaDataKey('annotationOrderMap')->getAnnotationMappings();
        $orderMapEntry = $annotations[0];
        $this->assertEquals( 'require', $orderMapEntry->getAnnotationName(), "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $orderMapEntry->getAnnotationIndex(), "Should reflect appropriate order" );

        $annotations = $dep2->getMetaDataKey('annotationOrderMap')->getAnnotationMappings();
        $orderMapEntry = $annotations[0];
        $this->assertEquals( 'require', $orderMapEntry->getAnnotationName(), "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $orderMapEntry->getAnnotationIndex(), "Should reflect appropriate order" );

        $annotations = $dep3->getMetaDataKey('annotationOrderMap')->getAnnotationMappings();
        $orderMapEntry = $annotations[0];
        $this->assertEquals( 'require', $orderMapEntry->getAnnotationName(), "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $orderMapEntry->getAnnotationIndex(), "Should reflect appropriate order" );

        $this->assertEmpty( $dep4->getMetaDataKey('annotationOrderMap')->getAnnotationMappings(), "Dep #4 has no dependencies" );

        $annotations = $dep5->getMetaDataKey('annotationOrderMap')->getAnnotationMappings();
        $orderMapEntry = $annotations[0];
        $this->assertEquals( 'require', $orderMapEntry->getAnnotationName(), "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $orderMapEntry->getAnnotationIndex(), "Should reflect appropriate order" );

    }

    /******************************************************************
     * parseFile > 3 Dependencies, #1, #2, and #3
     *      #1 with own dependency (#4)
     *      #2 & #3 both share a third dependency (#5)
     *      The shared third dependency (#5) has a dependency on #1's dependency (#4)
     * Fixture folder: 3_deps_all_on_one_package
     *****************************************************************/

    public function testParseFile_3DepsAllOnOnePackage()
    {
        $basePath = self::fixturesBasePath . '3_deps_all_on_one_package';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        // Ensure root file was scanned properly
        $this->assertEquals( 'main', $dependencyTree->filename, "Root file should be named main" );
        $this->assertEquals( 'js', $dependencyTree->filetype, "Root file should be of js filetype" );
        $this->assertEquals( $basePath, $dependencyTree->path, "Root file should be in the base path" );

        $this->assertCount( 3, $dependencyTree->getMetaDataKey('scripts'), "main.js should contain 3 scripts" );
        $this->assertCount( 0, $dependencyTree->getMetaDataKey('packages'), "main.js should contain no package" );
        $this->assertCount( 0, $dependencyTree->getMetaDataKey('stylesheets'), "main.js should contain no stylesheets" );

        $this->assertFalse( $dependencyTree->getMetaDataKey('isRoot'), "main.js should not be marked isRoot" );

        // Shortcut for easier access and readability
        $depTreeScripts = $dependencyTree->getMetaDataKey('scripts');
        $dep1 = $depTreeScripts[0];
        $dep1Scripts = $dep1->getMetaDataKey('scripts');
        $dep2 = $depTreeScripts[1];
        $dep2Scripts = $dep2->getMetaDataKey('scripts');
        $dep3 = $depTreeScripts[2];
        $dep3Scripts = $dep3->getMetaDataKey('scripts');

        // Ensure dependencies are not root packages themselves
        $this->assertFalse( $dep1->getMetaDataKey('isRoot'), 'Dep #1 should not be marked isRoot' );
        $this->assertFalse( $dep2->getMetaDataKey('isRoot'), 'Dep #2 should not be marked isRoot' );
        $this->assertFalse( $dep3->getMetaDataKey('isRoot'), 'Dep #3 should not be marked isRoot' );

        // #1 & #5 depends on #4
        $this->assertEquals( 'dep_4', $dep1Scripts[0]->filename, "Dep #1 should depend on Dep #4" );

        $dep4 = $dep1Scripts[0];
        $this->assertTrue( $dep4->getMetaDataKey('isRoot'), 'Dep #4 should be marked isRoot' );

        // #2 & #3 depend on #5
        $this->assertEquals( 'dep_5', $dep2Scripts[0]->filename, "Dep #2 should depend on Dep #5" );
        $this->assertEquals( 'dep_5', $dep3Scripts[0]->filename, "Dep #3 should depend on Dep #5" );

        $dep5 = $dep2Scripts[0];
        $dep5Scripts = $dep5->getMetaDataKey('scripts');
        $dep5packages = $dep5->getMetaDataKey('packages');
        $this->assertFalse( $dep5->getMetaDataKey('isRoot'), 'File should not be marked isRoot' );
        $this->assertEquals( $basePath . '/dep_4.js', $dep5packages[0], 'Dep #5 should contain package entry for Dep #4');

        // #5 depends on #4
        $this->assertEquals( 'dep_4', $dep5Scripts[0]->filename, "Dep #5 should depend on Dep #4" );
    }



    /******************************************************************
     * parseFile > annotation_nocompile
     * Fixture folder: 3annotation_nocompile
     *****************************************************************/

    public function testParseFile_annotation_nocompile()
    {
        $basePath = self::fixturesBasePath . 'annotation_nocompile';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());
        $dependencyTree = $treeParser->parseFile( $filePath );

        // Ensure root file was scanned properly
        $this->assertEquals( 'main', $dependencyTree->filename, "Root file should be named main" );
        $this->assertEquals( 'js', $dependencyTree->filetype, "Root file should be of js filetype" );
        $this->assertEquals( $basePath, $dependencyTree->path, "Root file should be in the base path" );

        $this->assertCount( 4, $dependencyTree->getMetaDataKey('scripts'), "main.js should contain 4 scripts" );
        $this->assertCount( 2, $dependencyTree->getMetaDataKey('packages'), "main.js should contain 2 packages" );
        $this->assertCount( 0, $dependencyTree->getMetaDataKey('stylesheets'), "main.js should contain no stylesheets" );

        $this->assertFalse( $dependencyTree->getMetaDataKey('isMarkedNoCompile'), "main.js should not be marked no compile" );

        // Shortcut for easier access and readability
        $depTreeScripts = $dependencyTree->getMetaDataKey('scripts');
        $nocompilePackage = $depTreeScripts[0];
        $nocompileScript = $depTreeScripts[1];
        $normalPackage = $depTreeScripts[2];
        $normalScript = $depTreeScripts[3];

        // Ensure dependencies `root` annotations were handled
        $this->assertTrue( $nocompilePackage->getMetaDataKey('isRoot'), 'Dep #1 should be marked isRoot' );
        $this->assertFalse( $nocompileScript->getMetaDataKey('isRoot'), 'Dep #2 should not be marked isRoot' );
        $this->assertTrue( $normalPackage->getMetaDataKey('isRoot'), 'Dep #3 should be marked isRoot' );
        $this->assertFalse( $normalScript->getMetaDataKey('isRoot'), 'Dep #4 should not be marked isRoot' );

        // Ensure dependencies `nocompile` annotations were handled
        $this->assertTrue( $nocompilePackage->getMetaDataKey('isMarkedNoCompile'), 'Dep #1 should be marked no compile' );
        $this->assertTrue( $nocompileScript->getMetaDataKey('isMarkedNoCompile'), 'Dep #2 should be be marked no compile' );
        $this->assertFalse( $normalPackage->getMetaDataKey('isMarkedNoCompile'), 'Dep #3 should not be marked no compile' );
        $this->assertFalse( $normalScript->getMetaDataKey('isMarkedNoCompile'), 'Dep #4 should not be marked no compile' );

        $packages = $dependencyTree->getMetaDataKey('packages');
        // Ensure the packages were detected properly
        $this->assertEquals(
            "tests/JsPackager/fixtures/annotation_nocompile/some/nocompile/package.js",
            $packages[0]
        );

        $this->assertEquals(
            "tests/JsPackager/fixtures/annotation_nocompile/some/normal/package.js",
            $packages[1]
        );
    }

    /******************************************************************
     * parseFile > Recursion (2 Dependencies, #1, #2)
     *      #1 with own dependency (#2)
     *      #2 with own dependency (#1)
     * Fixture folder: recursion
     *****************************************************************/

    public function testParseFileThrowsOnRecursion()
    {
        $basePath = self::fixturesBasePath . 'recursion';
        $filePath = $basePath . '/main.js';

        $parser = $this->getCommonAnnotationParser();
        $treeParser = new DependencyTreeParser($parser, '@remote', 'shared', null, new NullLogger(), false, new FileHandler());

        try {
            $dependencyTree = $treeParser->parseFile( $filePath );
            $this->fail('Set should throw a recursion exception');
        } catch (RecursionException $e) {
            $this->assertEquals(
                RecursionException::ERROR_CODE,
                $e->getCode(),
                'Exception should contain proper error code'
            );
        }
    }

}
