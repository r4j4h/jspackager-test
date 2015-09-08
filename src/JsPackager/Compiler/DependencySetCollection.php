<?php
/**
 * DependencySetCollection represents a file and all of it's possibly bundled dependencies.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager\Compiler;

use Iterator;

class DependencySetCollection implements Iterator
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

    public function getDependencySets()
    {
        return $this->dependencySets;
    }

    public function appendDependencySet(DependencySet $dependencySet)
    {
        return array_push( $this->dependencySets, $dependencySet );
    }

    public function prependDependencySet(DependencySet $dependencySet)
    {
        return array_unshift( $this->dependencySets, $dependencySet );
    }

    public function removeReundantDependencySets()
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
        return $this->dependencySets[$this->position];
    }

    function peekPrevious() {
        $offset = ($this->position - 1);
        if ( $offset < 0 ) {
            return null;
        }
        return $this->dependencySets[ $offset ];
    }

    function key() {
        return $this->position;
    }

    function next() {
        ++$this->position;
    }

    function valid() {
        return isset($this->dependencySets[$this->position]);
    }

}