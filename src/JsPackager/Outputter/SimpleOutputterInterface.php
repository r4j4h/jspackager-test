<?php
namespace JsPackager\Outputter;

interface SimpleOutputterInterface
{
    /**
     * @param SimpleOutputterParams $params
     * @return SimpleOutputterResult
     */
    public function output(SimpleOutputterParams $params);
}