<?php

namespace Adadgio\GraphBundle\ORM;

use Adadgio\GraphBundle\ORM\Helper\Helper;

class Cypher
{
    /**
     * @var string Alphabet for aliases
     */
    const ALPHABET = 'abcdefghijklmnopqrstuvwxyz';

    /**
     * @var string Roman alphabet
     */
    private static $alphabet;

    /**
     * @var integer Pointer of how many aliases of the alphabet were used.
     */
    private $aliases = array();

    /**
     * @var string The final cypher query
     */
    private $query;

    /**
     * @var array Matches, a multidimentional array of matches
     * that are actually groupped together in sets of matches
     */
    private $matches;

    /**
     * @var array Matches already declared to be reused.
     */
    private $variableMatches;

    /**
     *
     */
    private $relationships;

    /**
     * @var array
     */
    private $newPatterns = array();

    /**
     * @var string Return statement
     */
    private $return;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        self::$alphabet = static::ALPHABET;

        $this->return = null;
        $this->matches = array();
        $this->aliases = array();
        $this->variableMatches = array();
        $this->relationships = array();
    }

    /**
     * Get query.
     *
     * @return string
     */
    public function getQuery()
    {
        $this->buildQuery();

        return $this->query;
    }

    /**
     * Builds the final query.
     *
     * @return \Cypher
     */
    public function buildQuery()
    {
        $query = null;

        // 1. Create matches query string
        $query = $this->joinMatches($this->matches);

        if (null !== $this->return) {
            $query .= $this->return;
        }

        $this->query = $query;

        return $this;
    }

    /**
     * Add a match or optional match statement.
     *
     * @param string Node variable alias
     * @param string Labels notation ("A:B" or ":A:B")
     */
    public function match($alias = null, $label = null, array $props = array(), $optional = false)
    {
        // define which match type is to be performed
        // but also DONT include the "MATCH" keyword when not new subpattern
        // is declared. Cypher accepts MATCH (a), (b) OR MATCH (a) MATCH (b) as patterns
        //$match = (true === $optional) ? 'OPTIONAL MATCH' : '';

        // an alias with this name could exist. In this case we assume this
        // is a reuse of the varialbe from a previous match or declration
        if ($this->aliasExists($alias)) {
            $this->matches[] = array(
                'alias' => null, // $alias,
                'stmt'  => sprintf('(%s)', $alias),
                'optional' => $optional,
            );
            return $this;

        } else {
            // register the alias
            $this->registerAlias($alias);
        }

        // set the formatted label string
        $label = (null === $label) ? null : Helper::COLON.Helper::trimColons($label);

        // build a string of properties/values inside brackets
        $props = (null === Helper::newPropertiesPattern($props)) ? null : Helper::SPACE.Helper::newPropertiesPattern($props);

        // any previous relationship must safely be marked as "false" (dont remove it not to break indexes)
        $this->matches[] = array(
            'alias' => $alias,
            'stmt'  =>  sprintf('(%s%s%s)', $alias, $label, $props),
            'optional' => $optional,
        );

        return $this;
    }

    /**
     * Add an optional match statement
     *
     * @param string Node variable alias
     * @param string Labels notation ("A:B" or ":A:B")
     */
    public function optionalMatch($alias = null, $label = null, array $props = array())
    {
        return $this->match($alias, $label, $props, true);
    }

    /**
     * Add a target node to select through a relationship.
     *
     * @return \Cypher
     */
    public function relatedWith($alias = null, $label = null, array $props = array(), $direction = null)
    {
        // register the alias
        $this->registerAlias($alias);
        // $lastAlias = $this->lastAlias(); // equivalent of previousAlias($alias)
        // debug($lastAlias);

        // set the formatted label string
        $label = (null === $label) ? null : Helper::COLON.Helper::trimColons($label);

        // build a string of properties/values inside brackets
        $props = (null === Helper::newPropertiesPattern($props)) ? null : Helper::SPACE.Helper::newPropertiesPattern($props);

        $this->relationship = array(
            'alias' => $alias,
            'stmt'  => sprintf('(%s%s%s)', $alias, $label, $props),
        );

        return $this;
    }

    /**
     * Add the criterias to state which type or relationships.
     *
     * @param
     * @param
     * @param
     * @return \Cypher
     */
    public function by($alias = null, $type = null, array $props = array(), $direction = '-')
    {
        // register the alias
        $lastMatchIndex = $this->lastIndexOf($this->matches);

        // build a string of properties/values inside brackets
        $props = (null === Helper::newPropertiesPattern($props)) ? null : Helper::SPACE.Helper::newPropertiesPattern($props);

        // swith the relationship directions
        $dir = Helper::getDirections($direction);
        $type = (null !== $type) ? ':'.$type : null;

        // concat the relationships string with the previous last match statement and set back relationship to null
        $this->matches[$lastMatchIndex]['stmt'] = $this->matches[$lastMatchIndex]['stmt'].$dir[0]."[{$alias}{$type}{$props}]".$dir[1].$this->relationship['stmt'];
        $this->relationship = null;

        return $this;
    }

    /**
     * Set the beginning of a new match pattern, which will have
     * the effect of not separating the next match with a comma but a space.
     *
     * @return \Cypher
     */
    public function newPattern()
    {
        $index = $this->lastIndexOf($this->matches);
        $this->newPatterns[$index + 1] = $index;

        return $this;
    }

    /**
     * Joins matches string that are groupped together depending
     * taking into account joined or new patterns as well.
     *
     * @return string
     */
    private function joinMatches(array $matches)
    {
        // matches are separated with commas only
        // when a new pattern is needed at a precise index, otherwise
        // matches are chained because they belong to the same pattern
        $list = array();

        foreach ($matches as $index => $match) {
            $word = (true === $match['optional']) ? 'OPTIONAL MATCH' : 'MATCH';

            // if a new pattern was declared at this position
            if (isset($this->newPatterns[$index])) {
                $list[] = array(Helper::SPACE, $word.Helper::SPACE.$match['stmt']);
            } else {
                $list[] = array(Helper::COMMA, $match['stmt']);
            }
        }
        
        return 'MATCH'.Helper::SPACE.Helper::subvalImplode($list);
    }

    /**
     * Allows the query to actually return nodes or results.
     *
     * @return object \Cypher
     */
    public function andReturn($return)
    {
        $this->return = Helper::SPACE.Helper::addReturnStatement($return);

        return $this;
    }

    /**
     * Add node labels selection to the return statement (labels(a)).
     *
     * @return object \Cypher
     */
    public function withLabels()
    {
        foreach($this->aliases as $alias) {
            $stmt = Helper::SPACE.sprintf('labels(%s)', $alias);
            $this->return .= ', '.$stmt;
        }

        return $this;
    }

    /**
     * Add node real id selection to the return statement (id(a)).
     *
     * @return object \Cypher
     */
    public function withId()
    {
        foreach($this->aliases as $alias) {
            $stmt = Helper::SPACE.sprintf('id(%s)', $alias);
            $this->return .= ', '.$stmt;
        }

        return $this;
    }

    /**
     * Get last index of any array.
     *
     * @param  array
     * @return integer
     */
    private function lastIndexOf(array $array)
    {
        $keys = array_keys($array);
        return end($keys);
    }

    /**
     * Get last alias index that was declared.
     *
     * @return string
     */
    private function lastAliasIndex()
    {
        $keys = array_keys($this->aliases);
        return end($keys);
    }

    /**
     * Get last alias that was declared.
     *
     * @return string
     */
    private function aliasExists($alias)
    {
        // warning, might return 0 (position of value in array),
        // in this case it still exists and must return true
        $key = array_search($alias, $this->aliases);

        return (false === $key) ? false : true;
    }

    /**
     * Search the alias and return the previous one.
     *
     * @param
     * @return string
     */
    private function previousAlias($alias)
    {
        $key = array_search($alias, $this->aliases, true);

        return (isset($this->aliases[$key-1])) ? $this->aliases[$key-1] : false;
    }

    /**
     * Search the alias and return the previous one.
     *
     * @param
     * @return string
     */
    private function previousPreviousAlias($alias)
    {
        $key = array_search($alias, $this->aliases, true);

        return (isset($this->aliases[$key-2])) ? $this->aliases[$key-2] : false;
    }

    /**
     * Add the alias to the registered list of aliases.
     *
     * @param  string Alias
     * @return \Cypher
     */
    private function registerAlias($alias)
    {
        $this->aliases[] = $alias;

        return $this;
    }
}
