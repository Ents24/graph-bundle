<?php

namespace Adadgio\GraphBundle\Tests\ORM;

use Adadgio\GraphBundle\ORM\Cypher;

class CypherTest extends \PHPUnit_Framework_TestCase
{
    private $queries = array();
    private $results = array();

    public function configure()
    {
        $this->queries[] = (new Cypher())
            ->match('d', 'Document', array('id' => 5))
            ->match('p', 'Profession')
            ->getQuery();

        $this->queries[] = (new Cypher())
            ->match('a', 'Document', array('id' => 5))
            ->match('b', 'Test')
                ->relatedWith('a')
                ->by('r', 'AD_TARGETS', array('max' => 3), '->')
            ->match('a')
            ->getQuery();

        $this->queries[] = (new Cypher())
            ->match('a', 'Document', array('id' => 5))
            ->match('b', 'Test')
                ->relatedWith('a')
                ->by('r', 'AD_TARGETS', array('max' => 3), '->')
            ->newPattern()
            ->match('a')
            ->getQuery();
            
        $this->queries[] = (new Cypher())
            ->match('a', 'Countries', array('id' => 5))
            ->newPattern()
            ->match('b', ':City:Town')
                ->relatedWith('a')
                ->by('r', 'IS_IN', array('max' => 3), '->')
            ->newPattern()
            ->match('a')
            ->getQuery();

        $this->results = array(
            'MATCH (d:Document {id: 5}), (p:Profession)',
            'MATCH (a:Document {id: 5}), (b:Test)-[r:AD_TARGETS {max: 3}]->(a), (a)',
            'MATCH (a:Document {id: 5}), (b:Test)-[r:AD_TARGETS {max: 3}]->(a) MATCH (a)',
            'MATCH (a:Countries {id: 5}) MATCH (b:City:Town)-[r:IS_IN {max: 3}]->(a) MATCH (a)',
        );
    }

    public function testCypherQueries()
    {
        $this->configure();

        // assert that all the built queries match the expected results
        foreach ($this->queries as $i => $query) {
            $this->assertEquals($query, $this->results[$i]);
        }
    }
}
