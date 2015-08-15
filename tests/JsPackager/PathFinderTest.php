<?php

namespace JsPackagerTest;

use JsPackager\File;
use JsPackager\Resolver\DependencyTreeParser;
use JsPackager\Helpers\FileHandler;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Exception\MissingFile as MissingFileException;

use JsPackager\Helpers\Reflection as ReflectionHelper;
use JsPackager\Helpers\PathFinder;

/**
 * @group      JsPackager
 */
class PathFinderTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';


    /******************************************************************
     *
     *****************************************************************/


    public function testGetRelativePathBetweenTwoFiles()
    {
        $pathFinder = new PathFinder();

        $pathA = 'path/js/framework/layouts/stuff.js';
        $pathB = 'path/js/lib/core/core.js';

        $relativePath = $pathFinder->getRelativePathFromAbsoluteFiles( $pathA, $pathB );

        $expectedPath = '../../lib/core/core.js';

        $this->assertEquals($expectedPath, $relativePath);

    }

    public function testGetRelativePathBetweenTwoFilesDiffPath()
    {
        $pathFinder = new PathFinder();

        $pathA = '/Users/jhegman/architecture/NeoProvisioning/Vagrant/provisions/sharding-release-single/www/app/public/shared/js/layouts/';
        $pathB = '/Users/jhegman/architecture/NeoProvisioning/Vagrant/provisions/sharding-release-single/www/app/public/shared/js/framework/core.js';

        $relativePath = $pathFinder->getRelativePathFromAbsoluteFiles2( $pathA, $pathB );

        $whatWeGettinNow = "/Users/jhegman/architecture/NeoProvisioning/Vagrant/provisions/sharding-release-single/www/app/public/shared/js/framewor";

        $whatWeCouldGet = "/Users/jhegman/architecture/NeoProvisioning/Vagrant/provisions/sharding-release-single/www/app/public/shared/js/framework/../layouts";

        $andWhatWeCouldGet = "../layouts";

        $expectedPath = '../framework/core.js';
        $expectedOtherPath = '../../framework/layouts/stuff.js';

        $this->assertEquals($expectedPath, $relativePath);

    }


    public function testGetRelativePathBetweenTwoFilesAlt()
    {
        $pathFinder = new PathFinder();

        $pathA = 'path/js/framework/layouts/stuff.js';
        $pathB = 'path/js/lib/core/core.js';

        $relativePath = $pathFinder->getRelativePathFromAbsoluteFiles2( $pathA, $pathB );

        $expectedPath = '../../lib/core/core.js';

        $this->assertEquals($expectedPath, $relativePath);
    }

    public function testGetRelativePathBetweenTwoFilesDiffPathAlt()
    {
        $pathFinder = new PathFinder();

        $pathA = '/Users/jhegman/architecture/NeoProvisioning/Vagrant/provisions/sharding-release-single/www/app/public/shared/js/layouts/';
        $pathB = '/Users/jhegman/architecture/NeoProvisioning/Vagrant/provisions/sharding-release-single/www/app/public/shared/js/framework/core.js';

        $relativePath = $pathFinder->getRelativePathFromAbsoluteFiles2( $pathA, $pathB );

        $expectedPath = '../framework/core.js';

        $this->assertEquals($expectedPath, $relativePath);
    }

    public function testGetRelativePathBetweenTwoFilesAtSamePath()
    {
        $pathFinder = new PathFinder();

        $pathA = '/Users/jhegman/architecture/NeoProvisioning/Vagrant/provisions/sharding-release-single/www/app/public/shared/js/framework/fab.js';
        $pathB = '/Users/jhegman/architecture/NeoProvisioning/Vagrant/provisions/sharding-release-single/www/app/public/shared/js/framework/core.js';

        $relativePath = $pathFinder->getRelativePathFromAbsoluteFiles2( $pathA, $pathB );

        $expectedPath = './core.js';

        $this->assertEquals($expectedPath, $relativePath);
    }

    public function testGetRelativePathBetweenTwoFilesAtDeeperPath()
    {
        $pathFinder = new PathFinder();

        $pathA = '/Users/jhegman/architecture/NeoProvisioning/Vagrant/provisions/sharding-release-single/www/app/public/shared/js/framework/fab.js';
        $pathB = '/Users/jhegman/architecture/NeoProvisioning/Vagrant/provisions/sharding-release-single/www/app/public/shared/js/framework/lib/core.js';

        $relativePath = $pathFinder->getRelativePathFromAbsoluteFiles2( $pathA, $pathB );

        $expectedPath = './lib/core.js';

        $this->assertEquals($expectedPath, $relativePath);
    }


    /******************************************************************
     * normalizeRelativePath
     *****************************************************************/


    public function testNormalizeRelativePathDoesNotHarmBasicPaths()
    {
        $path = '/chocolate/and/strawberries/is/yummy';
        $expectedPath = '/chocolate/and/strawberries/is/yummy';

        $treeParser = new PathFinder();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathDoesNotTrailingSlash()
    {
        $path = '/chocolate/and/strawberries/is/yummy/';
        $expectedPath = '/chocolate/and/strawberries/is/yummy/';

        $treeParser = new PathFinder();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathHandlesSingleUpDirectory()
    {
        $path = '/chocolate/and/../strawberries';
        $expectedPath = '/chocolate/strawberries';

        $treeParser = new PathFinder();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathHandlesManyUpDirectories()
    {
        $path = '/somewhere/in/a/place/../../heaven';
        $expectedPath = '/somewhere/in/heaven';

        $treeParser = new PathFinder();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathEvenSeparated()
    {
        $path = '/somewhere/somehow/../in/a/place/../../heaven';
        $expectedPath = '/somewhere/in/heaven';

        $treeParser = new PathFinder();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathDoesNotExceedRoot()
    {
        $path = '/somewhere/../../home/';
        $expectedPath = '/../home/';

        $treeParser = new PathFinder();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathDoesNotExceedRootEvenSeparated()
    {
        $path = '/somewhere/../../home/ward/../../bound/../../';
        $expectedPath = '/../../';

        $treeParser = new PathFinder();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathHandlesDashes()
    {
        $path = '/some-where/../../home-ward/ward/../../bound/../../';
        $expectedPath = '/../../';

        $treeParser = new PathFinder();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }


}
