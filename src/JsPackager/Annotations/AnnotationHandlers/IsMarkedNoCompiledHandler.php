<?php

namespace JsPackager\Annotations\AnnotationHandlers;

use JsPackager\Annotations\AnnotationHandlerParameters;

class IsMarkedNoCompiledHandler
{

    public function doAnnotation_noCompile(AnnotationHandlerParameters $params)
    {
        // This is considered parameterless so if params came through ignore it as it is likely a misread.
        if ( $params->path ) {
            return;
        }

        $params->file->isMarkedNoCompile = true;
        $params->file->addMetaData('isMarkedNoCompile', true);
    }
}