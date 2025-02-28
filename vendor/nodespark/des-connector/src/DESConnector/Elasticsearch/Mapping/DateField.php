<?php
namespace nodespark\DESConnector\Elasticsearch\Mapping;

/**
 * Class DateField.
 *
 * @package nodespark\DESConnector\Elasticsearch\Mapping
 */
class DateField extends Field
{
    const FORMATS = array(
        'epoch_millis' => 'epoch_millis',
        'epoch_second' => 'epoch_second',
        // TODO: Add the rest of the formats.
    );
}
