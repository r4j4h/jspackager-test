<?php

namespace JsPackagerTest;

use JsPackager\File;
use JsPackager\DependencyTreeParser;
use JsPackager\FileHandler;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Exception\MissingFile as MissingFileException;

use JsPackager\Helpers\Reflection as ReflectionHelper;
use JsPackager\ManifestResolver;
use JsPackager\PathFinder;

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


    public function testHandlesPackages()
    {

        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_package';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = '.'; //self::fixturesBasePath;
        $manifestResolver->sharedFolderPath = '.';

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            './tests/JsPackager/fixtures/3_deps_1_feedback_shared_package/dep_3.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            './tests/JsPackager/fixtures/3_deps_1_feedback_shared_package/main.compiled.js',
            $paths[1]
        );

    }

    public function testUsesBaseFolderPath()
    {

        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_package';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = 'public'; //self::fixturesBasePath;
        $manifestResolver->sharedFolderPath = 'public/remote';

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            'public/tests/JsPackager/fixtures/3_deps_1_feedback_shared_package/dep_3.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            'public/tests/JsPackager/fixtures/3_deps_1_feedback_shared_package/main.compiled.js',
            $paths[1]
        );

    }


    public function testExpandsRemoteSymbols()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = 'basey'; //self::fixturesBasePath;
        $manifestResolver->sharedFolderPath = 'remmy';

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals( 'remmy/remotepackage/script.compiled.js', $paths[0] );
        $this->assertEquals( 'basey/tests/JsPackager/fixtures/remote_annotation/main.compiled.js', $paths[1] );

    }

    public function testUsesSharedFolderPath()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = $basePath;
        $manifestResolver->sharedFolderPath = $remotePath;

        $paths = $manifestResolver->resolveFile( $filePath );

//        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[1]
        );

    }

    public function testHandlesRelativeFilePathWithRelativeBaseAndRemotePaths()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = $basePath;
        $manifestResolver->sharedFolderPath = $remotePath;


        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[1]
        );

    }

    public function testHandlesRelativeFilePathWithAbsoluteBaseAndRemotePaths()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = getcwd() . '/' . $basePath;
        $manifestResolver->sharedFolderPath = getcwd() . '/' . $remotePath;



        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-remote/remotepackage/script.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/tests/JsPackager/fixtures/remote_annotation/main.compiled.js',
            $paths[1]
        );

    }

    public function testHandlesAbsoluteFilePathWithLocalBaseAndRemotePaths()
    {

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = $basePath;
        $manifestResolver->sharedFolderPath = $remotePath;

        $paths = $manifestResolver->resolveFile( getcwd() . '/' . $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            $remotePath . '/remotepackage/script.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            $basePath . '/main.compiled.js',
            $paths[1]
        );

    }

    public function testHandlesAbsoluteFilePathWithAbsoluteBaseAndRemotePaths()
    {

        $basePath = getcwd() . '/' . self::fixturesBasePath . 'remote_annotation';
        $remotePath = $basePath . '-remote';
        $filePath = $basePath . '/main.js';

        $manifestResolver = new ManifestResolver();
        $manifestResolver->baseFolderPath = $basePath;
        $manifestResolver->sharedFolderPath = $remotePath;

        $paths = $manifestResolver->resolveFile( $filePath );

        $this->assertEquals( 2, count( $paths ) );
        $this->assertEquals(
            $remotePath . '/remotepackage/script.compiled.js',
            $paths[0]
        );
        $this->assertEquals(
            $basePath . '/main.compiled.js',
            $paths[1]
        );

    }
}
