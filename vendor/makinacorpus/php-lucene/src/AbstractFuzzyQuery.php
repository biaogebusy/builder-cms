<?php

namespace MakinaCorpus\Lucene;

/**
 * Applies fuzziness or romaing operator over an expression
 */
abstract class AbstractFuzzyQuery extends AbstractQuery
{
    /**
     * Automatic fuzzy distance.
     */
    public const FUZZY_AUTO = 'auto';

    /**
     * @var mixed
     */
    protected $fuzzyness;

    /**
     * Set fuzzyness or roaming value, both uses the same operator, only the
     * type of data (phrase or term) on which you apply it matters
     *
     * @param mixed $fuzzyness
     *   Positive integer or null to unset. You may also pass in 'auto' to leave
     *   the fuzzy distance up to the implementation.
     *
     * @return $this
     */
    public function setFuzzyness($fuzzyness)
    {
        if ($fuzzyness != null && $fuzzyness !== self::FUZZY_AUTO && (!is_numeric($fuzzyness) || $fuzzyness < 0)) {
            throw new \InvalidArgumentException(
                sprintf("Fuzyness/roaming value must be a positive integer, %s given", $fuzzyness)
            );
        }

        $this->fuzzyness = $fuzzyness;

        return $this;
    }

    /**
     * Alias for setFuzzyness() method
     *
     * @param int $roaming
     *   Positive integer
     *
     * @return $this
     */
    public function setRoaming($roaming)
    {
        $this->setFuzzyness($roaming);

        return $this;
    }

    /**
     * Escape and apply boost and operators surrounding the given string
     *
     * @return string
     */
    public function __toString()
    {
        $raw = trim($this->toRawString());

        if ('' === $raw) {
            return '';
        }

        if ($this->field) {
            $raw = self::escapeToken($this->field) . ':' . $raw;
        }

        if (!empty($this->exclusion)) {
            $raw = $this->exclusion . $raw;
        } else {
            if (!empty($this->fuzzyness)) {
                if ($this->fuzzyness === self::FUZZY_AUTO) {
                    $raw .= '~';
                } else {
                    $raw .= '~' . $this->fuzzyness;
                }
            }
            if (!empty($this->boost)) {
                $raw .= '^' . $this->boost;
            }
        }

        return $raw;
    }
}
