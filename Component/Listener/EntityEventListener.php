<?php

namespace Adadgio\GraphBundle\Component\Listener;

use Doctrine\Common\Inflector;
use Doctrine\ORM\Event\LifecycleEventArgs;

use Adadgio\GraphBundle\ORM\Cypher;
use Adadgio\GraphBundle\Annotation\GraphAnnotationReader;

class EntityEventListener
{
    /**
     * @var object \Adadgio\GraphBundle\ORM\Neo4jManager
     */
    private $neo4j;

    /**
     * Service constructor.
     *
     * @param object \Adadgio\GraphBundle\Annotation\EntityAnnotationHandler
     * @return void
     */
    public function __construct(\Adadgio\GraphBundle\ORM\Neo4jManager $neo4j)
    {
        $this->neo4j = $neo4j;
    }

    /**
     * Triggered on entity post update event.
     *
     * @param \LifecycleEventArgs
     * @return void
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->onUpdateOrPersist($entity);
    }
    
    /**
     * Triggered on entity post update event.
     *
     * @param \LifecycleEventArgs
     * @return void
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->onUpdateOrPersist($entity);
    }

    /**
     *
     * @param  object A doctrine entity
     * @return
     */
    private function onUpdateOrPersist($entity)
    {
        $annotations = GraphAnnotationReader::getAnnotations($entity);

        // foreach annotation, create a simple query with the query builder
        $properties = array();
        foreach ($annotations as $field => $annotation) {
            // annotation is an instance of Adadgio\GraphBundle\Annotation\Graph

            $fieldName = $annotation->getFieldname();

            $get = self::guessGetter($fieldName);
            $properties[$fieldName] = $entity->$get();
        }

        debug($properties, true);

        // build a merge query to update the entity properties in the graph
        // $cypher = (new CYpher())
        //     ->match()
        //     ;
    }

    /**
     * Get entity getter.
     *
     * @param  string Entity field name
     * @return string Entity getter
     */
    private static function guessGetter($fieldName)
    {
        return 'get'.Inflector\Inflector::classify($fieldName);
    }
}
