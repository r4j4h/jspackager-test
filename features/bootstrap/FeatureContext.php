<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;
use Psr\Log\NullLogger;

//
// Require 3rd-party libraries here:
//
//   require_once '../../vendor/autoload.php';
//   require_once 'PHPUnit/Framework/Assert/Functions.php';
//

/**
 * Features context.
 */
class FeatureContext extends BehatContext
{
    /**
     * Initializes context.
     * Every scenario gets its own context object.
     *
     * @param array $parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters)
    {
        // Initialize your context here
    }

//
// Place your definition and hook methods here:
//
//    /**
//     * @Given /^I have done something with "([^"]*)"$/
//     */
//    public function iHaveDoneSomethingWith($argument)
//    {
//        doSomethingWith($argument);
//    }
//

    /**
     * @BeforeScenario
     */
    public function prepTestDir()
    {
        $this->filesCreated = array();
        $this->dir = 'behattestdir';
        mkdir($this->dir);
    }

    /**
     * @AfterScenario
     */
    public function cleanUpBehatTestDir()
    {
        foreach ( $this->filesCreated as $k => $v ) {
            unlink($v[0]);
        }
        rmdir('behattestdir');
    }

    /**
     * @Given /^a ([^"]*) file named "([^"]*)"$/
     */
    public function aFileNamed($fileType, $arg1)
    {
        $path = $this->dir . '/' . $arg1;
        touch($path);
        $this->filesCreated[$fileType] = [$path, ''];
    }

    /**
     * @Given /^a ([^"]*) file named "([^"]*)" containing$/
     */
    public function aFileContaining($fileType, $arg1, PyStringNode $string)
    {
        $path = $this->dir . '/' . $arg1;
        touch($path);
        file_put_contents($path, $string);
        $this->filesCreated[$fileType] = [$path, $string];
    }

    /**
     * @When /^I resolve for the ([^"]*) file$/
     */
    public function iResolveForTheSourceFile($fileType)
    {
        // resolver

        $resolver = new \JsPackager\ManifestResolver($this->dir, 'behatremote', '@remoties', new NullLogger());
        try {
            $this->result = $resolver->resolveFile($this->filesCreated[$fileType][0]);
        }
        catch (Exception $e) {
            $this->result = $e;
        }
    }

    /**
     * @Then /^display last command output:$/
     */
    public function displayLastCommandOutput(PyStringNode $string)
    {
        PHPUnit_Framework_Assert::assertNotInstanceOf(Exception::class, $this->result );
        PHPUnit_Framework_Assert::assertEquals($string->__toString(), join($this->result, "\n"));
    }


    /**
     * @Then /^I get an exception:$/
     */
    public function iGetAnException(PyStringNode $string)
    {
        PHPUnit_Framework_Assert::assertInstanceOf(Exception::class, $this->result );
        PHPUnit_Framework_Assert::assertEquals($string->__toString(), $this->result->getMessage());
    }

}
