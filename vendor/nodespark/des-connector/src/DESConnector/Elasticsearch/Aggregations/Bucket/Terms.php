<?php

namespace nodespark\DESConnector\Elasticsearch\Aggregations\Bucket;

/**
 * Class Terms.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-terms-aggregation.html
 * @package nodespark\DESConnector\Elasticsearch\Aggregations\Bucket
 */
class Terms extends Bucket
{

    const TYPE = 'terms';

    protected $size;

    /**
     * @var array
     *   The order of the term.
     */
    protected $order;

    const ORDER_BY_TERM = '_term';
    const ORDER_BY_COUNT = '_count';

    public function setSize($size)
    {
        $this->size = $size;
    }

    public function setOrder($order)
    {
        $this->order = $order;
    }

    public function constructAggregation()
    {
        $aggregation = parent::constructAggregation();

        // Set the additional parameters if needed.
        if (isset($this->size)) {
            $aggregation[$this->name][static::TYPE]['size'] = $this->size;
            // Also set the parameters if global name is set.
            if (isset($aggregation[$this->name . '_global'])) {
              $aggregation[$this->name . '_global']['aggs'][$this->name][static::TYPE]['size'] = $this->size;
            }
        }

        if (isset($this->order)) {
            $aggregation[$this->name][static::TYPE]['order'] = $this->order;
        }

        return $aggregation;
    }
}
