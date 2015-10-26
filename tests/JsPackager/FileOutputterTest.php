<?php

namespace JsPackager\Unit;

class FileOutputterTest extends \PHPUnit_Framework_TestCase
{

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

    public function testOutputWritesEachFileToItsPathType()
    {
        $dependencySets = $this->getDependencySetsFixtureA();

//        $fileOutputter = new FileOutputter();
//        $output = $fileOutputter->output($dependencySets);

        // output should be success/fail results for writes
        // use vfstream to test that the files were written
    }

    public function testOutputThrowsIfNoPermissionToWrite()
    {
        $dependencySets = $this->getDependencySetsFixtureA();

        // configure vfstream to not have permissions in directory

//        $fileOutputter = new FileOutputter();
//        $output = $fileOutputter->output($dependencySets);

        // test that an error was thrown
        // use vfstream to test that files were not written
    }

}