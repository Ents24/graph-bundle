Cypher Query Builder for PHP
====

Forked from adadgio/graph-bundle.

I needed a Cypher query builder and liked this one, but without all the Symfony and related gubbins.

## Install

Install with composer:

`composer require ents24/neo4j-query-builder-php`

# Usage Examples

```php
use Adadgio\GraphBundle\ORM\Cypher;

$cypher = (new Cypher())
    ->match('a', 'Document')
    ->match('b', 'Page')
    ->andReturn('a.id, a.name AS `a.test`, b.id, b.name')->withLabels()->withId();

// is the same as
$cypher = (new Cypher())
    ->match('a', 'Document')
    ->match('b', 'Page')
    ->andReturn('a.id, a.name AS `a.test`, b.id, b.name, labels(a), labels(b), id(a), id(b)');

// trying queries with relationships constraints (and passing string to manager instead of object)
$queryString = (new Cypher())
    ->match('a', 'Countries', array('id' => 5))
    ->newPattern()
    ->match('b', ':City:Town')
        ->relatedWith('a')
        ->by('r', 'IS_IN', array('max' => 3), '->')
    ->newPattern()
    ->match('a')
    ->getQuery();

$cypherA = (new Cypher())
    ->match('a', 'Document', array('id' => 389))
    ->set('a', array('name' => 'The good, the bad and the ugly'));

$cypherB = (new Cypher())
    ->match('a', 'Document', array('id' => 390))
    ->set('a', array('name' => 'The good, the bad and the ugly'));
```
