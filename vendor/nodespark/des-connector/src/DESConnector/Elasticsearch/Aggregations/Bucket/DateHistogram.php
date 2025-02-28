<?php

namespace nodespark\DESConnector\Elasticsearch\Aggregations\Bucket;

/**
 * Class DateHistogram.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-datehistogram-aggregation.html
 * @package nodespark\DESConnector\Elasticsearch\Aggregations\Bucket
 */
class DateHistogram extends Bucket
{

    const TYPE = 'date_histogram';

    /**
     * FIXED_INTERVAL_TYPES
     */
    const FIXED_INTERVAL_TYPES = array(
        'year' => 'year',
        'quarter' => 'quarter',
        'month' => 'month',
        'week' => 'week',
        'day' => 'day',
        'hour' => 'hour',
        'minute' => 'minute',
        'second' => 'second',
    );

    /**
     * FRACTIONAL_INTERVALS
     */
    const FRACTIONAL_INTERVALS = array(
        'Y' => 'Y',
        'M' => 'M',
        'w' => 'w',
        'd' => 'd',
        'h' => 'h',
        'm' => 'm',
        's' => 's',
        'ms' => 'ms',
    );

    /**
     * @param $value
     */
    public function setInterval($value)
    {
        // TODO: Validate the interval before set!
        $this->addParameter('interval', $value);
    }

    /**
     * @param $value
     */
    public function setTimeZone($value)
    {
        // TODO: timeZone should be an object itself;
        $this->addParameter('time_zone', $value);
    }

    /**
     * @param $value
     */
    public function setOffSet($value)
    {
        $this->addParameter('offset', $value);
    }

    /**
     * @param $value
     */
    public function setMinDocCount($value)
    {
        $this->addParameter('min_doc_count', $value);
    }

    /**
     * @param $min
     * @param @max
     */
    public function setExtendedBounds($min, $max)
    {
        $this->addParameter(
            'extended_bounds',
            array(
                'min' => $min,
                'max' => $max,
            )
        );
    }

    /**
     * @param $key
     * @param $direction
     */
    public function setOrder($key, $direction)
    {
        $this->addParameter(
            'order',
            array(
                $key => $direction,
            )
        );
    }

    /**
     * @param $value
     */
    public function setKeyed($value = FALSE)
    {
        $this->addParameter('keyed', $value);
    }

    /**
     * @param $value
     */
    public function setFormat($value)
    {
        $this->addParameter('format', $value);
    }

    /**
     * @param $key
     * @param $direction
     */
    public function setMissing($value)
    {
        $this->addParameter('missing', $value);
    }

}
