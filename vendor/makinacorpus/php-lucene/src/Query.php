<?php

namespace MakinaCorpus\Lucene;

class Query extends CollectionQuery
{
    /**
     * Require operator
     */
    public const OP_REQUIRE = '+';

    /**
     * Prohibit operator
     */
    public const OP_PROHIBIT = '-';

    /**
     * Boost operator
     */
    public const OP_BOOST = '^';

    /**
     * Fuzzyness / roaming operator
     */
    public const OP_FUZZY_ROAMING = "~";

    /**
     * And operator
     */
    public const OP_AND = 'AND';

    /**
     * Or operator
     */
    public const OP_OR = 'OR';

    /**
     * '*' wildcard
     */
    public const WILDCARD_ALL = '*';
}
