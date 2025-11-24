<?php

namespace Drupal\elasticsearch_connector\Plugin\ElasticSearch\Connector;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\elasticsearch_connector\Connector\ElasticSearchConnectorInterface;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a connector for an Elastic Cloud Endpoint.
 *
 * @ElasticSearchConnector(
 *   id = "elastic_cloud_endpoint",
 *   label = @Translation("Elastic Cloud Endpoint"),
 *   description = @Translation("Connect to Elasticsearch B.V.’s official Elastic Cloud with an Elasticsearch endpoint and API key."),
 * )
 */
class ElasticCloudEndpointConnector extends PluginBase implements ElasticSearchConnectorInterface, ContainerFactoryPluginInterface {

  /**
   * A repository for Key configuration entities.
   *
   * Note that we are intentionally NOT setting a typehint for this variable,
   * because doing so would introduce a required dependency on the Key module.
   * However, we want an OPTIONAL dependency on the Key module.
   *
   * @var \Drupal\key\KeyRepositoryInterface|null
   */
  protected $keyRepository;

  /**
   * A logging channel to use when 'enable_debug_logging' is enabled.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.channel.elasticsearch_connector_client');

    // If the key module is installed, then a 'key.repository' service will be
    // available: if so, set that.
    if ($container->has('key.repository')) {
      $instance->keyRepository = $container->get('key.repository');
    }

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(): string {
    return $this->configuration['url'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(): Client {
    $clientBuilder = ClientBuilder::create()
      ->setHosts([$this->configuration['url']]);

    if ($this->keyRepositoryIsValid()) {
      $apiKey = $this->keyRepository->getKey($this->configuration['api_key_id'])
        ?->getKeyValue();
      $clientBuilder->setApiKey($apiKey);
    }

    if ($this->configuration['enable_debug_logging']) {
      $clientBuilder->setLogger($this->logger);
    }

    return $clientBuilder->build();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'api_key_id' => '',
      'url' => '',
      'enable_debug_logging' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Elasticsearch endpoint'),
      '#description' => $this->t("Your Hosted deployment's <em>Elasticsearch endpoint</em>, which looks like a URL."),
      '#default_value' => $this->configuration['url'] ?? '',
      '#required' => TRUE,
    ];

    // If the key repository is valid, then we can assume the key module is
    // installed, and present a key-selection widget.
    if ($this->keyRepositoryIsValid()) {
      $form['api_key_id'] = [
        '#type' => 'key_select',
        '#title' => $this->t('Elastic Cloud API key'),
        '#default_value' => $this->configuration['api_key_id'] ?? '',
        '#required' => TRUE,
        '#description' => $this->t('At minimum, this API key needs the following security privileges... <pre>@minimum_security_privileges</pre> ...where <code>@role_name</code> is a unique role name; and <code>@index_1_name</code>, and <code>@index_2_name</code> are the machine names of the Indices you create on the server.', [
          '@minimum_security_privileges' => '{"role-a": {"indices": [{"names": ["INDEX_1", "INDEX_2"], "privileges": ["all", "create_index", "delete_index", "maintenance"], "allow_restricted_indices": false}]}}',
          '@role_name' => 'role-a',
          '@index_1_name' => 'INDEX_1',
          '@index_2_name' => 'INDEX_2',
        ]),
      ];
    }
    // If the key repository is not valid, then the key module is probably not
    // installed. This plugin won't work without it, so display an error
    // message.
    else {
      $form['no_key_module_message'] = [
        '#theme' => 'status_messages',
        '#status_headings' => [
          MessengerInterface::TYPE_ERROR => $this->t('Key module missing'),
        ],
        '#message_list' => [
          MessengerInterface::TYPE_ERROR => [
            $this->t("You must install <a href='@key_module_url'>Drupal's Key module</a> to use this authentication type! Please ensure that the Key module is downloaded and enabled.", [
              '@key_module_url' => 'https://www.drupal.org/project/key',
            ]),
          ],
        ],
      ];
    }

    $form['enable_debug_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging mode: log ElasticSearch network traffic'),
      '#description' => $this->t("This will write requests, responses, and response-time information to Drupal's log, which may help you diagnose problems with Drupal's connection to ElasticSearch.<p><strong>Warning</strong>: This setting will result in poor performance and may log a user’s personally identifiable information. This setting is only intended for temporary use and should be disabled when you finish debugging. Logs written while this mode is active will remain in the log until you clear them or the logs are rotated.</p>"),
      '#default_value' => $this->configuration['enable_debug_logging'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['api_key_id'] = \trim($form_state->getValue('api_key_id'));
    $this->configuration['url'] = \trim($form_state->getValue('url'));
    $this->configuration['enable_debug_logging'] = (bool) $form_state->getValue('enable_debug_logging');
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('url');
    if (!UrlHelper::isValid($url)) {
      $form_state->setErrorByName('url', $this->t("Invalid Elasticsearch endpoint"));
    }
  }

  /**
   * Determine if the key repository is valid, implying key module is installed.
   *
   * @return bool
   *   TRUE if the keyRepository service injected into this plugin is of type
   *   \Drupal\key\KeyRepositoryInterface; FALSE otherwise.
   */
  protected function keyRepositoryIsValid(): bool {
    // Note that we are intentionally NOT using KeyRepositoryInterface::class
    // here, because doing so would introduce a required dependency on the Key
    // module. However, we want an OPTIONAL dependency on the Key module.
    return \is_a($this->keyRepository, 'Drupal\key\KeyRepositoryInterface', TRUE);
  }

}
