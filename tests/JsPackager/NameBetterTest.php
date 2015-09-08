<?php

namespace JsPackager\Test;

use JsPackager\Annotations\FileToDependencySetsService;
use JsPackager\Compiler\DependencySet;
use JsPackager\Compiler\DependencySetCollection;
use JsPackager\ContentBasedFile;
use JsPackager\File;
use JsPackager\StreamBasedFile;
use PHPUnit_Framework_Test;
use Psr\Log\NullLogger;

class NameBetterTest extends \PHPUnit_Framework_TestCase
{

    public function testFileAlterReturnsNewFile()
    {
//        $this->markTestIncomplete('not sure where i am going with this yet');
//        $mockedFileHandler = $this->getMockedFileHandler();
//        $rootFileA = new ContentBasedFile('foo', sys_get_temp_dir(), array());
//        $rootFileA->addMetaData('foo','foo');
//        $fileB = clone $rootFileA;
//        $fileB->addMetaData('bar', 'bar');
//
//        $this->assertEquals( 'foo', $rootFileA->getMetaDataKey('foo') );
//        $this->assertEquals( 'foo', $fileB->getMetaDataKey('foo') );
//        $this->assertEquals( null, $rootFileA->getMetaDataKey('bar') );
//        $this->assertEquals( 'bar', $fileB->getMetaDataKey('bar') );
//
//        $this->assertEquals( 'bar', $rootFileA->getPath() );
//        $this->assertEquals( 'bar', $fileB->getPath() );
//        $this->assertEquals( 'bar', $rootFileA->getContents() );
//        $this->assertEquals( 'bar', $fileB->getContents() );
    }

    public function testCanConvertFileIntoPathBasedFile()
    {

    }

    public function testCanConvertFileIntoStreamBasedFile()
    {

    }

    public function testCanConvertContentIntoContentBasedFile()
    {

    }

    public function testCanConvertContentIntoStreamBasedFile()
    {

    }

    ///
    public function testCanConvertFileTreeIntoFileList()
    {
//        $rootFileA = $this->getBasicFileTree();
//
//        $fileList = convertFileTreeIntoFileList($rootFileA);
//
//        $this->assertCount(3, $fileList);
//        $this->assertCount(1, $fileList[0]);
//        $this->assertCount(2, $fileList[1]);
//        $this->assertCount(1, $fileList[1][0]);
//        $this->assertCount(1, $fileList[1][1]);
//        $this->assertCount(1, $fileList[2]);
    }

    public function testCanConvertFileListIntoFileTree()
    {

    }

    public function testCanConvertFileListIntoDependencySetsCollection()
    {

    }

    public function testCanConvertDependencySetsCollectionIntoFileList()
    {

    }


    public function testCanConvertDependencySetsCollectionIntoFileTree()
    {
//        $fileTree = $this->getBasicFileTree();
//
//        $service = new FileToDependencySetsService(new NullLogger());
//        $dependencySets = $service->getDependencySets( $fileTree );
//
//        $service = new DependencySetsToFileService();
//        $newTree = $service->getFileTree( $dependencySets );
//
//        $this->assertEquals( 'rootFileA', $newTree->getFilename() );


    }

    /**
     * @return File
     */
    private function getBasicFileTree()
    {
        $mockedFileHandler = $this->getMockedFileHandler();
        $rootFileA = new File('rootFileA', true, $mockedFileHandler);
        $childFileB = new File('childFileB', true, $mockedFileHandler);
        $childFileC = new File('childFileC', true, $mockedFileHandler);
        $childFileCChildFileD = new File('childFileCChildFileD', true, $mockedFileHandler);
        $childFileE = new File('childFileE', true, $mockedFileHandler);
        $childFileEChildFileF = new File('childFileEChildFileF', true, $mockedFileHandler);

        $rootFileA->addMetaData('scripts', array(
            $childFileB, $childFileC, $childFileE
        ));

        $childFileC->addMetaData('scripts', array(
            $childFileCChildFileD
        ));

        $childFileE->addMetaData('scripts', array(
            $childFileEChildFileF
        ));


//        $mockedFileHandler = $this->getMockedFileHandler();
//        $mockedFileHandler->expects($this->any())
//            ->method('is_file')
//            ->will($this->returnValue(true));
//        $mockedFileHandler->expects($this->any())
//            ->method('fopen')
//            ->will($this->returnValue(true));
//        $mockedFileHandler->expects($this->any())
//            ->method('fgets')
//            ->will($this->onConsecutiveCalls($lineA, $lineB, false));
//        $mockedFileHandler->expects($this->any())
//            ->method('fclose')
//            ->will($this->returnValue(true));




        return $rootFileA;
    }

    /**
     * @return array
     */
    private function getBasicFileList()
    {
        $mockedFileHandler = $this->getMockedFileHandler();
        $rootFileA = new File('rootFileA', true, $mockedFileHandler);
        $childFileB = new File('childFileB', true, $mockedFileHandler);
        $childFileC = new File('childFileC', true, $mockedFileHandler);
        $childFileCChildFileD = new File('childFileCChildFileD', true, $mockedFileHandler);
        $childFileE = new File('childFileE', true, $mockedFileHandler);
        $childFileEChildFileF = new File('childFileEChildFileF', true, $mockedFileHandler);

        $rootFileA->addMetaData('scripts', array(
            $childFileB, $childFileC, $childFileE
        ));

        $childFileC->addMetaData('scripts', array(
            $childFileCChildFileD
        ));

        $childFileE->addMetaData('scripts', array(
            $childFileEChildFileF
        ));

        $fileList = array(
            array($childFileB),
            array(
                array($childFileCChildFileD),
                array($childFileC),
            ),
            array($rootFileA),
        );

//        $mockedFileHandler = $this->getMockedFileHandler();
//        $mockedFileHandler->expects($this->any())
//            ->method('is_file')
//            ->will($this->returnValue(true));
//        $mockedFileHandler->expects($this->any())
//            ->method('fopen')
//            ->will($this->returnValue(true));
//        $mockedFileHandler->expects($this->any())
//            ->method('fgets')
//            ->will($this->onConsecutiveCalls($lineA, $lineB, false));
//        $mockedFileHandler->expects($this->any())
//            ->method('fclose')
//            ->will($this->returnValue(true));

        return $fileList;
    }

    private function getBasicDependencySet()
    {
        $mockedFileHandler = $this->getMockedFileHandler();
        $rootFileA = new File('rootFileA', true, $mockedFileHandler);
        $childFileB = new File('childFileB', true, $mockedFileHandler);
        $childFileC = new File('childFileC', true, $mockedFileHandler);
        $childFileCChildFileD = new File('childFileCChildFileD', true, $mockedFileHandler);
        $childFileE = new File('childFileE', true, $mockedFileHandler);
        $childFileEChildFileF = new File('childFileEChildFileF', true, $mockedFileHandler);

        $rootFileA->addMetaData('scripts', array(
            $childFileB, $childFileC, $childFileE
        ));

        $childFileC->addMetaData('scripts', array(
            $childFileCChildFileD
        ));

        $childFileE->addMetaData('scripts', array(
            $childFileEChildFileF
        ));

        $depSetCollection = new DependencySetCollection();

        $depSetCollection->appendDependencySet(
            new DependencySet(array(), array(), array('A') )
        );
        $depSetCollection->appendDependencySet(
            new DependencySet(array(), array(), array('B') )
        );
        $depSetCollection->appendDependencySet(
            new DependencySet(array(), array(), array('D', 'C') )
        );
        $depSetCollection->appendDependencySet(
            new DependencySet(array(), array(), array('F', 'E') )
        );

        return $depSetCollection;
    }

    /**
     * @return \JsPackager\Helpers\FileHandler
     */
    private function getMockedFileHandler()
    {
        return $this->getMock('JsPackager\Helpers\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
    }

}