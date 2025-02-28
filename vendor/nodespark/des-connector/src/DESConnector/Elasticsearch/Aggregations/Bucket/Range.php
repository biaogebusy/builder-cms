<?php

namespace nodespark\DESConnector\Elasticsearch\Aggregations\Bucket;

/**
 * Class Range
 *
 * @package nodespark\DESConnector\Elasticsearch\Aggregations\Bucket
 */
class Range extends Bucket
{
    const TYPE = 'range';

    /**
     * @param $value
     */
    public function setInterval(array $value)
    {
        $this->addParameter('ranges', $value);
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
    public function setScript(array $value)
    {
        // TODO: Change it to expect the value to be of type scriptInterface.
        $this->addParameter('script', $value);
    }

}
