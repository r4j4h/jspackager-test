<?php

namespace JsPackagerTest;

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
class AnnotationBasedFileResolverTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';



    private function getDependencySetsFixtureA()
    {
        $dependencySets = array(
            array(
                'quux.js', // these need to be new Files?
                'quux-styles.css',
            ),
            array(
                'baz.js',
                'bar.js',
            ),
            array(
                'foo.js',
                'main.js',
            ),
        );
        return $dependencySets;
    }

    /////

    public function testResolveLoadsEachFileAndParsesByLineForAnnotations()
    {
        $root = vfsStream::copyFromFileSystem('tests/jsPackager/fixtures/', vfsStream::setup());

        $resolver = new AnnotationBasedFileResolver($root->path(), '@cdn', null, false, new NullLogger(), new FileHandler());

        $file = new PathBasedFile($root->getChild('2_indep_deps/main.js')->url(), array());
        $context = new AnnotationBasedResolverContext();
        $context->remoteSymbol = '@cdn';

        $remoteFolder = vfsStream::newDirectory('_remote');
        $testFolder = vfsStream::newDirectory('_test');

        $mutingMissingFileExceptions = false;
        $logger = new NullLogger();

        $rootHandler = new RootAnnotationHandler();
        $noCompileHandler = new IsMarkedNoCompiledHandler();
        $requireRemoteStyleHandler = new RequireRemoteStyleAnnotationHandler(
            $remoteFolder, $context->remoteSymbol, $mutingMissingFileExceptions, $logger
        );
        $requireRemoteHandler = new RequireRemote(
            $remoteFolder, $context->remoteSymbol, $mutingMissingFileExceptions, $logger
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

        $parser = new AnnotationParser($annotationResponseHandlerMapping, $testFolder, new NullLogger(), new FileHandler());

        $fileFactory = new DependencyTreeParser($parser, '@cdn', $root->addChild($remoteFolder), $root->addChild($testFolder), new NullLogger(), false, new FileHandler());
        $files = $fileFactory->parseFile($root->getChild('2_indep_deps/main.js')->url());
        $resolved = $resolver->resolveDependenciesForFile($file, $context);


    }

    public function testResolveReturnsGivenFiles()
    {

    }

    public function testResolvePrependsAnyFileReferencedLocallyByAtRequire()
    {
        // move to handler test?
    }

    public function testResolvePrependsRemoteFilesReferencedByAtRemoteRequire()
    {
        // move to handler test?
    }

    public function testResolvePrependsTestedFiles()
    {
        // move to handler test?
    }

    public function testResolvePrependsFilesWithAtRootAsNewDependentDependencySets()
    {
        // move to handler test?
    }

    public function testResolveAddsNoCompileMetadataForFilesWithAtNoCompileAnnotation()
    {
        // move to handler test?
    }

}
