<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\ClientAuthorization;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entity_share_client\Service\KeyProvider;
use Drupal\Core\Http\ClientFactory;

/**
 * Base class for Client authorization plugins.
 */
abstract class ClientAuthorizationPluginBase extends PluginBase implements ClientAuthorizationInterface, ContainerFactoryPluginInterface {

  /**
   * Injected key service.
   *
   * @var \Drupal\entity_share_client\Service\KeyProvider
   */
  protected $keyService;

  /**
   * The key value store to use.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValueStore;

  /**
   * Injected UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * Injected HTTP client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    KeyProvider $keyProvider,
    KeyValueFactoryInterface $key_value_factory,
    UuidInterface $uuid,
    ClientFactory $clientFactory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->keyService = $keyProvider;
    $this->keyValueStore = $key_value_factory->get(ClientAuthorizationInterface::LOCAL_STORAGE_KEY_VALUE_COLLECTION);
    $this->uuid = $uuid;
    $this->httpClientFactory = $clientFactory;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_share_client.key_provider'),
      $container->get('keyvalue'),
      $container->get('uuid'),
      $container->get('http_client_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'uuid' => $this->uuid->generate(),
      'data' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCredentialProvider() {
    $configuration = $this->getConfiguration();
    return $configuration['data']['credential_provider'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageKey() {
    $configuration = $this->getConfiguration();
    return $configuration['data']['storage_key'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form + [
      'credential_provider' => [
        '#type' => 'hidden',
        '#value' => 'entity_share',
      ],
      'entity_share' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Stored locally'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (empty($values['credential_provider'])) {
      $form_state->setError(
        $form['credential_provider'],
        'A credential provider is required.'
      );
    }
    else {
      $provider = $values['credential_provider'];
      foreach ($values[$provider] as $key => $value) {
        if (empty($value)) {
          $form_state->setError(
            $form[$provider][$key],
            'All credential values are required.'
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $key = NULL;
    $values = $form_state->getValues();
    $configuration = $this->getConfiguration();
    $provider = $values['credential_provider'];
    $credentials = $values[$provider];
    switch ($provider) {
      case 'entity_share':
        $this->keyValueStore->set($configuration['uuid'], $credentials);
        $key = $configuration['uuid'];
        break;

      case 'key':
        $this->keyValueStore->delete($configuration['uuid']);
        $key = $credentials['id'];
        break;

    }
    $configuration['data'] = [
      'credential_provider' => $provider,
      'storage_key' => $key,
    ];
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * Helper method to build the credential provider elements of the form.
   *
   * @param array $form
   *   The configuration form.
   */
  protected function expandedProviderOptions(array &$form) {
    $provider = $this->getCredentialProvider();
    // Provide selectors for the api key credential provider.
    $form['credential_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Credential provider'),
      '#default_value' => empty($provider) ? 'entity_share' : $provider,
      '#options' => [
        'entity_share' => $this->t('Local storage'),
        'key' => $this->t('Key module'),
      ],
      '#attributes' => [
        'data-states-selector' => 'provider',
      ],
      '#weight' => -99,
    ];
    $form['entity_share']['#states'] = [
      'required' => [
        ':input[data-states-selector="provider"]' => ['value' => 'entity_share'],
      ],
      'visible' => [
        ':input[data-states-selector="provider"]' => ['value' => 'entity_share'],
      ],
      'enabled' => [
        ':input[data-states-selector="provider"]' => ['value' => 'entity_share'],
      ],
    ];
    $key_id = $provider == 'key' ? $this->getStorageKey() : '';
    $form['key'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Managed by the Key module'),
      '#states' => [
        'required' => [
          ':input[data-states-selector="provider"]' => ['value' => 'key'],
        ],
        'visible' => [
          ':input[data-states-selector="provider"]' => ['value' => 'key'],
        ],
        'enabled' => [
          ':input[data-states-selector="provider"]' => ['value' => 'key'],
        ],
      ],
    ];
    $form['key']['id'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Select a stored Key'),
      '#default_value' => $key_id,
      '#empty_option' => $this->t('- Please select -'),
    ];
  }

}
