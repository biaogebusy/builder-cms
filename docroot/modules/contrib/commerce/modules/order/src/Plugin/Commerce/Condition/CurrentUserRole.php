<?php

declare(strict_types=1);

namespace Drupal\commerce_order\Plugin\Commerce\Condition;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\Attribute\CommerceCondition;
use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the current user role condition for orders.
 */
#[CommerceCondition(
  id: "current_user_role",
  label: new TranslatableMarkup("User role"),
  entity_type: "commerce_order",
  category: new TranslatableMarkup("Current request"),
)]
class CurrentUserRole extends ConditionBase implements ContainerFactoryPluginInterface {

  /**
   * The account proxy service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new CurrentUserRole object.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The account proxy service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'matching_strategy' => 'any',
      'roles' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['matching_strategy'] = [
      '#type' => 'radios',
      '#title' => $this->t('Matching strategy'),
      '#default_value' => $this->configuration['matching_strategy'],
      '#options' => [
        'any' => $this->t('User must have any selected role'),
        'all' => $this->t('User must have all selected roles'),
        'none' => $this->t('User must have none of the selected roles'),
      ],
      '#required' => TRUE,
    ];

    $roles = Role::loadMultiple();
    $roles = array_map(fn(RoleInterface $role) => Html::escape($role->label()), $roles);

    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#default_value' => $this->configuration['roles'],
      '#options' => $roles,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    $this->configuration['roles'] = array_filter($values['roles']);
    $this->configuration['matching_strategy'] = $values['matching_strategy'];
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);

    switch ($this->configuration['matching_strategy']) {
      case 'any':
        return (bool) array_intersect($this->configuration['roles'], $this->currentUser->getRoles());

      case 'all':
        return $this->configuration['roles'] === array_intersect($this->configuration['roles'], $this->currentUser->getRoles());

      case 'none':
        return (bool) !array_intersect($this->configuration['roles'], $this->currentUser->getRoles());

    }

    return FALSE;
  }

}
