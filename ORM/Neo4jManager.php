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
    public function getResult()
    {
        return $this->result;
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
        foreach ($resultSet as $i => $row) {

            if ($row instanceof \Everyman\Neo4j\Query\Row) {
                $result[$i] = array();
                // depending on the return statement rows can be everyman \Node object or
                // simple scalar results that depend on selected fields from the return statement
                // information about what is selected is also available in the \Row "columns" property

                foreach ($row as $property => $objectOrScalar) {

                    // each row is iterable, but when nodes are selected, value is a \Node object and
                    // when fields are selected in the return statement, then props and values are scalar
                    if ($objectOrScalar instanceof \Everyman\Neo4j\Node) {
                        // corresponds to "RETURN a", and prop is "a", value is a \Node object
                        // $result[$prop] = $value;
                        // so loop through all node properties
                        //$result[$i][$prop] = $this->objectOrScalarToValue($objectOrScalar);
                        foreach ($objectOrScalar->getProperties() as $prop => $value) {

                            // in case you select labels, value can still be a \Row object
                            $result[$i][$prop] = $this->objectOrScalarToValue($value);

                        }

                    } else {
                        // corresponds to a "RETRURN a.id, a.name...", prop is "a.id", value is the scalar value
                        $result[$i][$property] = $this->objectOrScalarToValue($objectOrScalar);
                        // if ($objectOrScalar instanceof \Everyman\Neo4j\Query\Row) {
                        //     echo '<pre>';
                        //     print_r($objectOrScalar);
                        //     echo '</pre>';
                        // } else {
                        //
                        // }


                    }
                }
            }

        }

        return array_values($result);
    }
    
    /**
     *
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
}
