<?php

namespace Adadgio\GraphBundle\Tests\ORM;

use Adadgio\GraphBundle\ORM\Neo4jManager;

class Neo4jManagerTest extends \PHPUnit_Framework_TestCase
{
    private $queries = array();
    private $results = array();

    public function configure()
    {
        $this->queries = array();
        $this->results = array();
    }

    public function testManager()
    {
        $this->configure();

        foreach ($this->queries as $i => $query) {

        }
    }
}
