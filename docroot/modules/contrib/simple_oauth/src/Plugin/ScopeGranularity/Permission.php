<?php

namespace Drupal\simple_oauth\Plugin\ScopeGranularity;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simple_oauth\Attribute\ScopeGranularity;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Plugin\ScopeGranularityBase;
use Drupal\user\PermissionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The permission scope granularity plugin.
 */
#[ScopeGranularity(
  Oauth2ScopeInterface::GRANULARITY_PERMISSION,
  new TranslatableMarkup('Permission'),
)]
class Permission extends ScopeGranularityBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $pluginId,
    array $pluginDefinition,
    protected PermissionHandlerInterface $permissionHandler,
    protected ModuleExtensionList $moduleList,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('user.permissions'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'permission' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfiguration($configuration): void {
    if (empty($configuration['permission'])) {
      throw new PluginException('The "permission" configuration value is required.');
    }

    // Permission needs to exist.
    $permissions = $this->permissionHandler->getPermissions();
    if (!array_key_exists($configuration['permission'], $permissions)) {
      throw new PluginException(sprintf('Permission "%s" is undefined.', $configuration['permission']));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission(string $permission): bool {
    return $this->getConfiguration()['permission'] === $permission;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions(): array {
    return [$this->getConfiguration()['permission']];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['permission'] = [
      '#type' => 'select',
      '#title' => $this->t('Permission'),
      '#options' => $this->getPermissionOptions(),
      '#default_value' => $this->getConfiguration()['permission'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * Gets the permission options.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[][]
   *   Returns the permission options.
   */
  protected function getPermissionOptions(): array {
    $options = [];
    foreach ($this->permissionHandler->getPermissions() as $key => $permission) {
      $provider = $permission['provider'];
      $display_name = $this->moduleList->getName($provider);
      $options[$display_name][$key] = strip_tags($permission['title']);
    }

    return $options;
  }

}
