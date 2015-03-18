<?php

namespace JsPackagerTest;

use JsPackager\File;
use JsPackager\DependencyTreeParser;
use JsPackager\FileHandler;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Exception\MissingFile as MissingFileException;

use JsPackager\Helpers\Reflection as ReflectionHelper;
use JsPackager\PathFinder;

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



}
