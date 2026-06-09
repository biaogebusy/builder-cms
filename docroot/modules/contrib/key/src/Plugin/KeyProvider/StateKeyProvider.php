<?php

namespace Drupal\key\Plugin\KeyProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyProviderBase;
use Drupal\key\Plugin\KeyPluginFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A key provider that allows a key to be stored in Drupal's state system.
 *
 * The state system stores values in the database, so keys stored this way
 * should be considered to have the same security properties as database-stored
 * configuration. This provider is suitable for development keys, API tokens
 * with limited permissions, or other keys where database storage is acceptable.
 *
 * @KeyProvider(
 *   id = "state",
 *   label = @Translation("State"),
 *   description = @Translation("The State key provider allows a key to be retrieved from Drupal state."),
 *   tags = {
 *     "state",
 *   },
 *   key_value = {
 *     "accepted" = FALSE,
 *     "required" = FALSE
 *   }
 * )
 */
class StateKeyProvider extends KeyProviderBase implements KeyPluginFormInterface {

  /**
   * Drupal state system.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['state_key' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['state_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('State key'),
      '#description' => $this->t('Name of the state variable.'),
      '#required' => TRUE,
      '#default_value' => $this->getConfiguration()['state_key'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $key_provider_settings = $form_state->getValues();
    $state_variable = $key_provider_settings['state_key'];
    $key_value = $this->state->get($state_variable);

    // Does the state variable exist.
    if (!$key_value) {
      $form_state->setErrorByName('state_key', $this->t('The state variable does not exist or it is empty.'));
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValue(KeyInterface $key) {
    return $this->state->get($this->configuration['state_key']);
  }

}
