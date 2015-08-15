<?php

namespace JsPackagerTest;

use JsPackager\ManifestResolver;
use Psr\Log\NullLogger;

/**
 * @group      JsPackager
 */
class ManifestResolverTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';

    /******************************************************************
     *
     *****************************************************************/


    public function testParsesCompiledFilesFromPackages()
    {

        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_package';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver(
            '', //self::fixturesBasePath,
            '',
            '@remote',
            new NullLogger()
        );

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            'tests/JsPackager/fixtures/3_deps_1_feedback_shared_package/dep_3.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/3_deps_1_feedback_shared_package/main.compiled.js',
            $paths[1]
        );

    }

    public function testUsesBaseFolderPath()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver($basePath, $remotePath, '@remote', new NullLogger());

        $paths = $manifestResolver->resolveFile( $filePath );

        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

        $manifestResolver->baseFolderPath = 'dorf';

        $paths = $manifestResolver->resolveFile( $filePath );

        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }



    public function testHandlesEmptyBaseFolderPath()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver('', $remotePath, '@remote', new NullLogger());

        $paths = $manifestResolver->resolveFile( $filePath );

        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }

    public function testHandlesDotBaseFolderPath()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver('.', $remotePath, '@remote', new NullLogger());

        $paths = $manifestResolver->resolveFile( $filePath );

        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            './tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            './tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            './tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            './tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            './tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }

    public function testHandlesDotSlashBaseFolderPath()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver('./', $remotePath, '@remote', new NullLogger());

        $paths = $manifestResolver->resolveFile( $filePath );

        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            './tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            './tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            './tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            './tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            './tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }

    public function testHandlesRootBaseFolderPath()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver('/', $remotePath, '@remote', new NullLogger());

        $paths = $manifestResolver->resolveFile( $filePath );

        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            '/tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            '/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            '/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            '/tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            '/tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }

    public function testDoesNotOutputRemoteSymbols()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver('basey', 'remmy', '@remote', new NullLogger());

        $paths = $manifestResolver->resolveFile( $filePath );


        $this->assertEquals( 10, count( $paths ) );
        $i = 0;
        $this->assertEquals( 'remmy/remotepackage/package_subfolder/local_on_remote.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotepackage/package_subfolder/remote_on_remote.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotescript/script_subfolder/local_on_remote.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotescript/script_subfolder/remote_on_remote.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotepackage/script.compiled.js', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/main.compiled.js', $paths[$i++] );

        $i = 0;
        $this->assertStringStartsNotWith($manifestResolver->remoteSymbol, $paths[$i++]);
        $this->assertStringStartsNotWith($manifestResolver->remoteSymbol, $paths[$i++]);
        $this->assertStringStartsNotWith($manifestResolver->remoteSymbol, $paths[$i++]);
        $this->assertStringStartsNotWith($manifestResolver->remoteSymbol, $paths[$i++]);
        $this->assertStringStartsNotWith($manifestResolver->remoteSymbol, $paths[$i++]);
        $this->assertStringStartsNotWith($manifestResolver->remoteSymbol, $paths[$i++]);
        $this->assertStringStartsNotWith($manifestResolver->remoteSymbol, $paths[$i++]);
        $this->assertStringStartsNotWith($manifestResolver->remoteSymbol, $paths[$i++]);

    }

    public function testExpandsRemoteSymbols()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver('basey', 'remmy', '@remote', new NullLogger());
        $manifestResolver->baseFolderPath = 'basey'; //self::fixturesBasePath;
        $manifestResolver->remoteFolderPath = 'remmy';

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 10, count( $paths ) );

        $i = 0;
        $this->assertEquals( 'remmy/remotepackage/package_subfolder/local_on_remote.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotepackage/package_subfolder/remote_on_remote.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotescript/script_subfolder/local_on_remote.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotescript/script_subfolder/remote_on_remote.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css', $paths[$i++] );
        $this->assertEquals( 'remmy/remotepackage/script.compiled.js', $paths[$i++] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/main.compiled.js', $paths[$i++] );

    }

    public function testUsesSharedFolderPath()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver($basePath, $remotePath, '@remote', new NullLogger());

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 10, count( $paths ) );
        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

        $manifestResolver->remoteFolderPath = 'dorf';

        $paths = $manifestResolver->resolveFile( $filePath );


        $this->assertEquals( 10, count( $paths ) );
        $i = 0;
        $this->assertEquals(
            'dorf/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'dorf/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }

    public function testReturnsRelativeFilePathsWhenGivenRelativeFilesWithRelativeRemotePaths()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver($basePath, $remotePath, '@remote', new NullLogger());

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 10, count( $paths ) );
        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );
// good just flesh out
    }

    public function testReturnsRelativeFilePathsWhenGivenAbsoluteFilesWithAbsoluteRemotePaths()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver(
            getcwd() . '/' . $basePath,
            getcwd() . '/' . $remotePath,
            '@remote',
            new NullLogger()
        );

        $paths = $manifestResolver->resolveFile( getcwd() . '/' . $filePath );

        $this->assertEquals( 10, count( $paths ) );
        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }

    public function testReturnsRelativeFilePathsWhenGivenAbsoluteFilesWithRelativeRemotePath()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver($basePath, $remotePath, '@remote', new NullLogger());

        $paths = $manifestResolver->resolveFile( getcwd() . '/' . $filePath );

        $this->assertEquals( 10, count( $paths ) );
        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }

    public function testReturnsRelativeLocalFilePathsAndRelativeRemoteFilePathsWhenGivenRelativeFilesWithAbsoluteRemotePathAndRelativeBasePath()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver($basePath, getcwd() . '/' . $remotePath, '@remote', new NullLogger());

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 10, count( $paths ) );
        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );
    }

    public function testReturnsRelativeAndUrlFilePathsWhenGivenRelativeFilesWithUrlRemotePathsAndRelativeBasePath()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver(
            $basePath, 'http://bebopjims.com/cool-90s-stuff', '@remote', new NullLogger()
        );

        $paths = $manifestResolver->resolveFile( $filePath );

//        $this->markTestIncomplete(
//            'URLs work, but in this case remotepackage/script.compiled.js\' manifest is never read!'
//        );

        $this->assertEquals( 10, count( $paths ) );
        $i = 0;
        $this->assertEquals(
            'http://bebopjims.com/cool-90s-stuff/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'http://bebopjims.com/cool-90s-stuff/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'http://bebopjims.com/cool-90s-stuff/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'http://bebopjims.com/cool-90s-stuff/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'http://bebopjims.com/cool-90s-stuff/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );
    }

    public function testReturnsRelativeFilePathsWhenGivenRelativeFilesWithAbsoluteRemotePathsAndAbsoluteBasePath()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver(
            getcwd() . '/' . $basePath,
            getcwd() . '/' . $remotePath,
            '@remote',
            new NullLogger()
        );

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 10, count( $paths ) );
        $i = 0;
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }

    // Missing File behavior

    public function testMissingFileBehaviorSupportsFailingOnMissingFile()
    {

    }

    public function testMissingFileBehaviorSupportsCompilingMissingFilesOnTheFly()
    {

    }

    public function testMissingFileBehaviorSupportsFallingBackToUncompiledFiles()
    {

    }

    // Filename resolution

    public function testResolvesUncompiledFileByUncompiledFilename()
    {

    }

    public function testResolvesCompiledFileByUncompiledFilename()
    {

    }

    public function testResolvesManifestFileByUncompiledFilename()
    {

    }



    public function testDoesNotCrashIfFileIsMissing()
    {

    }


    // Remote Annotation Expansion


    public function testResolvesCompiledFilesExpandingRemoteAnnotationsToRelativePath()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $mainJsPath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver(
            '',
            getcwd() . '/' . 'hella_rela_path',
            '@remote',
            new NullLogger()
        );

        $paths = $manifestResolver->resolveFile( $mainJsPath );

        $i = 0;
        $this->assertEquals(
            'hella_rela_path/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'hella_rela_path/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'hella_rela_path/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'hella_rela_path/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'hella_rela_path/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }

    public function testResolvesCompiledFilesExpandingRemoteAnnotationsToAbsolutePath()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $mainJsPath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver('', '/abso_path', '@remote', new NullLogger());

        $paths = $manifestResolver->resolveFile( $mainJsPath );

        $i = 0;
        $this->assertEquals(
            '/abso_path/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            '/abso_path/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            '/abso_path/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            '/abso_path/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            '/abso_path/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }


    public function testResolvesCompiledFilesExpandingRemoteAnnotationsToHttpPath()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $mainJsPath = $basePath . '/main.js';

        $remotePath = 'http://theinternet.con/abso_path';

        $manifestResolver = new ManifestResolver( $basePath, $remotePath, '@remote', new NullLogger() );

        $paths = $manifestResolver->resolveFile( $mainJsPath );

        $this->assertEquals( 10, count( $paths ) );
        $i = 0;
        $this->assertEquals(
            'http://theinternet.con/abso_path/remotepackage/package_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'http://theinternet.con/abso_path/remotepackage/package_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'http://theinternet.con/abso_path/remotescript/script_subfolder/local_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'http://theinternet.con/abso_path/remotescript/script_subfolder/remote_on_remote.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css',
            $paths[$i++]
        );
        $this->assertEquals(
            'http://theinternet.con/abso_path/remotepackage/script.compiled.js',
            $paths[$i++]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[$i++]
        );

    }

}
