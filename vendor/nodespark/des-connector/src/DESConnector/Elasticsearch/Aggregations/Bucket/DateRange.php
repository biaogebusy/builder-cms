<?php

namespace nodespark\DESConnector\Elasticsearch\Aggregations\Bucket;

/**
 * Class DateRange
 *
 * @package nodespark\DESConnector\Elasticsearch\Aggregations\Bucket
 */
class DateRange extends Bucket
{

    const TYPE = 'date_range';

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

    /**
     * @param $value
     */
    public function setFormat($value)
    {
        $this->addParameter('format', $value);
    }

    /**
     * @param $value
     */
    public function setTimeZone($value)
    {
        // TODO: timeZone should be an object itself;
        $this->addParameter('time_zone', $value);
    }

}
