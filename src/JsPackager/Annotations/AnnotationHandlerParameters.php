<?php
namespace JsPackager\Annotations;

use JsPackager\DependencyFileInterface;
use JsPackager\Exception;

class AnnotationHandlerParameters
{

    public $file;
    public $path;
    public $filePath;
    public $testsSourcePath;

    /**
     *
     *
     */
    public $recursionCb;

    public function __construct(DependencyFileInterface &$file,
                                $path,
                                $testsSourcePath,
                                $recursionCb) {
        $this->path = $path;
        $this->file = $file;
        $this->filePath = $file->getPath();
        $this->testsSourcePath = $testsSourcePath;
        $this->recursionCb = $recursionCb;
    }

}
