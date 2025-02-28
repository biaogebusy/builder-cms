<?php

namespace Drupal\jsonapi_extras\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\ProxyClass\Routing\RouteBuilder;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
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
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\ProxyClass\Routing\RouteBuilder $router_builder
   *   The router builder to rebuild menus after saving config entity.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $jsonApiResourceRepository
   *   Resource type repository.
   * @param \Drupal\Component\DependencyInjection\ContainerInterface|null $container
   *   The dependency injection container.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RouteBuilder $router_builder, ResourceTypeRepositoryInterface $jsonApiResourceRepository, ContainerInterface $container = NULL) {
    parent::__construct($config_factory);
    $this->routerBuilder = $router_builder;
    $this->jsonApiResourceRepository = $jsonApiResourceRepository;
    if ($container === NULL) {
      $container = \Drupal::getContainer();
      @trigger_error('Calling ' . __METHOD__ . ' without the $container argument is deprecated in jsonapi_extras:8.x-3.24 and will be required in jsonapi_extras:8.x-4.0. See https://www.drupal.org/node/3384627', E_USER_DEPRECATED);
    }
    $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('router.builder'),
      $container->get('jsonapi.resource_type.repository'),
      $container
    );
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
