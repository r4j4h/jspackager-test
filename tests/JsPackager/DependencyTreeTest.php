<?php

namespace JsPackager;

use JsPackager\Compiler\DependencySet;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Parsing as ParsingException;

/**
 * @group      JsPackager
 */
class DependencyTreeTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';


    /******************************************************************
     * getTree
     *****************************************************************/


    /**
     * getTree > Returns File Hierarchy
     * Fixture folder: 2_indep_deps
     */
    public function testGetTreeReturnsFileHierarchy()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath );

        $fileHierarchy = $dependencyTree->getTree();

        $this->assertEquals( 'main', $fileHierarchy->filename );
        $this->assertEquals( 'js', $fileHierarchy->filetype );
        $this->assertEquals( $basePath, $fileHierarchy->path );
        $this->assertFalse( $fileHierarchy->isRoot, 'File should not be marked isRoot' );
        $this->assertNotEmpty( $fileHierarchy->scripts );
        $this->assertEmpty( $fileHierarchy->stylesheets );
        $this->assertEmpty( $fileHierarchy->packages );

        $this->assertCount(2, $fileHierarchy->scripts, 'Should have two dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\File', $fileHierarchy->scripts[0] );
        $this->assertInstanceOf( 'JsPackager\File', $fileHierarchy->scripts[1] );

        $this->assertEquals( 'comp_a', $fileHierarchy->scripts[0]->filename );
        $this->assertEquals( 'comp_b', $fileHierarchy->scripts[1]->filename );
        $this->assertFalse( $fileHierarchy->scripts[0]->isRoot, 'File should not be marked isRoot' );
        $this->assertFalse( $fileHierarchy->scripts[1]->isRoot, 'File should not be marked isRoot' );
    }


    /******************************************************************
     * getFlatten
     *****************************************************************/


    public function testGetFlattenReturnsArray()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree( false );

        // Verify response type
        $this->assertInternalType('array', $fileHierarchy);
    }

    /**
     * getFlatten > 2 Dependencies, #1 and #2
     * Fixture folder: 2_indep_deps
     */
    public function testGetFlattenFlattens2IndepDepsInProperFileOrder()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree( false );

        // Should be three files total
        $this->assertCount( 3, $fileHierarchy );

        $this->assertEquals( $basePath . '/ComponentA/comp_a.js', $fileHierarchy[ 0 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/ComponentB/comp_b.js', $fileHierarchy[ 1 ], "getFlatten maintains proper file order" );

        $fileHierarchyCount = count( $fileHierarchy );
        $this->assertEquals( $filePath, $fileHierarchy[ $fileHierarchyCount - 1 ], "Given file is last" );
    }


    /**
     * getFlatten > Maintain proper file order
     * Fixture folder: 2_indep_deps_individ_deps
     */
    public function testGetFlattenFlattens2IndepDepsIndividDepsInProperFileOrder()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps_individ_deps';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree( false );

        // Should be five files total
        $this->assertCount( 5, $fileHierarchy );

        $this->assertEquals( $basePath . '/dep_3.js', $fileHierarchy[ 0 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_1.js', $fileHierarchy[ 1 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_4.js', $fileHierarchy[ 2 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_2.js', $fileHierarchy[ 3 ], "getFlatten maintains proper file order" );

        $fileHierarchyCount = count( $fileHierarchy );
        $this->assertEquals( $filePath, $fileHierarchy[ $fileHierarchyCount - 1 ], "Given file is last" );
    }

    /**
     * getFlatten > Maintain proper file order
     * Fixture folder: 22_indep_deps_shared_deps
     */
    public function testGetFlattenFlattens2IndepDepsSharedDepsInProperFileOrder()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps_shared_deps';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree( false );

        // Should be four files total
        $this->assertCount( 4, $fileHierarchy );

        $this->assertEquals( $basePath . '/dep_3.js', $fileHierarchy[ 0 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_1.js', $fileHierarchy[ 1 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_2.js', $fileHierarchy[ 2 ], "getFlatten maintains proper file order" );

        $fileHierarchyCount = count( $fileHierarchy );
        $this->assertEquals( $filePath, $fileHierarchy[ $fileHierarchyCount - 1 ], "Given file is last" );
    }

    /**
     * getFlatten > Remove duplicates
     * Fixture folder: 2_indep_deps_1_root
     */
    public function testGetFlattenRemovesSubsequentDuplicatePaths()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps_1_root';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree( false );

        // Verify response type
        $this->assertInternalType('array', $fileHierarchy);
        $this->assertCount( 3, $fileHierarchy );

        $this->assertEquals( $basePath . '/ComponentA/comp_a.js', $fileHierarchy[ 0 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/ComponentB/comp_b.js', $fileHierarchy[ 1 ], "getFlatten maintains proper file order" );

        // There would be another comp_b, but the next assertion verifies that it is main instead
        $this->assertEquals( $filePath, $fileHierarchy[ 2 ], "Given file is last" );
    }

    /**
     * getFlatten > Root Packages
     * Fixture folder: 2_deps_1_package_2_deep
     */
    public function testGetFlattenIgnoringRootPackagesWithOnePackage()
    {
        $basePath = self::fixturesBasePath . '2_deps_1_package_2_deep';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree( false );

        // Verify response type
        $this->assertInternalType('array', $fileHierarchy);
        $this->assertCount( 6, $fileHierarchy );

        $this->assertEquals( $basePath . '/package/dep_4.js', $fileHierarchy[ 0 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/package/dep_5.js', $fileHierarchy[ 1 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/package/dep_3.js', $fileHierarchy[ 2 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_1.js', $fileHierarchy[ 3 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_2.js', $fileHierarchy[ 4 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/main.js', $fileHierarchy[ 5 ], "getFlatten maintains proper file order" );
    }

    /**
     * getFlatten > Root Packages
     * Fixture folder: 2_deps_1_package_2_deep
     */
    public function testGetFlattenRespectRootPackagesWithOnePackage()
    {
        $basePath = self::fixturesBasePath . '2_deps_1_package_2_deep';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree( true );

        // Verify response type
        $this->assertInternalType('array', $fileHierarchy);
        $this->assertCount( 4, $fileHierarchy );

        $this->assertEquals( $basePath . '/package/dep_3.js', $fileHierarchy[ 0 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_1.js', $fileHierarchy[ 1 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_2.js', $fileHierarchy[ 2 ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/main.js', $fileHierarchy[ 3 ], "getFlatten maintains proper file order" );
    }

    /**
     * getFlatten > Root Packages
     * Fixture folder: 2_deps_2_package_2_deep
     */
    public function testGetFlattenIgnoringRootPackagesWithTwoPackages()
    {
        $basePath = self::fixturesBasePath . '2_deps_2_package_2_deep';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree( false );

        // Verify response type
        $this->assertInternalType('array', $fileHierarchy);
        $this->assertCount( 8, $fileHierarchy );

        $i = 0;
        $this->assertEquals( $basePath . '/package/subpackage/dep_4_style.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/package/dep_3_style.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/package/subpackage/dep_5.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/package/subpackage/dep_4.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/package/dep_3.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_1.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_2.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/main.js', $fileHierarchy[ $i ], "getFlatten maintains proper file order" );

        // There would be another comp_b, but the next assertion verifies that it is main instead
        $this->assertEquals( $filePath, $fileHierarchy[ $i ], "Given file is last" );
    }

    /**
     * getFlatten > Root Packages
     * Fixture folder: 2_deps_2_package_2_deep
     */
    public function testGetFlattenRespectRootPackagesWithTwoPackages()
    {
        $basePath = self::fixturesBasePath . '2_deps_2_package_2_deep';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree( true );

        // Verify response type
        $this->assertInternalType('array', $fileHierarchy);
        $this->assertCount( 6, $fileHierarchy );

        $i = 0;
        $this->assertEquals( $basePath . '/package/dep_3_style.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/package/subpackage/dep_4.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/package/dep_3.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_1.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_2.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/main.js', $fileHierarchy[ $i ], "getFlatten maintains proper file order" );
        $this->assertEquals( $filePath, $fileHierarchy[ $i ], "Given file is last" );
    }

    public function testGetFlattenFlattens3Deps1FeedbackProperly()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree();

        $this->assertCount( 4, $fileHierarchy );

        $i = 0;
        $this->assertEquals( $basePath . '/dep_1.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_2.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_3.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $filePath, $fileHierarchy[ $i ], "Given file is last" );
    }

    public function testGetFlattenFlattensRemoteAnnotationProperly()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = self::fixturesBasePath . 'remote_annotation-remote';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath, null, false, null, $remotePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree();

        $this->assertCount( 19, $fileHierarchy );

        $i = 0;
        $this->assertEquals( $basePath . '/stylesheet_before.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_before.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/local_on_remote.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/remote_on_remote.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/local_on_remote.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/remote_on_remote.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_after.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/stylesheet_after.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_file_before.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_before.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/local_on_remote.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/remote_on_remote.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/local_on_remote.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/remote_on_remote.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/script.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_after.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_file_after.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $filePath, $fileHierarchy[ $i ], "Given file is last" );
    }


    public function testGetFlattenIntoAssocArrayFlattensRemoteAnnotationProperly()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = self::fixturesBasePath . 'remote_annotation-remote';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath, null, false, null, $remotePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTreeIntoAssocArrays( false );

        $this->assertCount( 11, $fileHierarchy['scripts'] );

        $i = 0;
        $this->assertEquals( $basePath . '/local_file_before.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_before.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/local_on_remote.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/remote_on_remote.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/local_on_remote.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/remote_on_remote.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/script.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_after.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_file_after.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $filePath, $fileHierarchy['scripts'][ $i ], "Given file is last" );

        $this->assertCount( 8, $fileHierarchy['stylesheets'] );

        $i = 0;
        $this->assertEquals( $basePath . '/stylesheet_before.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_before.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/local_on_remote.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/remote_on_remote.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/local_on_remote.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/remote_on_remote.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_after.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/stylesheet_after.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
    }

    public function testGetFlattenIntoAssocArrayFlattensRemoteAnnotationRespectingRootProperly()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = self::fixturesBasePath . 'remote_annotation-remote';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath, null, false, null, $remotePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTreeIntoAssocArrays( true );

        $this->assertCount( 9, $fileHierarchy['scripts'] );

        $i = 0;
        $this->assertEquals( $basePath . '/local_file_before.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_before.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/local_on_remote.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/remote_on_remote.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
//        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/script.compiled.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order and points to compiled file" );
        $i++;

        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_after.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_file_after.js', $fileHierarchy['scripts'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $filePath, $fileHierarchy['scripts'][ $i ], "Given file is last" );

        $this->assertCount( 8, $fileHierarchy['stylesheets'] );

        $i = 0;
        $this->assertEquals( $basePath . '/stylesheet_before.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_before.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/local_on_remote.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/remote_on_remote.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/local_on_remote.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/remote_on_remote.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_after.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/stylesheet_after.css', $fileHierarchy['stylesheets'][ $i++ ], "getFlatten maintains proper file order" );
    }

    /******************************************************************
     * getDependencySets
     *****************************************************************/


    /**
     * getDependencySets > 2 Packages
     *
     * Tests that we get 3 sets back, each set containing a file's dependencies with that file last,
     * and any dependent packages separate out, in order of dependence.
     *
     * Fixture folder: 2_deps_2_package_2_deep
     */
    public function testGetRootsWithTwoPackages()
    {
        $basePath = self::fixturesBasePath . '2_deps_2_package_2_deep';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath );

        $roots = $dependencyTree->getDependencySets();

        $this->assertCount( 3, $roots, "There should be 3 dependency sets returned" );

        $thisCollection = $roots[0];
        $this->assertInstanceOf( 'JsPackager\Compiler\DependencySet', $thisCollection, "Package #2 (Dep #4's) should be instance of DependencySet" );
        $this->assertNotEmpty( $thisCollection->dependencies, "Package #2 (Dep #4's) package should have dependent files" );
        $this->assertCount( 2, $thisCollection->dependencies, "Package #2 (Dep #4's) package should have 2 dependent files (including itself)" );
        $this->assertEquals( $basePath . '/package/subpackage/dep_5.js', $thisCollection->dependencies[0], "Package #2 (Dep #4's) should depend on Dep #5" );
        $this->assertEquals( $basePath . '/package/subpackage/dep_4.js', $thisCollection->dependencies[1], "Package #2 (Dep #4's) should include itself" );
        $this->assertEmpty( $thisCollection->packages, "Package #2 (Dep #4's) package should depend on no other packages" );
        $this->assertNotEmpty( $thisCollection->stylesheets, "Package #2 (Dep #4's) package should have stylesheets" );
        $this->assertCount( 1, $thisCollection->stylesheets, "Package #2 (Dep #4's) package should have 1 stylesheet" );
        $this->assertEquals( $basePath . '/package/subpackage/dep_4_style.css', $thisCollection->stylesheets[0], "Package #1 (Dep #3's) package should depend on dep_4_style.css" );


        $thisCollection = $roots[1];
        $this->assertInstanceOf( 'JsPackager\Compiler\DependencySet', $thisCollection, "Package #1 (Dep #3's) should be instance of DependencySet" );
        $this->assertCount( 1, $thisCollection->dependencies, "Package #1 (Dep #3's) package should have no dependencies (excluding itself)" );
        $this->assertEquals( $basePath . '/package/dep_3.js', $thisCollection->dependencies[0], "Package #1 (Dep #3's) depends on itself being included" );
        $this->assertCount( 1, $thisCollection->packages, "Package #1 (Dep #3's) package should depend on 1 other package" );
        $this->assertEquals( $basePath . '/package/subpackage/dep_4.js', $thisCollection->packages[0], "Package #1 (Dep #3's) package should depend on Package #2 (Dep #4's)" );
        $this->assertNotEmpty( $thisCollection->stylesheets, "Package #1 (Dep #3's) package should have stylesheets" );
        $this->assertCount( 1, $thisCollection->stylesheets, "Package #1 (Dep #3's) package should have 1 stylesheet" );
        $this->assertEquals( $basePath . '/package/dep_3_style.css', $thisCollection->stylesheets[0], "Package #1 (Dep #3's) package should depend on dep_3_style.css" );


        $thisCollection = $roots[2];
        $this->assertInstanceOf( 'JsPackager\Compiler\DependencySet', $thisCollection, "Root file should be instance of DependencySet" );
        $this->assertCount( 3, $thisCollection->dependencies, "The root file package has 3 dependencies (excluding itself)" );
        $this->assertEquals( $basePath . '/dep_1.js', $thisCollection->dependencies[0], "The root file should depend on Dep #1" );
        $this->assertEquals( $basePath . '/dep_2.js', $thisCollection->dependencies[1], "The root file should depend on Dep #2" );
        $this->assertEquals( $filePath, $thisCollection->dependencies[2], "The root file depends on itself being included last" );
        $this->assertCount( 1, $thisCollection->packages, "The root file should depend on 1 other package" );
        $this->assertEquals( $basePath . '/package/dep_3.js', $thisCollection->packages[0], "The root file's implicit package should depend on Package #1 (Dep #3's)" );
        $this->assertEmpty( $thisCollection->stylesheets, "The root file package has no stylesheets" );

    }


    public function testGetRootPackagesDoesNotSwallowMissingFileExceptionIfNotMuted()
    {
        // Test JavaScript

        $basePath = self::fixturesBasePath . '1_broken_js_reference';
        $filePath = $basePath . '/main.js';

        try {
            $dependencyTree = new DependencyTree( $filePath );
            $roots = $dependencyTree->getDependencySets();
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

        // Test Stylesheets

        $basePath = self::fixturesBasePath . '1_broken_css_reference';
        $filePath = $basePath . '/main.js';

        try {
            $dependencyTree = new DependencyTree( $filePath );
            $roots = $dependencyTree->getDependencySets();
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

    public function testGetRootPackagesDoesNotStopAtMissingFileExceptionIfMuted()
    {
        // Test JavaScript

        $basePath = self::fixturesBasePath . '1_broken_js_reference';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath, null, true );
        $roots = $dependencyTree->getDependencySets();

        $this->assertCount( 1, $roots, "There should be 1 dependency set returned" );

        $thisCollection = $roots[0];
        $this->assertInstanceOf( 'JsPackager\Compiler\DependencySet', $thisCollection, "Root file should be instance of DependencySet" );
        $this->assertCount( 3, $thisCollection->dependencies, "The root file package has 3 dependencies (including itself)" );
        $this->assertEquals( $basePath . '/heeper.js', $thisCollection->dependencies[0], "The root file should depend on broken file heeper.js" );
        $this->assertEquals( $basePath . '/helper.js', $thisCollection->dependencies[1], "The root file should depend on helper.js" );
        $this->assertEquals( $filePath, $thisCollection->dependencies[2], "The root file depends on itself being included last" );
        $this->assertEmpty( $thisCollection->packages, "The root file package depends on no other packages" );
        $this->assertEmpty( $thisCollection->stylesheets, "The root file package depends on no stylesheets" );


        // Test Stylesheets

        $basePath = self::fixturesBasePath . '1_broken_css_reference';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath, null, true );
        $roots = $dependencyTree->getDependencySets();

        $this->assertCount( 1, $roots, "There should be 1 dependency set returned" );

        $thisCollection = $roots[0];
        $this->assertInstanceOf( 'JsPackager\Compiler\DependencySet', $thisCollection, "Root file should be instance of DependencySet" );
        $this->assertCount( 1, $thisCollection->dependencies, "The root file package depends no other dependencies (excluding itself)" );
        $this->assertEquals( $filePath, $thisCollection->dependencies[0], "The root file depends on itself being included last" );
        $this->assertCount( 2, $thisCollection->stylesheets, "The root file package has 2 stylesheets" );
        $this->assertEquals( $basePath . '/heeper.css', $thisCollection->stylesheets[0], "The root file should depend on broken stylesheet heeper.css" );
        $this->assertEquals( $basePath . '/helper.css', $thisCollection->stylesheets[1], "The root file should depend on stylesheet helper.css" );
        $this->assertEmpty( $thisCollection->packages, "The root file package depends on no other packages" );
    }

    /**
     * getDependencySets
     * Fixture folder: 2_deps_2_package_2_deep
     */
    public function testGetRootsWithTwoPackagesDoesNotSwallowRecursionException()
    {
        $basePath = self::fixturesBasePath . 'recursion';
        $filePath = $basePath . '/main.js';

        try {
            $dependencyTree = new DependencyTree( $filePath );
            $roots = $dependencyTree->getDependencySets();
            $this->fail('Set should throw a recursion exception');
        } catch (RecursionException $e) { }
    }




    /******************************************************************
     * getDependencySets
     *****************************************************************/

    public function testGetDependencySetsReturnsArrayOfDependencySets()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $index = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $index );
        $roots = $dependencyTree->getDependencySets();

        $this->assertInstanceOf( 'JsPackager\Compiler\DependencySet', $roots[0], "Should be a DependencySet" );
        $this->assertInstanceOf( 'JsPackager\Compiler\DependencySet', $roots[1], "Should be a DependencySet" );
        $this->assertInstanceOf( 'JsPackager\Compiler\DependencySet', $roots[2], "Should be a DependencySet" );
    }

    public function testGetDependencySetsDoesNotIncludeRedundantDependencySets()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $index = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $index );
        $roots = $dependencyTree->getDependencySets();

        $this->assertEquals(3, count($roots), "Should contain 3 dependency sets");
        $this->assertEmpty(    $roots[0]->packages, "Should depend on nothing" );
        $this->assertContains( $basePath . '/dep_2.js', $roots[0]->dependencies, "Should include dep_2.js" );
        $this->assertContains( $basePath . '/dep_2.js', $roots[1]->packages, "Should depend on dep_2.js" );
        $this->assertContains( $basePath . '/dep_3.js', $roots[1]->dependencies, "Should include dep_3.js" );
        $this->assertContains( $basePath . '/dep_2.js', $roots[2]->packages, "Should depend on dep_2.js" );
        $this->assertContains( $basePath . '/dep_3.js', $roots[2]->packages, "Should depend on dep_3.js" );
        $this->assertContains( $basePath . '/dep_1.js', $roots[2]->dependencies, "Should include dep_1.js" );
        $this->assertContains( $basePath . '/main.js', $roots[2]->dependencies, "Should include main.js" );
    }

    public function testGetDependencySetsIncludesDependencySetsInOrder()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $index = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $index );
        $roots = $dependencyTree->getDependencySets();

        $this->assertEquals(3, count($roots), "Should contain 3 dependency sets");
        $this->assertEmpty(  $roots[0]->packages, "Should depend on nothing" );
        $this->assertEquals( $basePath . '/dep_2.js', $roots[0]->dependencies[0], "Should include dep_2.js" );
        $this->assertEquals( $basePath . '/dep_2.js', $roots[0]->dependencies[0], "Should include dep_2.js" );
        $this->assertEquals( $basePath . '/dep_2.js', $roots[1]->packages[0], "Should depend on dep_2.js" );
        $this->assertEquals( $basePath . '/dep_2.js', $roots[1]->packages[0], "Should depend on dep_2.js" );
        $this->assertEquals( $basePath . '/dep_3.js', $roots[1]->dependencies[0], "Should include dep_3.js" );
        $this->assertEquals( $basePath . '/dep_3.js', $roots[2]->packages[0], "Should depend on dep_3.js first" );
        $this->assertEquals( $basePath . '/dep_2.js', $roots[2]->packages[1], "Should depend on dep_2.js second" );
        $this->assertEquals( $basePath . '/dep_1.js', $roots[2]->dependencies[0], "Should include dep_1.js" );
        $this->assertEquals( $basePath . '/main.js', $roots[2]->dependencies[1], "Should include main.js" );
    }


    /**
     * @throws Exception\Parsing
     */
    public function testGetDependencySetsHandlesCustomRemoteSymbol()
    {
        /**
         * @var DependencySet $remoteDependencySet
         * @var DependencySet $localDependencySet
         */

        $basePath = self::fixturesBasePath . 'remote_annotation-custom-annotation';
        $mainJsPath = $basePath . '/main.js';
        $sharedPath = self::fixturesBasePath . 'remote_annotation-remote';

        $dependencyTree = new DependencyTree( $mainJsPath, null, false, null, $sharedPath );
        $dependencyTree->remoteSymbol = '$!cashmoney!$foobarbaz$!cashmoney!$'; // or whatever non @remote thing we want to use for testing

        $dependencySets = $dependencyTree->getDependencySets();

        $this->assertEquals(
            $sharedPath,
            $dependencyTree->remoteFolderPath,
            'Should provide a default value to use in place of ' . $dependencyTree->remoteSymbol
        );

        $this->assertEquals(
            2,
            count( $dependencySets ),
            "Expect 2 packages -- 1 for the file, 1 for the remote package"
        );

        $remoteDependencySet = $dependencySets[0];
        $localDependencySet  = $dependencySets[1];

        $this->assertEquals(
            3,
            count( $remoteDependencySet->dependencies ),
            "Remote package has 3 dependencies, including itself."
        );

        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/local_on_remote.js',
            $remoteDependencySet->dependencies[0]
        );
        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/remote_on_remote.js',
            $remoteDependencySet->dependencies[1]
        );
        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotepackage/script.js',
            $remoteDependencySet->dependencies[2]
        );



        $this->assertEquals(
            8,
            count( $localDependencySet->dependencies ),
            "Local package has 6 dependencies, including itself."
        );

        $this->assertEquals(
            1,
            count( $localDependencySet->packages ),
            "Local dependency set has 1 package."
        );
        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotepackage/script.js',
            $localDependencySet->packages[0],
            "Local dependency set's package is the remote package."
        );


        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-custom-annotation/local_file_before.js',
            $localDependencySet->dependencies[0]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-custom-annotation/local_subfolder/local_subfolder_before.js',
            $localDependencySet->dependencies[1]
        );
        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/local_on_remote.js',
            $localDependencySet->dependencies[2]
        );
        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/remote_on_remote.js',
            $localDependencySet->dependencies[3]
        );
        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotescript/script.js',
            $localDependencySet->dependencies[4]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-custom-annotation/local_subfolder/local_subfolder_after.js',
            $localDependencySet->dependencies[5]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-custom-annotation/local_file_after.js',
            $localDependencySet->dependencies[6]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation-custom-annotation/main.js',
            $localDependencySet->dependencies[7]
        );

    }

    /**
     * @throws Exception\Parsing
     */
    public function testGetDependencySetsHandlesRemoteAnnotations()
    {
        /**
         * @var DependencySet $remoteDependencySet
         * @var DependencySet $localDependencySet
         */

        $basePath = self::fixturesBasePath . 'remote_annotation';
        $mainJsPath = $basePath . '/main.js';

        $sharedPath = $basePath . '-remote';

        $dependencyTree = new DependencyTree( $mainJsPath, null, false, null, $sharedPath );

        $dependencySets = $dependencyTree->getDependencySets();

        $this->assertEquals(
            $sharedPath,
            $dependencyTree->remoteFolderPath,
            'Should provide a default value to use in place of ' . $dependencyTree->remoteSymbol
        );

        $this->assertEquals(
            2,
            count( $dependencySets ),
            "Expect 2 packages -- 1 for the file, 1 for the remote package"
        );

        $remoteDependencySet = $dependencySets[0];
        $localDependencySet  = $dependencySets[1];

        $this->assertEquals(
            3,
            count( $remoteDependencySet->dependencies ),
            "Remote package has 3 dependencies, including itself."
        );

        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/local_on_remote.js',
            $remoteDependencySet->dependencies[0]
        );
        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotepackage/package_subfolder/remote_on_remote.js',
            $remoteDependencySet->dependencies[1]
        );
        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotepackage/script.js',
            $remoteDependencySet->dependencies[2]
        );



        $this->assertEquals(
            8,
            count( $localDependencySet->dependencies ),
            "Local package has 6 dependencies, including itself."
        );

        $this->assertEquals(
            1,
            count( $localDependencySet->packages ),
            "Local dependency set has 1 package."
        );
        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotepackage/script.js',
            $localDependencySet->packages[0],
            "Local dependency set's package is the remote package."
        );


        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_file_before.js',
            $localDependencySet->dependencies[0]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.js',
            $localDependencySet->dependencies[1]
        );
        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/local_on_remote.js',
            $localDependencySet->dependencies[2]
        );
        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotescript/script_subfolder/remote_on_remote.js',
            $localDependencySet->dependencies[3]
        );
        $this->assertEquals(
            $dependencyTree->remoteSymbol . '/remotescript/script.js',
            $localDependencySet->dependencies[4]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.js',
            $localDependencySet->dependencies[5]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/local_file_after.js',
            $localDependencySet->dependencies[6]
        );
        $this->assertEquals(
            'tests/JsPackager/fixtures/remote_annotation/main.js',
            $localDependencySet->dependencies[7]
        );

    }



    public function testCanBeReUsed()
    {
        $basePath = self::fixturesBasePath . 'remote_annotation';
        $remotePath = self::fixturesBasePath . 'remote_annotation-remote';
        $filePath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $filePath, null, false, null, $remotePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree();

        $this->assertCount( 19, $fileHierarchy );

        $i = 0;
        $this->assertEquals( $basePath . '/stylesheet_before.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/local_subfolder/local_subfolder_before.css', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );

        $basePath = self::fixturesBasePath . '3_deps_1_feedback';
        $remotePath = self::fixturesBasePath . 'remote_annotation-remote';
        $filePath = $basePath . '/main.js';

        $dependencyTree->setFilePath( $filePath );

        $fileHierarchy = $dependencyTree->flattenDependencyTree();

        $this->assertCount( 4, $fileHierarchy );

        $i = 0;
        $this->assertEquals( $basePath . '/dep_1.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_2.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $basePath . '/dep_3.js', $fileHierarchy[ $i++ ], "getFlatten maintains proper file order" );
        $this->assertEquals( $filePath, $fileHierarchy[ $i ], "Given file is last" );
    }

}
