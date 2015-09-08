<?php
/**
 * The File DependencySet is used to represent a file and its set of dependencies.
 *
 * This set may include stylesheets, scripts to be compiled, and other packages.
 *
 * @category WebPT
 * @package JsPackager
 * @copyright Copyright (c) 2012 WebPT, INC
 */

namespace JsPackager\Compiler;

class DependencySet
{
    /**
     * Array of relative stylesheet paths
     *
     * @var array
     */
    public $stylesheets;

    /**
     * Array of relative package root paths
     *
     * @var array
     */
    public $packages;

    /**
     * Array of relative script paths, with this root file being the last
     *
     * @var array
     */
    public $dependencies;

    /**
     * Array of paths of files that should not be compiled
     *
     * @var array
     */
    public $pathsMarkedNoCompile;


    /**
     * DependencySet Constructor
     *
     * Please note: All provided arrays should be in dependency order.
     *
     * @param array $stylesheets Array of relative stylesheet paths
     * @param array $packages Array of relative package root paths
     * @param array $dependencies Array of relative script paths, with this root file being the last
     * @param array $pathsMarkedNoCompile Array of paths of files that should not be compiled
     */
    public function __construct( $stylesheets = array(), $packages = array(), $dependencies = array(), $pathsMarkedNoCompile = array() )
    {
        $this->stylesheets = $stylesheets;
        $this->packages = $packages;
        $this->dependencies = $dependencies;
        $this->pathsMarkedNoCompile = $pathsMarkedNoCompile;
    }
}