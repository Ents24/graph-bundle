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
     * Executes a neo4j query to sync the entity properties in the graph.
     *
     * @param  object A doctrine entity
     * @return
     */
    private function onUpdateOrPersist($entity)
    {
        $classAnnotations = GraphAnnotationReader::getClassAnnotations($entity);
        $propertyAnnotations = GraphAnnotationReader::getPropertyAnnotations($entity);
        
        // defined the entity labels in the graph, properties and merge by criteria
        $properties = array();
        $labels = $classAnnotations->getProperty('labels');
        $mergeBy = $classAnnotations->getProperty('mergeBy');

        // foreach annotation, create a simple query with the query builder to set the properties
        foreach ($propertyAnnotations as $field => $annotation) {
            // annotation is an instance of Adadgio\GraphBundle\Annotation\Graph

            $fieldName = $annotation->getFieldname();

            $get = self::guessGetter($fieldName);
            $properties[$fieldName] = $entity->$get();
        }

        $mergeByGetter = self::guessGetter($mergeBy);

        // get the entities to sync config
        $cypher = (new Cypher())
            ->merge('a', $labels, array($mergeBy => $entity->$mergeByGetter())) // depends on annotation, usualy getId()...
            ->onCreateSet('a', $properties)
            ->onMatchSet('a', $properties);

        $this->neo4j->transaction($cypher);
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
