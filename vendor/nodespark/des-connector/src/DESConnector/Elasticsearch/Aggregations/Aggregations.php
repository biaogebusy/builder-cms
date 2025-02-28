<?php

namespace nodespark\DESConnector\Elasticsearch\Aggregations;

/**
 * TODO:
 * Should generate the following output at the end:
 *
 *
 * "aggregations" : {
 * "<aggregation_name>" : {
 * "<aggregation_type>" : {
 * <aggregation_body>
 * }
 * [,"meta" : {  [<meta_data_body>] } ]?
 * [,"aggregations" : { [<sub_aggregation>]+ } ]?
 * }
 * [,"<aggregation_name_2>" : { ... } ]*
 * }
 * Class Aggregations
 * @package nodespark\DESConnector\Elasticsearch\Aggregations
 */
class Aggregations implements AggregationsInterface
{
    const AGGS_STRING = 'aggs';

    /**
     * @var Aggregations The reference to *Singleton* instance of this class
     */
    private static $instance;

    /**
     * {@inheritdoc}
     */
    public static function getInstance($id = 'default')
    {
        if (!isset(static::$instance[$id])) {
            static::$instance[$id] = new static();
        }

        return static::$instance[$id];
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct()
    {
    }

    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Private unserialize method to prevent unserializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    public function __wakeup()
    {
    }

    /**
     * Private serialize method to prevent serializing of the *Singleton*
     * instance.
     */
    public function __sleep()
    {
    }

    /**
     * @var array
     *   Array of Aggregation objects.
     */
    protected $aggregations = array();

    /**
     * {@inheritdoc}
     */
    public function hasAggregations()
    {
        return !empty($this->aggregations);
    }

    /**
     * {@inheritdoc}
     */
    public function setAggregation(AggregationInterface $aggregation)
    {
        $this->aggregations[$aggregation->getName()] = $aggregation;
    }

    /**
     * {@inheritdoc}
     */
    public function constructAggregation()
    {
        $aggregationArray = array();
        foreach ($this->aggregations as $name => $aggregationObj) {
            $aggsArray = $aggregationObj->constructAggregation();
            $aggregationArray[$name] = $aggsArray[$name];
            if (isset($aggsArray[$name . '_global'])) {
              $aggregationArray[$name . '_global'] = $aggsArray[$name . '_global'];
            }
        }

        if (!empty($aggregationArray)) {
            return array(Aggregations::AGGS_STRING => $aggregationArray);
        } else {
            return $aggregationArray;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function applyAggregationsToParams(&$params)
    {
        $aggregationParams = $this->constructAggregation();
        if (!empty($aggregationParams)) {
            $params['body'][static::AGGS_STRING] =
                $aggregationParams[static::AGGS_STRING];
        }
    }

    public function setAggregationResponse($response)
    {

    }
}
