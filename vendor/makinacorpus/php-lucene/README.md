# Minimalistic, feature-rich, PHP Lucene syntax query builder

This is a very small piece of API that brings a query builder for building
Lucene queries; use cases are numerous, the two most obvious ones being
Elastic Search and Apache SolR.

# Examples

```php
use MakinaCorpus\Lucene\Query;

$query = new Query();

$query
    ->createTermCollection(Query::OP_OR)
    ->add("foo")
    ->add("bar")
;

$query
    ->createDateRange()
    ->setInclusive()
    ->setRange('1983-03-22', new \DateTime())
;

$query
    ->matchTerm('some_field', 'some value', null, 0.8)
    ->matchTerm('other_field', 'some_other_value', 1.3)
;
```

Should give you the following query:

```lucene
(
    (foo OR bar)
    AND my_date_field:["1983\-03\-22T00\:00\:00\+0000" TO "2016\-06\-29T13\:10\:20\+0000"]
    AND some_field:"some value"~0.8
    AND other_field:some_other_value^1.3
)
```

# Current status

This API is in use in various projects for now almost 10 years, although this
version being a full refactor (mostly classes being renamed) that now runs in
production for 6 months.

It's probably not bug free, and lacks some testing, yet until now it works.
