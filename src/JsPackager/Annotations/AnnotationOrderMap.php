<?php

namespace JsPackager\Annotations;

/**
 * Container for ordering mapped annotations found in a file stored in individual ordered arrays
 *
 * Class AnnotationOrderMap
 * @package JsPackager\Annotations
 */
class AnnotationOrderMap
{

    /**
     * @var AnnotationOrderMapping[] Array of AnnotationOrderMappings
     */
    public $annotationOrderMap;

    /**
     * Create an Annotation Order Mapping entry.
     *
     * @param string $annotationName Name of the annotation bucket this maps for.
     * @param int $annotationIndex Index into that annotation bucket this entry represents.
     */
    public function __construct( ) {
        $this->annotationOrderMap = array();
    }

    /**
     * @return int
     */
    public function addAnnotation(AnnotationOrderMapping $entry)
    {
        $this->annotationOrderMap[] = $entry;
    }

    /**
     * @return array|AnnotationOrderMapping[]
     */
    public function getAnnotationMappings()
    {
        // TODO This should be immutable:
        // TODO this should be a copy. Test and make sure changing result does not change inner stored annotation mappings!
        return $this->annotationOrderMap;
    }


}


