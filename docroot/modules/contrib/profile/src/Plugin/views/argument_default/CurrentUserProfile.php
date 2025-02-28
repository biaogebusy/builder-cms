<?php

namespace Drupal\profile\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default argument plugin to extract the current user's profile.
 *
 * @ViewsArgumentDefault(
 *   id = "current_user_profile",
 *   title = @Translation("Profile ID from logged in user")
 * )
 */
class CurrentUserProfile extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['type'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Generate an option for each profile type which does not allow multiple.
    $types = [];
    foreach ($this->entityTypeManager->getStorage('profile_type')->loadMultiple() as $profile_type) {
      /** @var \Drupal\profile\Entity\ProfileTypeInterface $profile_type */
      if ($profile_type->allowsMultiple()) {
        continue;
      }
      $types[$profile_type->id()] = $profile_type->label();
    }
    asort($types);
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Profile type'),
      '#default_value' => $this->options['type'],
      '#options' => $types,
      '#description' => $this->t("Select the profile type to use. If a profile type is configured to support multiple profiles, it won't show up here as an option."),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    /** @var \Drupal\profile\Entity\ProfileInterface|bool $profile */
    if ($profile = $this->entityTypeManager->getStorage('profile')->loadByUser($this->currentUser, $this->options['type'])) {
      return $profile->id();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user'];
  }

}
