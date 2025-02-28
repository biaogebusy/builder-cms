<?php

namespace nodespark\DESConnector;

// TODO: We should only have interfaces here, all actual classes,
// should be injected.

use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use nodespark\DESConnector\Elasticsearch\Aggregations\Aggregations;
use nodespark\DESConnector\Elasticsearch\Query\Query;
use nodespark\DESConnector\Elasticsearch\Query\QueryInterface;
use nodespark\DESConnector\Elasticsearch\Response\SearchResponse;
use nodespark\DESConnector\Elasticsearch\Response\SearchResponseInterface;

/**
 * Class Client
 */
class Client implements ClientInterface
{

    /**
     *
     */
    const CLUSTER_STATUS_GREEN = 'green';
    /**
     *
     */
    const CLUSTER_STATUS_YELLOW = 'yellow';
    /**
     *
     */
    const CLUSTER_STATUS_RED = 'red';

    /**
     * @var \Elasticsearch\Client
     */
    protected $proxy_client;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var string
     */
    protected $client_uuid;

    /**
     * @var SearchResponseInterface
     */
    protected $searchResponse;

    /**
     * Client constructor.
     *
     * @param array $params
     *   The params that should initialize the client.
     */
    public function __construct($params)
    {
        $this->client_uuid = uniqid();
        $this->params = $params;
        $this->initClient($this->params);
    }

    /**
     * Magic method __call().
     * If the method is not found, search if the method exists in the proxy.
     * If exists, call it.
     *
     * @param string $name
     *   The name of the method we are calling.
     * @param array $arguments
     *   The arguments passed to the method.
     *
     * @return mixed
     *   The result of the execution of the proxy_client method.
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->proxy_client, $name)) {
            return call_user_func_array(array(
                $this->proxy_client,
                $name
            ), $arguments);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getClusterStatus()
    {
        try {
            $health = $this->proxy_client->cluster()->health();
            return $health['status'];
        } catch (ElasticsearchException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isClusterOk()
    {
        try {
            $health = $this->proxy_client->cluster()->health();

            if (in_array(
                $health['status'],
                [self::CLUSTER_STATUS_GREEN, self::CLUSTER_STATUS_YELLOW]
            )) {
                $status = true;
            } else {
                $status = false;
            }
        } catch (ElasticsearchException $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * @return array
     */
    public function info()
    {
        $info = $this->proxy_client->info();
        // Compatible with D7 version.
        $info['status'] = 200;
        return $info;
    }

    /**
     * {@inheritdoc}
     */
    public function getClusterInfo()
    {
        $result = [
            'state' => null,
            'health' => null,
            'stats' => null,
        ];

        try {
            $result['info'] = $this->proxy_client->info();
        } catch (ElasticsearchException $e) {
            throw $e;
        }

        try {
            $result['state'] = $this->proxy_client->cluster()->State();
        } catch (ElasticsearchException $e) {
        }

        try {
            $result['health'] = $this->proxy_client->cluster()->Health();
        } catch (ElasticsearchException $e) {
        }

        try {
            $result['stats'] = $this->proxy_client->cluster()->Stats();
        } catch (ElasticsearchException $e) {
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getNodesProperties()
    {
        try {
            $nodes = $this->proxy_client->nodes();

            return [
                'stats' => $nodes->stats(),
                'info' => $nodes->info(),
            ];
        } catch (ElasticsearchException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getIndicesStats()
    {
        return $this->proxy_client->indices()->stats();
    }

    /**
     * Initialize the real Elasticsearch client.
     *
     * @param $params
     *   The client initializer.
     *
     * @return void
     */
    protected function initClient($params)
    {
        $conn_params = array();
        if (isset($params['curl'])) {
            $conn_params['client']['curl'] = $params['curl'];
        }

        $builder = ClientBuilder::create();
        $params = $this->handleUrls($params);
        $builder->setHosts($params['hosts']);
        if (!empty($conn_params)) {
            $builder->setConnectionParams($conn_params);
        }

        if (isset($params['handler'])) {
            $builder->setHandler($params['handler']);
        }

        $this->proxy_client = $builder->build();
    }

    /**
     * Handle the URLs specifics like authentication.
     *
     * @param array $params
     *   The client initializer params.
     *
     * @return array
     *   Reworked params if needed.
     */
    private function handleUrls($params)
    {
        if (isset($params['auth'])) {
            foreach ($params['hosts'] as $key => $url) {
                $url_parsed = parse_url($url);
                if ($url_parsed !== false) {
                    $url_parsed['user'] = $params['auth'][$url]['username'];
                    $url_parsed['pass'] = $params['auth'][$url]['password'];
                    $params['hosts'][$key] =
                        ((isset($url_parsed['scheme'])) ? $url_parsed['scheme'] . '://' : '')
                        . ((isset($url_parsed['user'])) ? $url_parsed['user'] .
                            ((isset($url_parsed['pass'])) ? ':' . $url_parsed['pass'] : '') . '@' : '')
                        . ((isset($url_parsed['host'])) ? $url_parsed['host'] : '')
                        . ((isset($url_parsed['port'])) ? ':' . $url_parsed['port'] : '')
                        . ((isset($url_parsed['path'])) ? $url_parsed['path'] : '')
                        . ((isset($url_parsed['query'])) ? '?' . $url_parsed['query'] : '')
                        . ((isset($url_parsed['fragment'])) ? '#' . $url_parsed['fragment'] : '');
                }
            }
        }

        return $params;
    }

    /**
     * Check if the Elasticsearch response is successful and with status code 200.
     *
     * TODO: Change the method to use camel case format!
     *
     * @param mixed $response
     *
     * @return bool
     */
    public function CheckResponseAck($response)
    {
        if (is_array($response) && !empty($response['acknowledged'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the version number of the Elasticsearch server.
     *
     * @throws \Elasticsearch\Common\Exceptions\ElasticsearchException
     */
    public function getServerVersion()
    {
        $info = $this->info();
        return $info['version']['number'];
    }

    /**
     * Get all plugins that exists on all nodes.
     * TODO: This should be changed to check all data Nodes only but for now lets check all of them.
     *
     * Read more about plugins system here:
     * http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/modules-plugins.html
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getInstalledPlugins()
    {
        $nodes_plugins = array();

        try {
            $plugins = $this->proxy_client->nodes()
                ->info(array('node_id' => '_all'));
            foreach ($plugins['nodes'] as $elastic_node_id => $elastic_node) {
                foreach ($elastic_node['plugins'] as $plugin) {
                    $nodes_plugins[$elastic_node_id][$plugin['name']] = $plugin;
                }
            }

            return $nodes_plugins;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Check if Elasticsearch plugin exists.
     *
     * @param string $plugin_name
     *   The name of the plugin you are looking for.
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function checkIfPluginExists($plugin_name)
    {
        $plugins = $this->getInstalledPlugins();

        foreach ($plugins as $elastic_node_id => $plugin) {
            if (isset($plugin[$plugin_name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function aggregations()
    {
        return Aggregations::getInstance($this->client_uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function search($params)
    {
        if (empty($params[Aggregations::AGGS_STRING]) && $this->aggregations()
                ->hasAggregations()
        ) {
            $this->aggregations()->applyAggregationsToParams($params);
        }

        // Temporary workaround until we have fully functional query builder.
        $query = new Query();

        $response = $this->setSearchResponse($this->proxy_client->search($params), $query);

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function indices()
    {
        return $this->proxy_client->indices();
    }

    /**
     * @inheritdoc
     */
    public function getSearchResponse()
    {
        return $this->searchResponse;
    }

    /**
     * @inheritdoc
     */
    public function setSearchResponse(array $searchResponse, QueryInterface $query)
    {
        $this->searchResponse = new SearchResponse($searchResponse, $this->aggregations(), $query);
        return $this->searchResponse;
    }
}
