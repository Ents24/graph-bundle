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
            ->match('d', 'Document', array('id' => 5))
            ->newPattern()
            ->optionalMatch('p', 'Profession')
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

        $this->queries[] = (new Cypher())
            ->merge('a', 'City', array('id' => 13))
            ->getQuery();

        $this->queries[] = (new Cypher())
            ->merge('a', 'City', array('id' => 25))
            ->onCreateSet('a', array('id' => 25, 'name' => 'Test'))
            ->onMatchSet('a', array('name' => "L'éléphant"))
            ->getQuery();

        $this->queries[] = (new Cypher())
            ->merge('a', 'Document', array('id' => 1))
                ->relatedWith('b', 'Page', array('id' => 3))
                ->by('r', 'IS_RELATED')
            ->onCreateSet('a', array('id' => 1, 'name' => 'My document'))
            ->onMatchSet('a', array('name' => 'My document'))
            ->onCreateSet('b', array('id' => 3, 'name' => 'My page'))
            ->onMatchSet('b', array('name' => 'My page'))
            ->getQuery();

        $this->queries[] = (new Cypher())
            ->merge('a', 'Document', array('id' => 1))
                ->relatedWith('b', 'Page', array('id' => 3))
                ->by('r', 'IS_RELATED', array(), '->')
            ->onCreateSet('a', array('id' => 1, 'name' => 'My document'))
            ->onMatchSet('a', array('name' => 'My document'))
            ->onCreateSet('b', array('id' => 3, 'name' => 'My page'))
            ->onMatchSet('b', array('name' => 'My page'))
            ->onCreateSet('r', array('test' => 'yo'))
            ->onMatchSet('r', array('test' => 'yo'))
            ->andReturn('a, b')
            ->getQuery();

        // indexes and queries constraints
        $this->queries[] = (new Cypher())
            ->createIndex('Person', 'id')
            ->getQuery();
        $this->queries[] = (new Cypher())
            ->createConstraint('City', 'name')
            ->getQuery();
        $this->queries[] = (new Cypher())
            ->dropIndex('Person', 'id')
            ->getQuery();
        $this->queries[] = (new Cypher())
            ->dropConstraint('City', 'name')
            ->getQuery();

        $this->queries[] = (new Cypher())
            ->match('d', 'Document')
            ->match('s', 'Specialty')
            ->newPattern()
            ->optionalMatch('d')
                ->relatedWith('s')
                ->by('r', 'AD_TARGETS', array(), '<-')
            ->getQuery();

        $this->queries[] = (new Cypher())
            ->match('d', 'Document')
            ->match('s', 'Specialty')
            ->with('d, s')
                ->match('d')
                ->relatedWith('s')
                ->by('r', 'AD_TARGETS', array(), '<-')
                ->where('a.name = :truc')
                ->setParameter('truc', 'Ben Afflict')
            ->getQuery();

        $this->queries[] = (new Cypher())
            ->match('d', 'Document')
            ->match('s', 'Specialty')
            ->with('d, s')
                ->match('d')
                ->relatedWith('s')
                ->by('r', 'AD_TARGETS', array(), '<-')
                ->andWhere('a.name = :truc')
                ->andWhere('s.id = :id')
                ->setParameter('truc', 'Ben Afflict')
                ->setParameter('id', 15)
            ->getQuery();

        $this->results = array(
            'MATCH (d:Document {id: 5}), (p:Profession)',
            'MATCH (d:Document {id: 5}) OPTIONAL MATCH (p:Profession)',
            'MATCH (a:Document {id: 5}), (b:Test)-[r:AD_TARGETS {max: 3}]->(a), (a)',
            'MATCH (a:Document {id: 5}), (b:Test)-[r:AD_TARGETS {max: 3}]->(a) MATCH (a)',
            'MATCH (a:Countries {id: 5}) MATCH (b:City:Town)-[r:IS_IN {max: 3}]->(a) MATCH (a)',
            'MERGE (a:City {id: 13})',
            "MERGE (a:City {id: 25}) ON CREATE SET a.id = 25, a.name = 'Test' ON MATCH SET a.name = 'L\'éléphant'",
            "MERGE (a:Document {id: 1})-[r:IS_RELATED]-(b:Page {id: 3}) ON CREATE SET a.id = 1, a.name = 'My document', b.id = 3, b.name = 'My page' ON MATCH SET a.name = 'My document', b.name = 'My page'",
            "MERGE (a:Document {id: 1})-[r:IS_RELATED]->(b:Page {id: 3}) ON CREATE SET a.id = 1, a.name = 'My document', b.id = 3, b.name = 'My page', r.test = 'yo' ON MATCH SET a.name = 'My document', b.name = 'My page', r.test = 'yo' RETURN a, b",
            'CREATE INDEX ON :Person(id)',
            'CREATE CONSTRAINT ON (a:City) ASSERT a.name IS UNIQUE',
            'DROP INDEX ON :Person(id)',
            'DROP CONSTRAINT ON (a:City) ASSERT a.name IS UNIQUE',
            'MATCH (d:Document), (s:Specialty) OPTIONAL MATCH (d)<-[r:AD_TARGETS]-(s)',
            'MATCH (d:Document), (s:Specialty) WITH d, s (d)<-[r:AD_TARGETS]-(s) WHERE a.name = :truc',
            'MATCH (d:Document), (s:Specialty) WITH d, s (d)<-[r:AD_TARGETS]-(s) WHERE a.name = :truc AND s.id = :id',
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
