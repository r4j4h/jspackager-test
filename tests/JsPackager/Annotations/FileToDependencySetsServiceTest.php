<?php

namespace JsPackager\Annotations\Unit;

use JsPackager\Annotations\FileToDependencySetsService;
use JsPackager\File;
use Psr\Log\NullLogger;

class FileToDependencySetsServiceTest extends \PHPUnit_Framework_TestCase
{

    public function testCanConvertFileTreeIntoDependencySetsCollection()
    {
        // todo Use a factory to build the file tree? or a full file mock?
        $fileTree = $this->getBasicFileTree();

        $service = new FileToDependencySetsService(new NullLogger());
        $dependencySets = $service->getDependencySets( $fileTree );

//        $this->assertEquals( $dependencySets, 3 );

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
     * @return \JsPackager\Helpers\FileHandler
     */
    private function getMockedFileHandler()
    {
        return $this->getMock('JsPackager\Helpers\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
    }

}