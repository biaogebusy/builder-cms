<?php

namespace nodespark\DESConnector\Elasticsearch\Aggregations\Bucket;

/**
 * Class Children
 *
 * @package nodespark\DESConnector\Elasticsearch\Aggregations\Bucket
 */
class Children extends Bucket
{

    const TYPE = 'children';

    /**
     * @param string $aggrName
     * @param string $aggrFieldName
     */
    public function __construct($aggrName, $aggrFieldName)
    {
        parent::__construct($aggrName, $aggrFieldName);

        $this->removeParameter('field');
        $this->addParameter('type', $this->getFieldName());
    }

}
