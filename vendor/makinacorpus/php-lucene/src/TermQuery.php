<?php

namespace MakinaCorpus\Lucene;

/**
 * Represent a simple user term or phrase
 */
class TermQuery extends AbstractFuzzyQuery
{
    /**
     * Term
     */
    protected $term = null;

    /**
     * Set term
     *
     * @param string $term
     *
     * @return $this
     */
    public function setValue($term)
    {
        $this->term = trim((string)$term);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return null === $this->term;
    }

    /**
     * {@inheritdoc}
     */
    protected function toRawString()
    {
        return self::escapeToken($this->term);
    }
}
