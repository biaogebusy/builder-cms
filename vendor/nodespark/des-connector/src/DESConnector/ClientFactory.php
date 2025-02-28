<?php

namespace nodespark\DESConnector;

/**
 * Class ClientFactory
 */
class ClientFactory implements ClientFactoryInterface
{

    /**
     * Build an instance of the elastic search client
     *
     * @param array $params
     *   The client initializer parameters.
     *
     * @return \nodespark\DESConnector\ClientInterface
     */
    public function create(array $params)
    {
        $instance = new Client($params);
        // TODO: the logic inside Client's constructor throw an exception
        // or returns the expected instance so the following code does not
        // seem to ever run.
        if (!($instance instanceof ClientInterface)) {
            // TODO: Handle the exception with specific class and handle the translation.
            throw new \Exception('The instance of the class is not the supported one.');
        }

        return $instance;
    }
}
