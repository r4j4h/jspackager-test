<?php
/**
 * DependencySetCollection represents a file and all of it's possibly bundled dependencies.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager\Compiler;

class DependencySetCollection
{
    /**
     * @var array
     */
    public $dependencySets;

    /**
     * DependencySetCollection Constructor
     *
     * Please note: All provided DependencySets should be in dependency order.
     */
    public function __construct()
    {
        $this->dependencySets = array();
    }

    public function getDependencySets()
    {
        return $this->dependencySets;
    }

    public function appendDependencySet(DependencySet $dependencySet)
    {
        array_push( $this->dependencySets, $dependencySet );
    }

    public function prependDependencySet(DependencySet $dependencySet)
    {
        array_unshift( $this->dependencySets, $dependencySet );
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
}