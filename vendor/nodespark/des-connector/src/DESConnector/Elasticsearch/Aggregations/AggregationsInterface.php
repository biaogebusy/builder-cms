<?php

namespace nodespark\DESConnector\Elasticsearch\Aggregations;

/**
 * Interface AggregationsInterface
 *
 * @package nodespark\DESConnector\Elasticsearch\Aggregations
 */
interface AggregationsInterface
{
    /**
     * Returns the *Singleton* instance of this class.
     *
     * @param string $id
     *   The id of the aggregation instance you want to get.
     *
     * @return AggregationsInterface The *Singleton* instance.
     */
    public static function getInstance($id = 'default');

    /**
     * Check if there are aggregations.
     *
     * @return bool
     */
    public function hasAggregations();

    /**
     * Set aggregation.
     *
     * @param \nodespark\DESConnector\Elasticsearch\Aggregations\AggregationInterface $aggregation
     */
    public function setAggregation(AggregationInterface $aggregation);

    /**
     * Construct the aggregations for sending them to Elasticsearch.
     *
     * @return array
     *   The array structure needed for Elasticsearch query.
     */
    public function constructAggregation();

    /**
     * Apply the aggregations to the Elasticsearch search params.
     *
     * @param array $params
     * @return
     */
    public function applyAggregationsToParams(&$params);
}
