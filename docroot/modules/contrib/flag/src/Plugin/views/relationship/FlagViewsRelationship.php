<?php

namespace Drupal\flag\Plugin\views\relationship;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\flag\FlagServiceInterface;
use Drupal\user\RoleInterface;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a views relationship to select flag content by a flag.
 *
 * @ViewsRelationship("flag_relationship")
 */
class FlagViewsRelationship extends RelationshipPluginBase {

  /**
   * The Page Cache Kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $pageCacheKillSwitch;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a FlagViewsRelationship object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $page_cache_kill_switch
   *   The kill switch.
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, KillSwitch $page_cache_kill_switch, FlagServiceInterface $flag_service, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->flagService = $flag_service;
    $this->pageCacheKillSwitch = $page_cache_kill_switch;
    $this->currentUser = $current_user;
    $this->definition = $plugin_definition + $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $flag_service = $container->get('flag');
    $page_cache_kill_switch = $container->get('page_cache_kill_switch');
    $current_user = $container->get('current_user');
    return new static($configuration, $plugin_id, $plugin_definition, $page_cache_kill_switch, $flag_service, $current_user);
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    $options['flag'] = ['default' => NULL];
    $options['required'] = ['default' => TRUE];
    $options['user_scope'] = ['default' => 'current'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $entity_type = $this->definition['flaggable'];
    $form['admin_label']['admin_label']['#description'] = $this->t('The name of the selected flag makes a good label.');

    $flags = $this->flagService->getAllFlags($entity_type);

    $form['flag'] = [
      '#type' => 'radios',
      '#title' => $this->t('Flag'),
      '#default_value' => $this->options['flag'],
      '#required' => TRUE,
    ];

    foreach ($flags as $flag_id => $flag) {
      $form['flag']['#options'][$flag_id] = $flag->label();
    }

    $form['user_scope'] = [
      '#type' => 'radios',
      '#title' => $this->t('By'),
      '#options' => ['current' => $this->t('Current user'), 'any' => $this->t('Any user')],
      '#default_value' => $this->options['user_scope'],
    ];

    $form['required']['#title'] = $this->t('Include only flagged content');
    $form['required']['#description'] = $this->t('If checked, only content that has this flag will be included. Leave unchecked to include all content; or, in combination with the <em>Flagged</em> filter, <a href="@unflagged-url">to limit the results to specifically unflagged content</a>.', ['@unflagged-url' => 'http://drupal.org/node/299335']);

    if (!$form['flag']['#options']) {
      $form = [
        'error' => [
          '#markup' => '<p class="error form-item">' . $this->t('No %type flags exist. You must first <a href="@create-url">create a %type flag</a> before being able to use this relationship type.', ['%type' => $entity_type, '@create-url' => Url::fromRoute('entity.flag.collection')->toString()]) . '</p>',
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!($flag = $this->getFlag())) {
      return;
    }

    $this->definition['extra'][] = [
      'field' => 'flag_id',
      'value' => $flag->id(),
      'numeric' => TRUE,
    ];

    if ($this->options['user_scope'] == 'current' && !$flag->isGlobal()) {
      $this->definition['extra'][] = [
        'field' => 'uid',
        'value' => '***CURRENT_USER***',
        'numeric' => TRUE,
      ];
      $flag_roles = user_roles(FALSE, "flag " . $flag->id());
      if (isset($flag_roles[RoleInterface::ANONYMOUS_ID]) && $this->currentUser->isAnonymous()) {
        // Disable page caching for anonymous users.
        $this->pageCacheKillSwitch->trigger();

        // Add a condition to the join on the PHP session id for anonymous users.
        $this->definition['extra'][] = [
          'field' => 'session_id',
          'value' => '***FLAG_CURRENT_USER_SID***',
        ];
      }
    }

    parent::query();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    // Relationships need to depend on the flag that creates the relationship.
    $dependencies['config'][] = $this->getFlag()->getConfigDependencyName();
    return $dependencies;
  }

  /**
   * Get the flag of the relationship.
   *
   * @return \Drupal\flag\FlagInterface|null
   *   The flag being selected by in the view.
   */
  public function getFlag() {
    if (empty($this->options['flag'])) {
      return NULL;
    }
    $flag = $this->flagService->getFlagById($this->options['flag']);
    return $flag;
  }

}
