<?php

namespace nodespark\DESConnector\Elasticsearch\Response;

use nodespark\DESConnector\Elasticsearch\Aggregations\AggregationsInterface;
use nodespark\DESConnector\Elasticsearch\Query\QueryInterface;

interface SearchResponseInterface
{
    /**
     * SearchResponse constructor.
     *
     * @param array $response
     *   The response from execution of \Elasticsearch\Client::search() method
     * @param AggregationsInterface $aggregations
     *   The aggregations for the current search.
     * @param QueryInterface $query
     *   The query for the current search.
     */
    public function __construct(array $response, AggregationsInterface $aggregations, QueryInterface $query);

    /**
     * Get the time Elasticsearch spending on executing the search.
     *
     * @return int
     *   Time in milliseconds for Elasticsearch to execute the search.
     */
    public function getSearchTimeTook();

    /**
     * Does Elasticsearch timed out on search execution.
     *
     * @return bool
     *   TRUE - timed out.
     *   FALSE - does not timed out.
     */
    public function hasTimeout();

    /**
     * Get the total number of shards searched by Elasticsearch.
     *
     * @return int
     *   Number of shards.
     */
    public function getTotalShards();

    /**
     * Get the number of successful shards searched by Elasticsearch.
     *
     * @return int
     *   Number of successful shards.
     */
    public function getSuccessfulShards();

    /**
     * Get the number of failed shards searched by Elasticsearch.
     *
     * @return int
     *   Number of failed shards.
     */
    public function getFailedShards();

    /**
     * Get the total number of results.
     *
     * @return int
     *   The total number of results.
     */
    public function getTotalResultsNumber();

    /**
     * Get the max score of the search.
     *
     * @return float
     *   The max score of the search calculate by Elasticsearch.
     */
    public function getSearchMaxScore();

    /**
     * Get the document result set.
     *
     * @return array
     *   An array, each element contains a class of
     * \nodespark\DESConnector\Elasticsearch\Objects\Document
     */
    public function getResults();

    /**
     * Get the raw results from Elasticsearch.
     * @return array
     *   The raw result from Elasticsearch search execution.
     */
    public function getRawResults();

    /**
     * Get the raw response from Elasticsearch.
     * @return array
     */
    public function getRawResponse();
}
