<?php

namespace nodespark\DESConnector\Elasticsearch\Aggregations\Bucket;

use nodespark\DESConnector\Elasticsearch\Aggregations\Aggregation;
use nodespark\DESConnector\Elasticsearch\Aggregations\AggregationInterface;
use nodespark\DESConnector\Elasticsearch\Aggregations\SubAggregationInterface;

/**
 * Class Bucket.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket.html
 * @package nodespark\DESConnector\Elasticsearch\Aggregations\Bucket
 */
abstract class Bucket extends Aggregation implements AggregationInterface, SubAggregationInterface
{
    protected $subAggregations = array();

    /**
     * @param string $aggrName
     * @param string $aggrFieldName
     */
    public function __construct($aggrName, $aggrFieldName)
    {
        parent::__construct($aggrName, $aggrFieldName, static::TYPE);
        $this->addParameter('field', $aggrFieldName);
    }

    /**
     * Construct the aggregation body needed for Elasticsearch.
     */
    public function constructAggregation()
    {
        $aggregation = parent::constructAggregation();

        // Construct the sub aggregations.
        foreach ($this->subAggregations as $name => $aggregationObj) {
            $aggregation[$this->name]['aggs'] = $aggregationObj->constructAggregation();
        }

        return $aggregation;
    }

    /**
     * Set a sub aggregation.
     *
     * @param AggregationInterface $aggregation
     *   The sub aggregation param.
     */
    public function setSubAggregation(AggregationInterface $aggregation)
    {
        $this->subAggregations[$aggregation->getName()] = $aggregation;
    }
}
