<?php
use JsPackager\Resolver\FileResolverInterface;

/**

FragmentAppendingResolver enables injection of small inline fragments.

This may be any content. Some example uses are inline javascript for injecting variables or configuration details. Or
for scss for variable injection. It could also be for banners or footers. It could be used as an underlying bisection
enabling inserting javascript in the middle of a file by splitting the file in halves, before and after the split. Each
half would then be a FragmentAppendingResolver.

FragmentAppendingResolvers are created with a body of their fragment, and an optional path.

If no path is given, they generate one in a temporary directory or remain in memory if a path is never needed.

Note that if a path is not given they will be outside the web root.

*/

class FragmentAppendingResolver implements FileResolverInterface
{
    public function __construct($contents, $path = null)
    {
        $path = isset( $path ) ? $path : sys_get_temp_dir();
        $file = new \JsPackager\ContentBasedFile($contents, $path, array());
    }

    public function resolveDependenciesForFile(\JsPackager\DependencyFileInterface $file, \JsPackager\AnnotationBasedResolverContext $context)
    {
//        $file->addMetaData()
        // TODO: Implement resolveDependenciesForFile() method.
    }
}