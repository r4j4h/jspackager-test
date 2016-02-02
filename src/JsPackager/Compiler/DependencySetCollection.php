<?php
/**
 * DependencySetCollection represents a file and all of it's possibly bundled dependencies.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager\Compiler;

use ArrayAccess;
use Countable;
use Iterator;

class DependencySetCollection implements Iterator, ArrayAccess, Countable
{
    private $position = 0;

    /**
     * @var array
     */
    private $dependencySets;

    /**
     * DependencySetCollection Constructor
     *
     * Please note: All provided DependencySets should be in dependency order.
     */
    public function __construct()
    {
        $this->dependencySets = array();
        $this->position = 0;
    }

    /**
     * Factory Method for constructing a DependencySetCollection from an array of dependency sets.
     *
     * @param $depSetsArray
     * @return DependencySetCollection
     */
    public static function fromDependencySets($depSetsArray)
    {
        $collection = new DependencySetCollection();
        foreach($depSetsArray as $depSet)
        {
            if ( is_array($depSet) ) {
                foreach($depSet as $subDepSet) {
                    $collection->appendDependencySet( $subDepSet );
                }
            } else {
                $collection->appendDependencySet($depSet);
            }
        }
        $collection->removeRedundantDependencySets();
        return $collection;
    }

    public function getDependencySets()
    {
        return $this->dependencySets;
    }

    public function peekRoot()
    {
        return $this->dependencySets[ count( $this->dependencySets ) - 1 ];
    }

    public function appendDependencySet(DependencySet $dependencySet)
    {
        return array_push( $this->dependencySets, $dependencySet );
    }

    public function prependDependencySet(DependencySet $dependencySet)
    {
        return array_unshift( $this->dependencySets, $dependencySet );
    }

    public function removeRedundantDependencySets()
    {
        // Ensure we do not return redundant root packages
        $uniquePackages = array();
        foreach( $this->getDependencySets() as $thisRootPackage )
        {
            if ( !in_array( $thisRootPackage, $uniquePackages ) )
            {
                $uniquePackages[] = $thisRootPackage;
            }
        }
        $this->dependencySets = $uniquePackages;
        return $this->getDependencySets();
    }

    function rewind() {
        $this->position = 0;
    }

    function current() {
        return $this->offsetGet($this->position);
    }

    function peekPrevious() {
        $offset = ($this->position - 1);
        if ( $offset < 0 ) {
            return null;
        }
        return $this->offsetGet($offset);
    }

    function key() {
        return $this->position;
    }

    function next() {
        ++$this->position;
    }

    function valid() {
        return $this->offsetExists($this->position);
    }

    public function offsetExists($offset)
    {
        return isset($this->dependencySets[$offset]);
    }

    public function offsetGet($offset)
    {
        return ( $this->offsetExists( $offset ) ? $this->dependencySets[$offset] : null );
    }

    public function offsetSet($offset, $value)
    {
        $this->dependencySets[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset( $this->dependencySets[$offset] );
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return count( $this->dependencySets );
    }
}