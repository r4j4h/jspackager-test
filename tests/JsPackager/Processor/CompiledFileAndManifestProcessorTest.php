<?php

namespace JsPackager\Processor\Unit;

use JsPackager\Compiler\DependencySet;
use JsPackager\Compiler\DependencySetCollection;
use JsPackager\File;
use JsPackager\ManifestContentsGenerator;
use JsPackager\Processor\CompiledAndManifestProcessor;
use JsPackager\Processor\NonStreamingConcatenationProcessor;
use JsPackager\Processor\SimpleProcessorParams;
use Psr\Log\NullLogger;

class CompiledFileAndManifestProcessorTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessCombinesScriptsIntoOneScriptFile()
    {

    }

    public function testProcessCombinesRemoteScriptsIntoScriptFile()
    {

    }

    public function testProcessAddsFilesWithIsRootMetadataToManifest()
    {

    }

    public function testProcessAddsStylesheetsToManifest()
    {
        $logger = new NullLogger();
        $processor = new CompiledAndManifestProcessor(
            new NonStreamingConcatenationProcessor($logger),
            new ManifestContentsGenerator('@remote', 'shared', $logger),
            $logger
        );
//
//        $files = $this->getBasicFileTree();
//        $params = new SimpleProcessorParams( $files->getAsArray() );
//
//        $processed = $processor->process( $params );

//        $this->assertEmpty($processed);

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