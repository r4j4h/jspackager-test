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
     * @var array
     */
    public $stylesheets;

    /**
     * @var array
     */
    public $packages;

    /**
     * @var array
     */
    public $dependencies;


    /**
     * DependencySet Constructor
     *
     * Please note: All provided arrays should be in dependency order.
     *
     * @param array $stylesheets Array of relative stylesheet paths
     * @param array $packages Array of relative package root paths
     * @param array $dependencies Array of relative script paths, with this root file being the last
     */
    public function __construct( $stylesheets = array(), $packages = array(), $dependencies = array() )
    {
        $this->stylesheets = $stylesheets;
        $this->packages = $packages;
        $this->dependencies = $dependencies;
    }
}