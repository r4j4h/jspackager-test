<?php

namespace JsPackager\Resolver;

interface FileResolverInterface {

    public function resolveDependenciesForFile($filePath);

}