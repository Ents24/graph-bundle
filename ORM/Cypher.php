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
     * @var array With aliases
     */
    private $with;

    /**
     * @var array Where conditions
     */
    private $where;

    /**
     * @var array Where parameters
     */
    private $parameters;

    /**
     * @var array Merges, a multidimentional array of merges
     */
    private $merges;

    /**
     * @var array On create set clause for merges
     */
    private $onCreateSet;

    /**
     * @var array On update set clause for merges
     */
    private $onMatchSet;

    /**
     * @var array Create constraint/index statements.
     */
    private $index;

    /**
     * @var array Matches already declared to be reused.
     */
    private $variableMatches;

    /**
     * @var array Relationships statements
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
        $this->index = null;
        $this->matches = array();
        $this->merges = array();
        $this->where = array();
        $this->parameters = array();
        $this->onCreateSet = array();
        $this->onMatchSet = array();
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

        // 1. Create matches query string when applicable
        if (!empty($this->matches)) {
            $query = $this->joinMatches($this->matches);
        }

        // 3. Create merges query string when applicable
        if (!empty($this->onCreateSet)) {
            $query .= Helper::SPACE.$this->joinOnCreateOrUpdate($this->onCreateSet, 'ON CREATE');
        }

        // 4. Create merges query string when applicable
        if (!empty($this->onMatchSet)) {
            $query .= Helper::SPACE.$this->joinOnCreateOrUpdate($this->onMatchSet, 'ON MATCH');
        }

        // 5. Create where clauses
        if (!empty($this->where)) {
            $query .= Helper::SPACE.$this->joinWhere($this->where);
        }

        // 7. Add return statement when applicable
        if (null !== $this->return) {
            $query .= $this->return;
        }

        // 8. Constraints, special query overrides everything
        if (null !== $this->index) {
            $query = $this->createIndexStatement($this->index);
        }

        $this->query = $query;

        return $this;
    }

    /**
     * Add a merge statement.
     *
     * @param string Node variable alias
     * @param string Labels notation ("A:B" or ":A:B")
     */
    public function merge($alias = null, $label = null, array $props = array())
    {
        return $this->match($alias, $label, $props, false, 'MERGE');
    }

    /**
     * Add a match or optional match statement.
     *
     * @param string Node variable alias
     * @param string Labels notation ("A:B" or ":A:B")
     */
    public function match($alias = null, $label = null, array $props = array(), $optional = false, $keyword = 'MATCH')
    {
        // define which match type is to be performed
        // but also DONT include the "MATCH" keyword when not new subpattern
        // is declared. Cypher accepts MATCH (a), (b) OR MATCH (a) MATCH (b) as patterns
        $keyword = (true === $optional) ? 'OPTIONAL '.$keyword : $keyword;
        $label = Helper::normalizeLabelsToString($label);

        // an alias with this name could exist. In this case we assume this
        // is a reuse of the varialbe from a previous match or declration
        if ($this->aliasExists($alias)) {
            $this->matches[] = array(
                'keyword'   => $keyword,
                'alias'     => null, // $alias,
                'stmt'      => sprintf('(%s)', $alias),
                'optional'  => $optional,
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
            'keyword'   => $keyword,
            'alias'     => $alias,
            'stmt'      =>  sprintf('(%s%s%s)', $alias, $label, $props),
            'optional'  => $optional,
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
        return $this->match($alias, $label, $props, true, 'MATCH');
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
        // find if we work on matches or merges
        $lastIndex = $this->lastIndexOf($this->matches);

        // build a string of properties/values inside brackets
        $props = (null === Helper::newPropertiesPattern($props)) ? null : Helper::SPACE.Helper::newPropertiesPattern($props);

        // swith the relationship directions
        $dir = Helper::getDirections($direction);
        $type = (null !== $type) ? ':'.$type : null;

        // concat the relationships string with the previous last match statement and set back relationship to null
        $this->matches[$lastIndex]['stmt'] = $this->matches[$lastIndex]['stmt'].$dir[0]."[{$alias}{$type}{$props}]".$dir[1].$this->relationship['stmt'];
        $this->relationship = null;

        return $this;
    }

    /**
     * Add a "with" pattern statement.
     *
     * @param string Aliases
     * @return \Cypher
     */
    public function with($aliases)
    {
        $index = $this->matchesNumericIndex();
        $this->with[$index] = $aliases;

        return $this;
    }

    /**
     * Set a where parameter.
     *
     * @param string Parameter key
     * @param string Parameter value
     * @return \Cypher
     */
    public function setParameter($key, $value)
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /**
     * Add a where condition.
     *
     * @param string Where condition doctrine style
     * @return \Cypher
     */
    public function where($criterium, $keyword = null)
    {
        $this->where[] = array(
            'keyword' => $keyword,
            'criterium' => $criterium,
        );

        return $this;
    }

    /**
     * Add a where condition.
     *
     * @param string Where condition doctrine style
     * @return \Cypher
     */
    public function andWhere($criterium)
    {
        $keyword = (count($this->where) === 0) ? null : 'AND';
        return $this->where($criterium, $keyword);
    }

    /**
     * Add a or where condition.
     *
     * @param string Where condition doctrine style
     * @return \Cypher
     */
    public function orWhere($criterium)
    {
        return $this->where($criterium, 'OR');
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
     * Add a create an constraint statement.
     *
     * @param string Label string or name
     * @return \Cypher
     */
    public function createConstraint($label, $property = null)
    {
        $this->index = array(
            'keyword' => 'CREATE',
            'unique' => true,
            'label' => Helper::trimColons($label),
            'property' => $property,
        );

        return $this;
    }

    /**
     * Add a create an index statement.
     *
     * @param string Label string or name
     * @return \Cypher
     */
    public function createIndex($label, $property)
    {
        $this->index = array(
            'keyword' => 'CREATE',
            'unique' => false,
            'label' => Helper::trimColons($label),
            'property' => $property,
        );

        return $this;
    }

    /**
     * Drop an constraint statement.
     *
     * @param string Label string or name
     * @return \Cypher
     */
    public function dropConstraint($label, $property = null)
    {
        $this->index = array(
            'keyword' => 'DROP',
            'unique' => true,
            'label' => Helper::trimColons($label),
            'property' => $property,
        );

        return $this;
    }

    /**
     * Drop an index statement.
     *
     * @param string Label string or name
     * @return \Cypher
     */
    public function dropIndex($label, $property)
    {
        $this->index = array(
            'keyword' => 'DROP',
            'unique' => false,
            'label' => Helper::trimColons($label),
            'property' => $property,
        );

        return $this;
    }

    /**
     * Add on create set clause for merges.
     *
     * @param
     * @return object
     */
    public function onCreateSet($alias, array $properties)
    {
        foreach ($properties as $prop => $value) {
            $prop = Helper::addAlias($alias, $prop);
            $this->onCreateSet[] = Helper::newPropertyValueEquality($prop, $value);
        }

        return $this;
    }

    /**
     * Add on update set clause for merges.
     *
     * @param
     * @return object
     */
    public function onMatchSet($alias, array $properties)
    {
        foreach ($properties as $prop => $value) {
            $prop = Helper::addAlias($alias, $prop);
            $this->onMatchSet[] = Helper::newPropertyValueEquality($prop, $value);
        }

        return $this;
    }

    /**
     * Joins matches string that are groupped together depending
     * taking into account joined or new patterns as well.
     *
     * @return string
     */
    private function joinMatches(array $clauses)
    {
        // matches are separated with commas only
        // when a new pattern is needed at a precise index, otherwise
        // matches are chained because they belong to the same pattern
        $list = array();
        $noCommasAt = null;

        foreach ($clauses as $index => $match) {
            $keyword = $match['keyword'];

            // if a new pattern was declared at this position
            if (isset($this->newPatterns[$index])) {
                $list[] = array(Helper::SPACE, $keyword.Helper::SPACE.$match['stmt']);
            } else {
                $list[] = array(Helper::COMMA, $match['stmt']);
            }

            if (isset($this->with[$index])) {
                $noCommasAt = $index + 2;
                $list[] = array(Helper::SPACE, 'WITH '.$this->with[$index]);
            }
        }

        // modify next statment after the "WITH" (no comma in syntax)
        if (null !== $noCommasAt && isset($list[$noCommasAt])) {
            $list[$noCommasAt][0] = Helper::SPACE;
        }

        return $clauses[0]['keyword'].Helper::SPACE.Helper::subvalImplode($list);
    }

    /**
     * Joins where conditions together.
     *
     * @param  array  Where conditions
     * @return string Where statement
     */
    public function joinWhere(array $clauses)
    {
        $list = array();

        foreach ($clauses as $index => $where) {
            $list[] = array(Helper::SPACE.$where['keyword'].Helper::SPACE, $where['criterium']);
        }

        return 'WHERE'.Helper::SPACE.Helper::subvalImplode($list);
    }

    /**
     * Joins matches string that are groupped together depending
     * taking into account joined or new patterns as well.
     *
     * @return string
     */
    private function joinOnCreateOrUpdate(array $clauses, $onCreateOrUpdateKeyword)
    {
        return $onCreateOrUpdateKeyword.' SET'.Helper::SPACE.Helper::joinWithCommas($clauses);
    }

    /**
     * Joins matches string that are groupped together depending
     * taking into account joined or new patterns as well.
     *
     * @return string
     */
    private function createIndexStatement(array $index)
    {
        if (true === $index['unique']) {
            return sprintf('%s CONSTRAINT ON (a:%s) ASSERT a.%s IS UNIQUE', $index['keyword'], $index['label'], $index['property']);
        } else {
            // normal index creation
            return sprintf('%s INDEX ON :%s(%s)', $index['keyword'], $index['label'], $index['property']);
        }
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
     * Get last alias index that was declared.
     *
     * @return string
     */
    private function lastAlias()
    {
        $keys = array_keys($this->aliases);
        $key = end($keys);

        return $this->aliases[$key];
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

    /**
     * Return the current matches numeric index.
     *
     * @return integer
     */
    private function matchesNumericIndex()
    {
        return count($this->matches) - 1;
    }
}
