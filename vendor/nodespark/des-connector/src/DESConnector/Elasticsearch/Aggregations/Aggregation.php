<?php

namespace nodespark\DESConnector\Elasticsearch\Aggregations;

/**
 * Class Aggregation
 * @package nodespark\DESConnector\Elasticsearch\Aggregations
 */
abstract class Aggregation implements AggregationInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $type;

    protected $parameters = array();

    /**
     * @var bool
     */
    protected $global = false;

    /**
     * @var array
     */
    protected $response;

    /**
     * Metric constructor.
     *
     * @param string $aggrName
     *   The aggregation name.
     * @param string $aggrFieldName
     *   The aggregation field name.
     */
    public function __construct($aggrName, $aggrFieldName, $aggrType)
    {
        $this->name = $aggrName;
        $this->fieldName = $aggrFieldName;
        $this->type = $aggrType;
    }

    /**
     * @param bool $isGlobal
     */
    public function setGlobalScope($isGlobal)
    {
        $this->global = $isGlobal;
    }

    public function getGlobalName()
    {
        return $this->name . '_global';
    }

    public function getFieldName()
    {
        return $this->fieldName;
    }

    protected function getParameter($key)
    {
        return isset($this->parameters[$key]) ? $this->parameters[$key] : NULL;
    }

    protected function addParameter($key, $param_value)
    {
        $this->parameters[$key] = $param_value;
    }

    protected function removeParameter($key)
    {
        unset($this->parameters[$key]);
    }

    protected function getParameters()
    {
        if (empty($this->parameters)) {
            $this->addParameter('field', $this->getFieldName());
        }

        return $this->parameters;
    }

    /**
     * Construct the aggregation body needed for Elasticsearch.
     */
    public function constructAggregation()
    {
        $aggregation = array(
            $this->name => array(
                $this->type => $this->getParameters(),
            )
        );

        if ($this->global) {
            $aggregation[$this->getGlobalName()] = array(
                // TODO: Check if global is available for all Aggregations or it is bind
                // to Bucket only.
                // TODO: Global to make it as const.
                // Global has to be an (empty) object.
                'global' => new \stdClass(),
                Aggregations::AGGS_STRING => $aggregation,
            );
        }

        return $aggregation;
    }

    /**
     * @return string
     *   The aggregation name.
     */
    public function getName()
    {
        return $this->name;
    }

    public function setResponse($response)
    {
    }

    public function parseResponse()
    {
    }

    // TODO: Add meta support.
}
