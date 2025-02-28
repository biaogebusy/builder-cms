<?php

namespace MakinaCorpus\Lucene;

class CollectionQuery extends AbstractQuery implements
    \IteratorAggregate,
    \Countable
{
    /**
     * @var AbstractQuery[]
     */
    protected $elements = array();

    /**
     * Can be Query::OP_AND or Query::OP_OR, or null for default (AND)
     *
     * @var string
     */
    protected $operator = null;

    /**
     * {@inheritdoc}
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->elements);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->elements);
    }

    /**
     * Create new term collection statement
     *
     * @return TermCollectionQuery
     */
    public function createTermCollection($operator = Query::OP_AND)
    {
        $statement = new TermCollectionQuery();
        $statement->setOperator($operator);

        $this->add($statement);

        return $statement;
    }

    /**
     * Create new term collection statement
     *
     * @return CollectionQuery
     */
    public function createCollection($operator = Query::OP_AND)
    {
        $statement = new CollectionQuery();
        $statement->setOperator($operator);

        $this->add($statement);

        return $statement;
    }

    /**
     * Create new term statement
     *
     * @return TermQuery
     */
    public function createTerm()
    {
        $statement = new TermQuery();

        $this->add($statement);

        return $statement;
    }

    /**
     * Create new arbitrary range statement
     *
     * @return RangeQuery
     */
    public function createRange()
    {
        $statement = new RangeQuery();

        $this->add($statement);

        return $statement;
    }

    /**
     * Create new arbitrary range statement
     *
     * @return DateRangeQuery
     */
    public function createDateRange($field = null)
    {
        $statement = new DateRangeQuery();
        $statement->setField($field);

        $this->add($statement);

        return $statement;
    }

    /**
     * Match single term to this query
     *
     * @param string $field
     * @param string|TermQuery $term
     * @param float $boost
     * @param float $fuzzyness
     *
     * @return $this
     */
    public function matchTerm($field = null, $term = null, $boost = null, $fuzzyness = null)
    {
        $this
            ->createTerm()
            ->setValue($term)
            ->setFuzzyness($fuzzyness)
            ->setBoost($boost)
            ->setField($field);

        return $this;
    }

    /**
     * Require range
     *
     * @param string $field
     * @param mixed $start
     * @param mixed $stop
     * @param boolean $inclusive
     *
     * @return $this
     */
    public function requireRange($field = null, $start = null, $stop = null, $inclusive = true)
    {
        $this
            ->createRange()
            ->setField($field)
            ->setInclusive($inclusive)
            ->setRange($start, $stop);

        return $this;
    }

    /**
     * Require date range
     *
     * @param string $field
     * @param int|string|\DateTime $start
     *   Timestamp, \DateTime parsable string or \DateTime object
     * @param int|string|\DateTime $stop
     *   Timestamp, \DateTime parsable string or \DateTime object
     * @param boolean $inclusive
     *
     * @return $this
     */
    public function requireDateRange($field = null, $start = null, $stop = null, $inclusive = true)
    {
        $this
            ->createDateRange()
            ->setInclusive($inclusive)
            ->setRange($start, $stop)
            ->setField($field);

        return $this;
    }

    /**
     * Require single term to this query
     *
     * @param string $field
     * @param string|TermQuery $term
     *
     * @return $this
     */
    public function requireTerm($field = null, $term = null)
    {
        $this
            ->createTerm()
            ->setValue($term)
            ->setExclusion(Query::OP_REQUIRE)
            ->setField($field);

        return $this;
    }

    /**
     * Prohibit single term to this query
     *
     * @param string $field
     * @param string|TermQuery $term
     *
     * @return $this
     */
    public function prohibitTerm($field = null, $term = null)
    {
        $this
            ->createTerm()
            ->setValue($term)
            ->setField($field)
            ->setExclusion(Query::OP_PROHIBIT);

        return $this;
    }

    /**
     * Match term collection (OR by default)
     *
     * @param string $field
     * @param string[]|TermQuery[] $terms
     * @param float $boost
     * @param string $operator
     *
     * @return $this
     */
    public function matchTermCollection($field = null, $terms = [], $boost = null, $operator = Query::OP_OR)
    {
        if (!is_array($terms)) {
            $terms = [$terms];
        }

        $this
            ->createTermCollection()
            ->addAll($terms)
            ->setOperator($operator)
            ->setField($field)
            ->setBoost($boost);

        return $this;
    }

    /**
     * Require term collection (OR by default)
     *
     * @param string $field
     * @param string[]|TermQuery[] $terms
     * @param float $boost
     * @param string $operator
     *
     * @return $this
     */
    public function requireTermCollection($field = null, $terms = [], $operator = Query::OP_OR)
    {
        if (!is_array($terms)) {
            $terms = [$terms];
        }

        $this
            ->createTermCollection()
            ->addAll($terms)
            ->setOperator($operator)
            ->setField($field)
            ->setExclusion(Query::OP_REQUIRE);

        return $this;
    }

    /**
     * Adds an element to the internal list
     *
     * @param AbstractQuery $element
     *
     * @return $this
     */
    public function add($element)
    {
        if (!$element instanceof AbstractQuery) {
            throw new \InvalidArgumentException("Provided element is not an AbstractQuery instance");
        }

        $this->elements[] = $element;

        return $this;
    }

    /**
     * Remove element
     *
     * @param AbstractQuery $element
     *
     * @return $this
     */
    protected function removeElement(AbstractQuery $element)
    {
        foreach ($this->elements as $key => $existing) {
            if ($existing === $element) {
                unset($this->elements[$key]);
            }
        }

        return $this;
    }

    /**
     * Set default operator
     *
     * @param string $operator
     *
     * @return $this
     */
    public function setOperator($operator)
    {
        if ($operator !== null && $operator !== Query::OP_AND && $operator !== Query::OP_OR) {
            throw new \InvalidArgumentException("Operator must be Query::OP_AND or Query::OP_OR");
        }

        $this->operator = $operator;

        return $this;
    }

    /**
     * Get current operator.
     *
     * @return string $operator
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return empty($this->elements);
    }

    /**
     * {@inheritdoc}
     */
    protected function toRawString()
    {
        if (empty($this->elements)) {
            return '';
        }
        if (count($this->elements) > 1) {
            $operator = ($this->operator ? (' ' . $this->operator . ' ') : ' ');
            return '(' . implode($operator, $this->elements) . ')';
        } else {
            reset($this->elements);
            return (string)current($this->elements);
        }
    }
}
