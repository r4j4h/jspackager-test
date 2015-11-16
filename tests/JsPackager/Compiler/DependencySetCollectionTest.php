<?php
/**
 * Created by PhpStorm.
 * User: jasmine
 * Date: 11/15/2015
 * Time: 8:34 PM
 */

namespace JsPackager\Compiler\Unit;


use JsPackager\Compiler\DependencySet;
use JsPackager\Compiler\DependencySetCollection;

class DependencySetCollectionTest extends \PHPUnit_Framework_TestCase
{

    public function testFromDependencySetsFactoryMethodCreatesDependencySetCollection()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');
        $arr = array($depSetA, $depSetB);

        $sets = DependencySetCollection::fromDependencySets($arr);

        $this->assertInstanceOf('\JsPackager\Compiler\DependencySetCollection', $sets);
    }

    public function testFromDependencySetsFactoryMethodAcceptsArrayOfArrays()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');
        $arrAB = array($depSetA, $depSetB);

        $depSetC = new DependencySet('file_c.js');
        $depSetD = new DependencySet('file_d.js');
        $arrCD = array($depSetC, $depSetD);

        $arr = array($arrAB,$arrCD);

        $sets = DependencySetCollection::fromDependencySets($arr);

        $this->assertInstanceOf('\JsPackager\Compiler\DependencySetCollection', $sets);
    }

    /**
     * @depends testFromDependencySetsFactoryMethodCreatesDependencySetCollection
     */
    public function testCurrentGetsSetInCollection()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');
        $arr = array($depSetA, $depSetB);

        $sets = DependencySetCollection::fromDependencySets($arr);

        $this->assertSame($depSetA, $sets->current());
    }

    public function testNextIteratesThroughCollection()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');
        $arr = array($depSetA, $depSetB);

        $sets = DependencySetCollection::fromDependencySets($arr);

        $this->assertNull($sets->next());
        $this->assertSame($depSetB, $sets->current());
        $this->assertNull($sets->next());
        $this->assertNull($sets->current());
    }

    public function testNextIteratesIntoNull()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');
        $arr = array($depSetA, $depSetB);

        $sets = DependencySetCollection::fromDependencySets($arr);

        $this->assertNull($sets->next());
        $this->assertNull($sets->next());
        $this->assertNull($sets->current());
    }

    public function testRewind()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');
        $arr = array($depSetA, $depSetB);

        $sets = DependencySetCollection::fromDependencySets($arr);

        $this->assertNull($sets->next());
        $this->assertNull($sets->next());
        $this->assertNull($sets->rewind());
        $this->assertSame($depSetA, $sets->current());
    }

    public function testGetDependencySets()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');
        $arr = array($depSetA, $depSetB);

        $sets = DependencySetCollection::fromDependencySets($arr);

        $gotSets = $sets->getDependencySets();

        $this->assertEquals(array($depSetA, $depSetB), $gotSets);
    }

    public function testPeekRoot()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');
        $arr = array($depSetA, $depSetB);

        $sets = DependencySetCollection::fromDependencySets($arr);

        $gotSets = $sets->peekRoot();

        $this->assertEquals($depSetB, $gotSets);
    }

    public function testPeekPrevious()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');
        $arr = array($depSetA, $depSetB);

        $sets = DependencySetCollection::fromDependencySets($arr);

        $this->assertNull($sets->peekPrevious());
        $this->assertNull($sets->next());
        $this->assertSame($depSetA, $sets->peekPrevious());
        $this->assertNull($sets->next());
        $this->assertSame($depSetB, $sets->peekPrevious());
        $this->assertNull($sets->next());
        $this->assertNull($sets->peekPrevious());
        $this->assertNull($sets->rewind());
        $this->assertNull($sets->peekPrevious());

        // Start over after rewind...
        $this->assertNull($sets->next());
        $this->assertSame($depSetA, $sets->peekPrevious());
    }

    public function testKeyReturnsCurrentPosition()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');
        $arr = array($depSetA, $depSetB);

        $sets = DependencySetCollection::fromDependencySets($arr);

        $this->assertEquals(0,$sets->key());
        $this->assertNull($sets->next());
        $this->assertEquals(1,$sets->key());
        $this->assertNull($sets->next());
        $this->assertEquals(2,$sets->key());
        $this->assertNull($sets->next());
        $this->assertEquals(3,$sets->key());
        $this->assertNull($sets->rewind());
        $this->assertEquals(0,$sets->key());
        $this->assertNull($sets->next());
        $this->assertEquals(1,$sets->key());
    }

    public function testValidReturnsTrueIfDependencySetExistsAtCurrentPosition()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');
        $arr = array($depSetA, $depSetB);

        $sets = DependencySetCollection::fromDependencySets($arr);

        $this->assertEquals(true,$sets->valid());
        $this->assertNull($sets->next());
        $this->assertEquals(true,$sets->valid());
        $this->assertNull($sets->next());
        $this->assertEquals(false,$sets->valid());
        $this->assertNull($sets->next());
        $this->assertEquals(false,$sets->valid());
        $this->assertNull($sets->rewind());
        $this->assertEquals(true,$sets->valid());
        $this->assertNull($sets->next());
        $this->assertEquals(true,$sets->valid());
    }

    public function testAppendDependencySet()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');

        $sets = new DependencySetCollection();
        $sets->appendDependencySet($depSetA);
        $sets->appendDependencySet($depSetB);

        $gotSets = $sets->getDependencySets();

        $this->assertEquals(array($depSetA, $depSetB), $gotSets);
    }

    public function testPrependDependencySet()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');

        $sets = new DependencySetCollection();
        $sets->prependDependencySet($depSetB);
        $sets->prependDependencySet($depSetA);

        $gotSets = $sets->getDependencySets();

        $this->assertEquals(array($depSetA, $depSetB), $gotSets);
    }

    public function testOffsetSet()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');

        $sets = new DependencySetCollection();
        $sets->offsetSet(1, $depSetB);
        $sets->offsetSet(0, $depSetA);

        $gotSets = $sets->getDependencySets();

        $this->assertEquals(array($depSetA, $depSetB), $gotSets);
        $this->assertSame($depSetA, $sets->offsetGet(0));
        $this->assertSame($depSetB, $sets->offsetGet(1));
    }

    public function testOffsetUnset()
    {
        $depSetA = new DependencySet('file_a.js');
        $depSetB = new DependencySet('file_b.js');

        $sets = new DependencySetCollection();
        $sets->offsetSet(1, $depSetB);
        $sets->offsetSet(0, $depSetA);

        $gotSets = $sets->getDependencySets();

        $this->assertEquals(array($depSetA, $depSetB), $gotSets);
        $this->assertTrue($sets->valid());

        $sets->offsetUnset(0);
        $sets->offsetUnset(1);

        $this->assertEquals(array(), $sets->getDependencySets());
        $this->assertFalse($sets->valid());
    }

}
