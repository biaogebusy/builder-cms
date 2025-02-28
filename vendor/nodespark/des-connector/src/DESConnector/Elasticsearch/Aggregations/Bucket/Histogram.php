<?php

namespace nodespark\DESConnector\Elasticsearch\Aggregations\Bucket;

/**
 * Class Histogram
 *
 * @package nodespark\DESConnector\Elasticsearch\Aggregations\Bucket
 */
class Histogram extends Bucket
{

    const TYPE = 'histogram';

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
    public function setOffSet($value)
    {
        $this->addParameter('offset', $value);
    }

    /**
     * @param $value
     */
    public function setKeyed($value = false)
    {
        $this->addParameter('keyed', $value);
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
