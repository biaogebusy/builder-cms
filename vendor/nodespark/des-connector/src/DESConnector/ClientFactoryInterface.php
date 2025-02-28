<?php

namespace nodespark\DESConnector;

/**
 * Interface ClientFactoryInterface
 *
 * @package nodespark\DESConnector
 */
interface ClientFactoryInterface
{

    /**
     * Create and return Elasticsearch client library.
     * @return \nodespark\DESConnector\ClientInterface
     */
    public function create(array $params);
}
