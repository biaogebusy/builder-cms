<?php

namespace MakinaCorpus\Lucene;

/**
 * Represent a user term collection
 */
class TermCollectionQuery extends CollectionQuery
{
    /**
     * Add a term
     *
     * @param string|TermQuery $element
     *
     * @return $this
     */
    public function add($element)
    {
        if ($element instanceof AbstractQuery) {
            parent::add($element);
        } else {
            parent::add(
                (new TermQuery())
                    ->setValue($element)
            );
        }

        return $this;
    }

    /**
     * Add a list of terms
     *
     * @param string[]|TermQuery[] $terms
     *
     * @return $this
     */
    public function addAll(array $terms)
    {
        foreach ($terms as $term) {
            $this->add($term);
        }

        return $this;
    }
}
