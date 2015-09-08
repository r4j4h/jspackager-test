<?php

namespace JsPackager\Outputter;

class CompiledAndManifestOutputter implements SimpleOutputterInterface
{

    /**
     * @param SimpleOutputterParams $params
     * @return SimpleOutputterResult
     */
    public function output(SimpleOutputterParams $params)
    {
        $successful = true;
        $errs = array();
// todo move manifest file creation stuff here from Compiler.php
        $result = new SimpleOutputterResult($successful, $errs, $file->getMetaData(), $params->getDependencySets());

    }
}