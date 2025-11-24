<?php

namespace Drupal\elasticsearch_connector\Plugin\search_api\backend;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Url;
use Drupal\elasticsearch_connector\Connector\ConnectorPluginManager;
use Drupal\elasticsearch_connector\Connector\ElasticSearchConnectorInterface;
use Drupal\elasticsearch_connector\Connector\InvalidConnectorException;
use Drupal\elasticsearch_connector\Event\SupportsDataTypeEvent;
use Drupal\elasticsearch_connector\SearchAPI\BackendClientFactory;
use Drupal\elasticsearch_connector\SearchAPI\BackendClientInterface;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Elastic\Elasticsearch\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides an ElasticSearch backend for Search API.
 *
 * @SearchApiBackend(
 *   id = "elasticsearch",
 *   label = @Translation("ElasticSearch"),
 *   description = @Translation("Provides an ElasticSearch backend.")
 * )
 */
class ElasticSearchBackend extends BackendPluginBase implements PluginFormInterface {

  use DependencySerializationTrait {
    __sleep as traitSleep;
  }
  use PluginDependencyTrait {
    getPluginDependencies as traitGetPluginDependencies;
  }

  /**
   * Auto fuzziness setting.
   *
   * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/common-options.html#fuzziness
   */
  const FUZZINESS_AUTO = 'auto';

  /**
   * The client factory.
   *
   * @var \Drupal\elasticsearch_connector\Connector\ConnectorPluginManager
   */
  protected $connectorPluginManager;

  /**
   * The ElasticSearch backend client factory.
   *
   * @var \Drupal\elasticsearch_connector\SearchAPI\BackendClientFactory
   */
  protected $backendClientFactory;

  /**
   * The ElasticSearch Search API client.
   *
   * @var \Drupal\elasticsearch_connector\SearchAPI\BackendClient
   */
  protected $backendClient;

  /**
   * The Elasticsearch client.
   *
   * @var \Elastic\Elasticsearch\Client
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    ConnectorPluginManager $connectorPluginManager,
    BackendClientFactory $sapiClientFactory,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->connectorPluginManager = $connectorPluginManager;
    $this->backendClientFactory = $sapiClientFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.elasticsearch_connector.connector'),
      $container->get('elasticsearch_connector.backend_client_factory'),
      $container->get('event_dispatcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedFeatures() {
    return [
      'search_api_facets',
      'search_api_facets_operator_or',
      'search_api_mlt',
      'search_api_spellcheck',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'connector' => 'standard',
      'connector_config' => [],
      'advanced' => [
        'fuzziness' => self::FUZZINESS_AUTO,
        'prefix' => NULL,
        'suffix' => NULL,
        'synonyms' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    $options = $this->getConnectorOptions();
    $form['connector'] = [
      '#type' => 'radios',
      '#title' => $this->t('ElasticSearch Connector'),
      '#description' => $this->t('Choose a connector to use for this ElasticSearch server.'),
      '#options' => $options,
      '#default_value' => $this->configuration['connector'],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'buildAjaxConnectorConfigForm'],
        'wrapper' => 'elasticsearch-connector-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $this->buildConnectorConfigForm($form, $form_state);

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
    ];

    $fuzzinessOptions = [
      '0' => $this->t('- Disabled -'),
      self::FUZZINESS_AUTO => self::FUZZINESS_AUTO,
    ];
    $fuzzinessOptions += array_combine(range(1, 5), range(1, 5));
    $form['advanced']['fuzziness'] = [
      '#type' => 'select',
      '#title' => $this->t('Fuzziness'),
      '#required' => TRUE,
      '#options' => $fuzzinessOptions,
      '#default_value' => $this->configuration['advanced']['fuzziness'] ?? 0,
      '#description' => $this->t('Some queries and APIs support parameters to allow inexact fuzzy matching, using the fuzziness parameter. See <a href="https://www.elastic.co/guide/en/elasticsearch/reference/current/common-options.html#fuzziness">Fuzziness</a> for more information.'),
    ];
    $form['advanced']['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Index prefix'),
      '#description' => $this->t('Using an index prefix can be useful for using the same server for different projects or environments.'),
      '#default_value' => $this->configuration['advanced']['prefix'] ?? '',
    ];
    $form['advanced']['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Index suffix'),
      '#description' => $this->t('Using an index suffix can be useful for using the same server for different projects or environments.'),
      '#default_value' => $this->configuration['advanced']['suffix'] ?? '',
    ];
    $form['advanced']['synonyms'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Synonyms'),
      '#description' => $this->t('Enter synonyms in <a href=":url">Solr synonyms.txt format</a>', [
        ':url' => 'https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-synonym-tokenfilter.html#_solr_synonyms',
      ]),
      '#default_value' => implode(\PHP_EOL, (array) ($this->configuration['advanced']['synonyms'] ?? [])),
    ];

    return $form;
  }

  /**
   * Builds the backend-specific configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function buildConnectorConfigForm(array &$form, FormStateInterface $form_state) {
    $form['connector_config'] = [];

    $connector_id = $this->configuration['connector'];
    if (isset($connector_id)) {
      $connector = $this->connectorPluginManager->createInstance($connector_id, $this->configuration['connector_config']);
      if ($connector instanceof ElasticSearchConnectorInterface) {
        $form_state->set('connector', $connector_id);
        // Attach the ElasticSearch connector plugin configuration form.
        $connector_form_state = SubformState::createForSubform($form['connector_config'], $form, $form_state);
        $form['connector_config'] = $connector->buildConfigurationForm($form['connector_config'], $connector_form_state);

        // Modify the backend plugin configuration container element.
        $form['connector_config']['#type'] = 'details';
        $form['connector_config']['#title'] = $this->t('Configure %plugin ElasticSearch connector', [
          '%plugin' => $connector->getLabel(),
        ]);
        $form['connector_config']['#description'] = $connector->getDescription();
        $form['connector_config']['#open'] = TRUE;
      }
    }
    $form['connector_config'] += ['#type' => 'container'];
    $form['connector_config']['#attributes'] = [
      'id' => 'elasticsearch-connector-config-form',
    ];
    $form['connector_config']['#tree'] = TRUE;
  }

  /**
   * Handles switching the selected connector plugin.
   */
  public static function buildAjaxConnectorConfigForm(array $form, FormStateInterface $form_state) {
    // The work is already done in form(), where we rebuild the entity according
    // to the current form values and then create the backend configuration form
    // based on that. So we just need to return the relevant part of the form
    // here.
    return $form['backend_config']['connector_config'];
  }

  /**
   * Gets a list of connectors for use in an HTML options list.
   *
   * @return array
   *   An associative array of plugin id => label.
   */
  protected function getConnectorOptions(): array {
    $options = [];
    foreach ($this->connectorPluginManager->getDefinitions() as $plugin_id => $plugin_definition) {
      $options[$plugin_id] = Html::escape($plugin_definition['label']);
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Check if the ElasticSearch connector plugin changed.
    if ($form_state->getValue('connector') != $form_state->get('connector')) {
      $new_connector = $this->connectorPluginManager->createInstance($form_state->getValue('connector'));
      if (!$new_connector instanceof PluginFormInterface) {
        $form_state->setError($form['connector'], $this->t('The connector could not be activated.'));
        return;
      }
      $form_state->setRebuild();
      return;
    }

    // Check before loading the backend plugin so we don't throw an exception.
    $this->configuration['connector'] = $form_state->get('connector');
    $connector = $this->getConnector();
    if (!$connector instanceof PluginFormInterface) {
      $form_state->setError($form['connector'], $this->t('The connector could not be activated.'));
      return;
    }
    $connector_form_state = SubformState::createForSubform($form['connector_config'], $form, $form_state);
    $connector->validateConfigurationForm($form['connector_config'], $connector_form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $values['advanced']['synonyms'] = explode(\PHP_EOL, $form_state->getValue([
      'advanced',
      'synonyms',
    ], ''));
    $this->setConfiguration($values);
    $this->configuration['connector'] = $form_state->getValue('connector');
    $connector = $this->getConnector();
    if ($connector instanceof PluginFormInterface) {
      $connector_form_state = SubformState::createForSubform($form['connector_config'], $form, $form_state);
      $connector->submitConfigurationForm($form['connector_config'], $connector_form_state);
      // Overwrite the form values with type casted values.
      $form_state->setValue('connector_config', $connector->getConfiguration());
    }
  }

  /**
   * Gets the ElasticSearch connector.
   *
   * @return \Drupal\elasticsearch_connector\Connector\ElasticSearchConnectorInterface
   *   The ElasticSearch connector.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Thrown when a plugin error occurs.
   * @throws \Drupal\elasticsearch_connector\Connector\InvalidConnectorException
   *   Thrown when a connector is invalid.
   */
  public function getConnector(): ElasticSearchConnectorInterface {
    $connector = $this->connectorPluginManager->createInstance($this->configuration['connector'], $this->configuration['connector_config']);
    if (!$connector instanceof ElasticSearchConnectorInterface) {
      throw new InvalidConnectorException(sprintf("Invalid connector %s", $this->configuration['connector']));
    }
    return $connector;
  }

  /**
   * Gets the ElasticSearch client.
   *
   * @return \Elastic\Elasticsearch\Client
   *   The ElasticSearch client.
   */
  public function getClient(): Client {
    if (!isset($this->client)) {
      $this->client = $this->getConnector()->getClient();
    }
    return $this->client;
  }

  /**
   * Gets the ElasticSearch Search API client.
   *
   * @return \Drupal\elasticsearch_connector\SearchAPI\BackendClientInterface
   *   The ElasticSearch Search API client.
   */
  public function getBackendClient(): BackendClientInterface {
    if (!isset($this->backendClient)) {
      $settings = [
        'prefix' => $this->getPrefix(),
        'suffix' => $this->getSuffix(),
        'fuzziness' => $this->getFuzziness(),
      ];
      $this->backendClient = $this->backendClientFactory->create($this->getClient(), $settings);
    }
    return $this->backendClient;
  }

  /**
   * Get the configured index prefix.
   *
   * @return string
   *   The configured prefix.
   */
  protected function getPrefix(): string {
    return $this->configuration['advanced']['prefix'] ?? '';
  }

  /**
   * Get the configured index suffix.
   *
   * @return string
   *   The configured suffix.
   */
  protected function getSuffix(): string {
    return $this->configuration['advanced']['suffix'] ?? '';
  }

  /**
   * Get the configured fuzziness value.
   *
   * @return string
   *   The configured fuzziness value.
   */
  public function getFuzziness(): string {
    return $this->configuration['advanced']['fuzziness'] ?? 'auto';
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings(): array {
    $info = [];

    $connector = $this->getConnector();
    $url = $connector->getUrl();
    $info[] = [
      'label' => $this->t('ElasticSearch cluster URL'),
      'info' => Link::fromTextAndUrl($url, Url::fromUri($url)),
    ];

    if ($this->server->status()) {
      // If the server is enabled, check whether ElasticSearch can be reached.
      $ping = $this->server->isAvailable();
      if ($ping) {
        $msg = $this->t('The ElasticSearch cluster was reached successfully');
      }
      else {
        $msg = $this->t('The ElasticSearch cluster could not be reached. Further data is therefore unavailable.');
      }
      $info[] = [
        'label' => $this->t('Connection'),
        'info' => $msg,
        'status' => $ping ? 'ok' : 'error',
      ];
    }

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return $this->traitGetPluginDependencies($this->getConnector());
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    return $this->getBackendClient()->isAvailable();
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index) {
    $this->getBackendClient()->addIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index) {
    $this->getBackendClient()->removeIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {
    $this->getBackendClient()->updateIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items): array {
    return $this->getBackendClient()->indexItems($index, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $item_ids) {
    $this->getBackendClient()->deleteItems($index, $item_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL) {
    $this->getBackendClient()->clearIndex($index, $datasource_id);
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    $this->getBackendClient()->search($query);
  }

  /**
   * {@inheritdoc}
   *
   * Make sure that the client does not get serialized.
   */
  public function __sleep(): array {
    $vars = $this->traitSleep();
    unset($vars[array_search('client', $vars)]);
    return $vars;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Method overriding is to support return types in 10.x. Remove
   * once drupal:10.x support is dropped.
   */
  // phpcs:ignore
  public function __wakeup(): void {
    parent::__wakeup();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDataType($type) {
    if (str_starts_with($type, 'elasticsearch_connector_')) {
      return TRUE;
    }
    $event = new SupportsDataTypeEvent($type);
    $this->eventDispatcher->dispatch($event);
    return $event->isSupported() || parent::supportsDataType($type);
  }

}
