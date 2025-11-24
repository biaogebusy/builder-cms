<?php

namespace Drupal\jsonapi_extras\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure JSON:API settings for this site.
 */
class JsonapiExtrasSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected $routerBuilder;

  /**
   * Resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $jsonApiResourceRepository;

  /**
   * The dependency injection container.
   *
   * @var \Drupal\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->routerBuilder = $container->get('router.builder');
    $instance->jsonApiResourceRepository = $container->get('jsonapi.resource_type.repository');
    $instance->container = $container;

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['jsonapi_extras.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'jsonapi_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('jsonapi_extras.settings');
    $path_prefix_default_value = $config->get('path_prefix');
    if ($this->isJsonApiBasePathParameterOverrideActive()) {
      $path_prefix_default_value = ltrim($this->container->getParameter('jsonapi.base_path'), '/');
    }

    $form['path_prefix'] = [
      '#title' => $this->t('Path prefix'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#field_prefix' => '/',
      '#description' => $this->t('The path prefix for JSON:API.'),
      '#disabled' => $this->isJsonApiBasePathParameterOverrideActive(),
      '#default_value' => $path_prefix_default_value,
    ];

    if ($this->isJsonApiBasePathParameterOverrideActive()) {
      $form['path_prefix']['#description'] = $this->t('@original <strong>This configuration option is disabled because the JSON:API base path is overridden via the <em>jsonapi.base_path</em> container parameter.</strong>', ['@original' => $form['path_prefix']['#description']]);
    }

    $form['include_count'] = [
      '#title' => $this->t('Include count in collection queries'),
      '#type' => 'checkbox',
      '#description' => $this->t('If activated, all collection responses will return a total record count for the provided query.'),
      '#default_value' => $config->get('include_count'),
    ];

    $form['default_disabled'] = [
      '#title' => $this->t('Disabled by default'),
      '#type' => 'checkbox',
      '#description' => $this->t("If activated, all resource types that don't have a matching enabled resource config will be disabled."),
      '#default_value' => $config->get('default_disabled'),
    ];

    $form['validate_configuration_integrity'] = [
      '#title' => $this->t('Validate config integrity'),
      '#type' => 'checkbox',
      '#description' => $this->t("Enable a configuration validation step for the fields in your resources. This will ensure that new (and updated) fields also contain configuration for the corresponding resources.<br /><strong>IMPORTANT:</strong> disable this <em>temporarily</em> to allow importing incomplete configuration, so you can fix it locally and export complete configuration. Remember to re-enable this after the configuration has been fixed."),
      '#default_value' => $config->get('validate_configuration_integrity'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->isJsonApiBasePathParameterOverrideActive() && ($path_prefix = $form_state->getValue('path_prefix'))) {
      $this->config('jsonapi_extras.settings')
        ->set('path_prefix', trim($path_prefix, '/'))
        ->save();
    }

    $this->config('jsonapi_extras.settings')
      ->set('include_count', $form_state->getValue('include_count'))
      ->set('default_disabled', $form_state->getValue('default_disabled'))
      ->set('validate_configuration_integrity', $form_state->getValue('validate_configuration_integrity'))
      ->save();

    // Rebuild the router.
    $this->routerBuilder->setRebuildNeeded();
    // And the resource-type repository.
    $this->jsonApiResourceRepository->reset();
    Cache::invalidateTags(['jsonapi_resource_types']);

    parent::submitForm($form, $form_state);
  }

  /**
   * Checks if jsonapi.base_path container parameter override is active.
   *
   * @return bool
   *   TRUE if it is active, FALSE otherwise.
   */
  private function isJsonApiBasePathParameterOverrideActive(): bool {
    return $this->container->hasParameter('jsonapi_extras.base_path_override_disabled') && $this->container->getParameter('jsonapi_extras.base_path_override_disabled');
  }

}
