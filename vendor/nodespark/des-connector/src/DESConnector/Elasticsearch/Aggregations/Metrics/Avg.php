<?php

namespace nodespark\DESConnector\Elasticsearch\Aggregations\Metrics;

class Avg extends Metric
{
    protected $missing;

    const TYPE = 'avg';

    public function __construct($aggrName, $aggrFieldName)
    {
        parent::__construct($aggrName, $aggrFieldName, self::TYPE);
    }

    /**
     * Documents without a value in the grade field,
     * will fall into the same bucket as documents that have the value $missing.
     *
     * @param float $missing
     *   The missing value that will be passed to aggregation.
     */
    public function setMissing($missing)
    {
        $this->missing = $missing;
    }

    /**
     * @return array
     */
    public function constructAggregation()
    {
        $aggregation = parent::constructAggregation();

        // Set the missing parameter if needed.
        if (isset($this->missing)) {
            $aggregation[$this->name][self::TYPE]['missing'] = $this->missing;
        }

        return $aggregation;
    }
}
