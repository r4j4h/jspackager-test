<?php

namespace JsPackager\Annotations;

/**
 * Mapping for ordering each annotation found
 *
 * Class AnnotationOrderMap
 * @package JsPackager\Annotations
 */
class AnnotationOrderMapping
{

    /**
     * @var string
     */
    private $annotationName;

    /**
     * @var int
     */
    private $annotationIndex;

    /**
     * Create an Annotation Order Mapping entry.
     *
     * @param string $annotationName Name of the annotation bucket this maps for.
     * @param int $annotationIndex Index into that annotation bucket this entry represents.
     */
    public function __construct( $annotationName, $annotationIndex) {
        $this->annotationName = $annotationName;
        $this->annotationIndex = $annotationIndex;
    }

    /**
     * @return int
     */
    public function getAnnotationIndex()
    {
        return $this->annotationIndex;
    }

    /**
     * @return string
     */
    public function getAnnotationName()
    {
        return $this->annotationName;
    }

}