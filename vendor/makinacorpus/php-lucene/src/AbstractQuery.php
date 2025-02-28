<?php

namespace MakinaCorpus\Lucene;

/**
 * Applies boost and prohibit/require operators over an expression
 */
abstract class AbstractQuery
{
    /**
     * Regex that matches all Lucene query syntax reserved chars
     */
    public const RE_SPECIALS = '/\+|-|=|&|\||<|>|!|\(|\)|\{|\}|\[|\]|\^|"|~|\*|\?|\:|\/|\\\/';

    /**
     * Add '"' chars if necessary to a token value, and escape Lucene query
     * syntax reserved chars
     *
     * @param string $token
     *   String to escape
     * @param boolean $force = FALSE
     *   Escape whatever happens
     *
     * @return string
     */
    public static function escapeToken($token, $force = false)
    {
        $escaped = preg_replace(self::RE_SPECIALS, '\\\\\\0', $token);

        if ($force || preg_match('/ /', $escaped) || strlen($token) != strlen($escaped)) {
            return '"' . $escaped . '"';
        } else {
            return $escaped;
        }
    }

    /**
     * REQUIRE or PROHIBIT operator
     */
    protected $exclusion = null;

    /**
     * Boost float value, NULL for default
     */
    protected $boost = null;

    /**
     * Field name
     *
     * @var int $field
     */
    protected $field = null;

    /**
     * Set exclusion mode
     *
     * @param string $exclusive
     *   Query::OP_REQUIRE or Query::OP_PROHIBIT or null
     *
     * @return $this
     */
    public function setExclusion($exclusion)
    {
        if (!empty($exclusion) && $exclusion !== Query::OP_PROHIBIT && $exclusion !== Query::OP_REQUIRE) {
            throw new \InvalidArgumentException("Exclusion must be Query::OP_REQUIRE or Query::OP_PROHIBIT");
        }

        $this->exclusion = $exclusion;

        return $this;
    }

    /**
     * Set exclusion mode
     *
     * @param float $boost
     *   Float superior to 0 or null
     *
     * @return $this
     */
    public function setBoost($boost)
    {
        if (!empty($boost) && (!is_numeric($boost) && $boost <= 0)) {
            throw new \InvalidArgumentException("Boost must be a absolute positive float");
        }

        $this->boost = (float)$boost;

        return $this;
    }

    /**
     * Set field name
     *
     * @param string $field
     *
     * @return $this
     */
    public function setField($field)
    {
        $this->field = (string)$field;

        return $this;
    }

    /**
     * Is this query empty
     *
     * @return boolean
     */
    abstract public function isEmpty();

    /**
     * Build this specific statement query string
     *
     * @return string
     */
    abstract protected function toRawString();

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

        if ($this->exclusion) {
            $raw = $this->exclusion . $raw;
        } elseif ($this->boost) {
            $raw .= '^' . $this->boost;
        }

        return $raw;
    }
}
