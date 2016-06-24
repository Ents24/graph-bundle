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
