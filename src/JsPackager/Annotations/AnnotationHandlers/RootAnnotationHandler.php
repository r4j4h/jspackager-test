<?php

namespace JsPackager\Annotations\AnnotationHandlers;

use JsPackager\Annotations\AnnotationHandlerParameters;

class RootAnnotationHandler
{
    public function doAnnotation_root(AnnotationHandlerParameters $params)
    {
        // This is considered parameterless so if params came through ignore it as it is likely a misread.
        if ( $params->path ) {
            return;
        }

        $params->file->isRoot = true;
        $params->file->addMetaData('isRoot', true);
    }
}