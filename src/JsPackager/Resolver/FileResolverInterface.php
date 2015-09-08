<?php

namespace JsPackager\Resolver;

use JsPackager\DependencyFileInterface;
use JsPackager\AnnotationBasedResolverContext;

interface FileResolverInterface {

    public function resolveDependenciesForFile(DependencyFileInterface $file, AnnotationBasedResolverContext $context);

}