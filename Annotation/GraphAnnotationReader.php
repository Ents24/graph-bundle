<?php

namespace Adadgio\GraphBundle\Annotation;

use Doctrine\Common\Annotations\AnnotationReader;

class GraphAnnotationReader
{
    public static function getClassAnnotations($entity)
    {
        $classAnnotation = null;
        $reader = new AnnotationReader();

        $reflectionClass = new \ReflectionClass($entity);
        $annotationsFound = $reader->getClassAnnotations($reflectionClass);

        foreach ($annotationsFound as $i => $annotation) {
            if ($annotation instanceof \Adadgio\GraphBundle\Annotation\Graph) {
                $classAnnotation = $annotation;
                break;
            }
        }

        // by default the label is the class name
        // whenever and if no class annotations is found
        if (null === $classAnnotation->getProperty('labels')) {
            $classShortName = $reflectionClass->getShortName();
            $classAnnotation = new \Adadgio\GraphBundle\Annotation\Graph(array('labels' => array($classShortName)));
        }

        return $classAnnotation;
    }

    public static function getPropertyAnnotations($entity)
    {
        $annotations = array();
        $reader = new AnnotationReader();

        $reflectionClass = new \ReflectionClass($entity);
        $reflectionProperties = $reflectionClass->getProperties();

        foreach ($reflectionProperties as $reflectionProperty) {
            $fieldName = $reflectionProperty->getName();
            $annotationsFound = $reader->getPropertyAnnotations($reflectionProperty);

            $presetValues = array('type' => false, 'name' => false);

            foreach ($annotationsFound as $i => $annotation) {

                // also read ORM annotations to pick up property/field type and name automatically...
                if ($annotation instanceof \Doctrine\ORM\Mapping\Column) {
                    if (isset($annotation->type)) { $presetValues['type'] = $annotation->type; }
                    if (isset($annotation->name)) { $presetValues['name'] = $annotation->name; }
                }

                if ($annotation instanceof \Adadgio\GraphBundle\Annotation\Graph) {
                    $annotations[$fieldName] = $annotation;

                    if ($presetValues['type'] && !$annotation->hasProperty('type')) { $annotations[$fieldName]->setProperty('type', $presetValues['type']); }
                    if ($presetValues['name'] && !$annotation->hasProperty('name')) { $annotations[$fieldName]->setProperty('name', $presetValues['name']); }

                    // register the field name as annotation object property (we will need it later)
                    $annotations[$fieldName]->setFieldName($fieldName);

                    break; // only first annotation of type ...\Graph is taken into account
                }
            }
        }

        return $annotations;
    }
}
