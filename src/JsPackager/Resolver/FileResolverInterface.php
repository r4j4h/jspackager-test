<?php

namespace JsPackager\Resolver;

use JsPackager\File;
use JsPackager\ResolverContext;

interface FileResolverInterface {

    public function resolveDependenciesForFile(File $file, ResolverContext $context);

}