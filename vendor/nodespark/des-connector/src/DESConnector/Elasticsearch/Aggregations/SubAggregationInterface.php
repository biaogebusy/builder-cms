<?php
/**
 * Created by PhpStorm.
 * User: nikolayignatov
 * Date: 1/21/17
 * Time: 12:47 PM
 */

namespace nodespark\DESConnector\Elasticsearch\Aggregations;

interface SubAggregationInterface
{
    public function setSubAggregation(AggregationInterface $aggregation);
}
