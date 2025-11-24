<?php

namespace Drupal\elasticsearch_connector\Plugin\ElasticSearch\Connector;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\elasticsearch_connector\Connector\ElasticSearchConnectorInterface;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a standard ElasticSearch connector.
 *
 * @ElasticSearchConnector(
 *   id = "standard",
 *   label = @Translation("Standard"),
 *   description = @Translation("A standard connector without authentication")
 * )
 */
class StandardConnector extends PluginBase implements ElasticSearchConnectorInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a new StandardConnector.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.elasticsearch_connector_client')
    );
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
    return (string) $this->configuration['url'];
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
    // We only support one host.
    $clientBuilder = ClientBuilder::create()
      ->setHosts([$this->configuration['url']]);

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
      '#title' => $this->t('ElasticSearch URL'),
      '#description' => $this->t('The URL of your ElasticSearch server, e.g. <code>http://127.0.0.1</code> or <code>https://www.example.com:443</code>.'),
      '#default_value' => $this->configuration['url'] ?? '',
      '#required' => TRUE,
    ];

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
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('url');
    if (!UrlHelper::isValid($url)) {
      $form_state->setErrorByName('url', $this->t("Invalid URL"));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['url'] = trim($form_state->getValue('url'), '/ ');
    $this->configuration['enable_debug_logging'] = (bool) $form_state->getValue('enable_debug_logging');
  }

}
