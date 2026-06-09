<?php

namespace Drupal\ai\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
use Http\Discovery\Exception\NotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Model Assignment form.
 */
class AiModelSettingsForm extends FormBase {

  /**
   * The ai provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor.
   */
  final public function __construct(AiProviderPluginManager $provider_manager, ConfigFactoryInterface $config_factory) {
    $this->providerManager = $provider_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_model_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $operation_type = NULL, $provider = NULL, $model_id = NULL) {
    if (!$operation_type || !$provider) {
      throw new NotFoundException('Operation type and provider are required.');
    }

    // Try loading the provider.
    try {
      $provider = $this->providerManager->createInstance($provider);
    }
    catch (\Exception $e) {
      throw new NotFoundException('Provider not found.');
    }
    $form = $provider->loadModelsForm($form, $form_state, $operation_type, $model_id);
    $form['provider'] = [
      '#type' => 'value',
      '#value' => $provider->getPluginId(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate the model assignment.
    $values = $form_state->getValues();
    $provider = $this->providerManager->createInstance($values['provider']);
    $provider->validateModelsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Check trigger if its delete.
    $values = $form_state->getValues();
    $config = $this->configFactory()->getEditable('ai.settings')->get('models');
    if ($form_state->getTriggeringElement()['#id'] == 'edit-delete') {
      unset($config[$values['provider']][$values['operation_type']][$values['model_id']]);
      $this->configFactory()->getEditable('ai.settings')->set('models', $config)->save();
      return;
    }

    // Unset the following.
    foreach (['submit', 'delete', 'form_build_id', 'form_token', 'form_id', 'op', 'base_on'] as $key) {
      unset($values[$key]);
    }
    if (empty($values['label'])) {
      $values['label'] = $values['model_id'];
    }
    $config[$values['provider']][$values['operation_type']][$values['model_id']] = $values;
    $this->configFactory()->getEditable('ai.settings')->set('models', $config)->save();
  }

  /**
   * The create title callback.
   *
   * @param string $operation_type
   *   The operation type.
   * @param string $provider
   *   The provider.
   * @param string $model_id
   *   The model id.
   *
   * @return string
   *   The title.
   */
  public static function createTitle($operation_type, $provider, $model_id = NULL) {
    if ($model_id === NULL) {
      return t('Create @operation_type Model for provider @provider', [
        '@operation_type' => ucfirst($operation_type),
        '@provider' => ucfirst($provider),
      ]);
    }
    return t('Edit @model_id as @operation_type Model for provider @provider', [
      '@operation_type' => ucfirst($operation_type),
      '@provider' => ucfirst($provider),
      '@model_id' => $model_id,
    ]);
  }

}
