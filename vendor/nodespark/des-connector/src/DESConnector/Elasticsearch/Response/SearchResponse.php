<?php

/**
 * Handling the search response by parsing and manipulate it.
 */

namespace nodespark\DESConnector\Elasticsearch\Response;

use nodespark\DESConnector\Elasticsearch\Aggregations\AggregationsInterface;
use nodespark\DESConnector\Elasticsearch\Query\QueryInterface;

class SearchResponse implements SearchResponseInterface
{
    /**
     * The response from execution of \Elasticsearch\Client::search() method.
     * @var array
     */
    protected $response = array();

    /**
     * @var AggregationsInterface
     */
    protected $aggregations;

    /**
     * @var QueryInterface
     */
    protected $query;

    /**
     * The time spend for the search request.
     * @var int
     */
    protected $timeSpend = 0;

    /**
     * Indicate if the response timed out.
     * @var bool
     */
    protected $doesTimeOut = false;

    /**
     * @inheritdoc
     */
    public function __construct(array $response, AggregationsInterface $aggregations, QueryInterface $query)
    {
        $this->response = $response;
        $this->aggregations = $aggregations;
    }

    /**
     * Validate the responses
     */
    protected function validateResponse()
    {

    }

    /**
     * @return static
     */
    protected function parseResponse()
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSearchTimeTook()
    {
        return $this->response['took'];
    }

    /**
     * @inheritdoc
     */
    public function hasTimeout()
    {
        return (bool) $this->response['timed_out'];
    }

    /**
     * @inheritdoc
     */
    public function getTotalShards()
    {
        return $this->response['_shards']['total'];
    }

    /**
     * @inheritdoc
     */
    public function getFailedShards()
    {
        return $this->response['_shards']['failed'];
    }

    /**
     * @inheritdoc
     */
    public function getSuccessfulShards()
    {
        return $this->response['_shards']['successful'];
    }

    /**
     * @inheritdoc
     */
    public function getSearchMaxScore()
    {
        return $this->response['hits']['max_score'];
    }

    /**
     * @inheritdoc
     */
    public function getTotalResultsNumber()
    {
        return $this->response['hits']['total']['value'];
    }

    /**
     * @inheritdoc
     */
    public function getRawResponse()
    {
        return $this->response;
    }

    /**
     * @inheritdoc
     */
    public function getRawResults()
    {
        if (isset($this->response['hits']['hits'])) {
            return $this->response['hits']['hits'];
        }

        return array();
    }

    /**
     * @inheritdoc
     */
    public function getResults()
    {
        // TODO: Parse the documents and initialize Document() object.
        if (isset($this->response['hits']['parsed_hits'])) {
            return $this->response['hits']['parsed_hits'];
        }

        return array();
    }
}
