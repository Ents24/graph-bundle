AdadgioGraphBundle
====

Awesome helpers to use a Neo4j database inside a Sf project. This does not replace doctrine entities but is aimed at syncing some entities
with a graph database or making queries, retriveing nodes and relationships. It is *not a full ORM replacement* !

## Install

Install with composer.

`composer require adadgio/graph-bundle`

Make the following change to your `AppKernel.php` file to the registered bundles array.

```
new Adadgio\GraphBundle\AdadgioGraphBundle(),
```

## Components

## Queries, query builder and manager

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

$manager = $this
    ->get('adadgio_graph.neo4j_manager')
    ->cypher($cypher);

// all results
print_r(manager->getResult());

// result set for "a" nodes
print_r(manager->getResult('a'));

// result set for "a" nodes
print_r(manager->getResult('b'));

```
