<?php

namespace MakinaCorpus\Lucene;

class RangeQuery extends AbstractQuery
{
    /**
     * @var mixed
     */
    protected $start = null;

    /**
     * @var mixed
     */
    protected $stop = null;

    /**
     * Is this range inclusive
     *
     * @var boolean
     *   Default is TRUE
     */
    protected $inclusive = true;

    /**
     * Set inclusive or exclusive mode
     *
     * @param boolean $inclusive
     *   Set to true for inclusive mode, false for exclusive mode
     *
     * @return $this
     */
    public function setInclusive($inclusive = true)
    {
        $this->inclusive = (bool)$inclusive;

        return $this;
    }

    /**
     * Construct a range statement
     *
     * If you build the object with both $start and $stop set to NULL, this
     * statement won't been built at all in the final query.
     *
     * We can, but we won't send [* TO *] useless range.
     *
     * @param null|mixed $start
     * @param null|mixed $stop
     *
     * @return $this
     */
    public function setRange($start, $stop)
    {
        $this->start = $start;
        $this->stop = $stop;

        return $this;
    }

    /**
     * Render range element
     *
     * Children classes can override this method in order to format their values
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function renderElement($value)
    {
        return (string)$value;
    }

    /**
     * Render range element
     *
     * Overriding classes must implement this function in order to escape values
     *
     * Replace the element by '*' wildcard if empty
     *
     * @param string $value
     *
     * @return string
     */
    protected function escapeElement($value)
    {
        if (empty($value)) {
            $element = '*';
        } else {
            $element = $this->renderElement($value);
        }

        return self::escapeToken($element);
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function toRawString()
    {
        if (empty($this->start) && empty($this->stop)) {
            return '';
        }

        if ($this->inclusive) {
            return '[' . $this->escapeElement($this->start) . ' TO ' . $this->escapeElement($this->stop) . ']';
        } else {
            return '{' . $this->escapeElement($this->start) . ' TO ' . $this->escapeElement($this->stop) . '}';
        }
    }
}
