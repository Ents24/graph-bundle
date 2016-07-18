<?php

namespace Adadgio\GraphBundle\ORM;

use Everyman\Neo4j\Client as Neo4jClient;
use Everyman\Neo4j\Cypher\Query as EverymanQuery;

class Neo4jManager
{
    /**
     * @var array Bundle configuration nodes.
     */
    private $config;

    /**
     * @var object \Everyman\Neo4j\Client
     */
    private $client;

    /**
     * @var array Cypher query results
     */
    private $result;

    /**
     * Service constructor.
     *
     * @param array Bundle configuration nodes.
     * @return void
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = new Neo4jClient($this->config['host'], $this->config['port']);

        // use https if config requires it
        $transport = $this->client->getTransport();
        if ($this->config['https'] === true) {
            $transport->useHttps();
        }

        // use http basic authentication when user is set
        if (!empty($this->config['user'])) {
            $transport->setAuth($this->config['user'], $this->config['pass']);
        }
    }

    /**
     * Return query parsed and formatted result.
     *
     * @return array Query result
     */
    public function getResult($alias = null)
    {
        if (null === $alias) {
            return $this->result;
        } else {
            return isset($this->result[$alias]) ? $this->result[$alias] : array();
        }
    }

    /**
     * Perform a simple cypher query and can return results.
     *
     * @param  string A cypher query string
     * @return object \Neo4jManager
     */
    public function cypher($query, array $params = array())
    {
        // the input can be a AdadgioGraphBundle\..\Cypher object or a native query string
        $nativeQuery = ($query instanceof Cypher) ? $query->getQuery() : $query;

        $cypher = new EverymanQuery($this->client, $nativeQuery, $params);
        $this->result = $this->parse($cypher->getResultSet());

        return $this;
    }

    /**
     * Perform multiple queries via cypher transation(s).
     * Note that a transaction does not enabled returning results.
     *
     * @param  array Cypher querie(s) string(s)
     * @return object \Neo4jManager
     */
    public function transaction(array $queries)
    {
        $transaction = $this->client->beginTransaction();

        foreach ($queries as $params => $query) {
            $transaction->addStatements(
                new EverymanQuery($this->client, $query, $params)
            );
        }

        $result = $transaction->commit();

        return $this;
    }
    
    /**
     * Analyse a cypher query result set. There are two types of results
     * to analyze, node results of field results because "MATCH a" or
     * "MATCH a.id" end up with different types of results)
     *
     * @param  object \Everyman\Neo4j\Query\ResultSet
     * @return array  Formatted result(s)
     */
    public function parse(\Everyman\Neo4j\Query\ResultSet $resultSet)
    {
        $result = array();
        $columnSets = $this->getColumnSets($resultSet->getColumns());

        foreach ($columnSets as $setName => $fields) {
            $result[$setName] = array();

            foreach ($resultSet as $i => $row) {
                $result[$setName][$i] = array();

                foreach ($row as $property => $objectOrScalar) {
                    $info = $this->extractFieldInfo($property);

                    if ($info['set'] !== $setName) {
                        continue;
                    }

                    if ($objectOrScalar instanceof \Everyman\Neo4j\Node) {

                        foreach ($objectOrScalar->getProperties() as $prop => $value) {
                            $result[$setName][$i][$prop] = $value;
                        }

                    } else {
                        // handle scalar values
                        $result[$setName][$i][$info['field']] = $this->objectOrScalarToValue($objectOrScalar);
                    }
                }

            }
        }

        return $result;
    }

    /**
     * Returns a scalar value of a row if applicable otherwise loop through the object
     * values and sets the object property as an array containing those values (ex labels)
     *
     * @param  mixed Scalar or everyman \Row object
     * @return mixed Scalar or array object final value
     */
    private function objectOrScalarToValue($objectOrScalar)
    {
        if ($objectOrScalar instanceof \Everyman\Neo4j\Query\Row) {

            $array = array();
            foreach ($objectOrScalar as $prop => $val) {
                $array[$prop] = $val;
            }

            return $array;

        } else {

            if ($objectOrScalar instanceof \Everyman\Neo4j\Query\Row) {
                return $this->objectOrScalarToValue($objectOrScalar);
            } else {
                return $objectOrScalar;
            }
        }
    }

    /**
     * Return description of columns selected (sets) and their
     * respective fields selection if applicable.
     *
     * @param  array Columns as given by everyman result set object
     * @return array Set(s) column map description
     */
    private function getColumnSets(array $columns)
    {
        $sets = array();
        $typeA = '~([a-z0-9_]+)\.([a-z0-9_]+)~i'; // "a.id"
        $typeB = '~([a-z0-9_]+)\(([a-z0-9_]+)\)~'; // "labels(a)"

        foreach ($columns as $col) {

            $info = $this->extractFieldInfo($col);

            $setName = $info['set'];
            $fieldName = $info['field'];

            if (!isset($sets[$setName])) {
                $sets[$setName] = array();
            }

            $sets[$setName][] = $fieldName;
        }

        return $sets;
    }

    /**
     * Guesses to which node set a field belongs to and return both the node set
     * and the real field name "a.id" belgons to "a", and field is "id", and "labels(a)" or
     * "id(a)" belong to set "a" and fields are "$labels" and "$id" (real reserved neo4j properties)
     *
     * @param
     * @return
     */
    private function extractFieldInfo($field)
    {
        $typeA = '~([a-z0-9_]+)\.([a-z0-9_]+)~i'; // "a.id"
        $typeB = '~([a-z0-9_]+)\(([a-z0-9_]+)\)~'; // "labels(a)"

        if (preg_match($typeA, $field, $m)) {

            // "a.id" selection type
            $node = $m[1];
            $field = $m[2];

            return array('set' => $node, 'field' => $field);

        } else if (preg_match($typeB, $field, $m)) {

            // "labels(a)" selection type
            $node = $m[2];
            $field = $m[1];

            return array('set' => $node, 'field' => '$'.$field);

        } else {
            // "a" selection type
            $node = $field;
            return array('set' => $node, 'field' => $node);
        }
    }
}
