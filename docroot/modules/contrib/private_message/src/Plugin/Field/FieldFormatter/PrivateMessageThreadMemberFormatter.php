<?php

namespace Drupal\private_message\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the private message member field formatter.
 *
 * @FieldFormatter(
 *   id = "private_message_thread_member_formatter",
 *   label = @Translation("Private Message Thread Members"),
 *   field_types = {
 *     "entity_reference"
 *   },
 * )
 */
class PrivateMessageThreadMemberFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * Construct a PrivateMessageThreadFormatter object.
   *
   * @param string $plugin_id
   *   The ID of the plugin.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $settings
   *   The field settings.
   * @param mixed $label
   *   The label of the field.
   * @param string $view_mode
   *   The current view mode.
   * @param array $third_party_settings
   *   The third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    EntityDisplayRepositoryInterface $entity_display_repository,
    RendererInterface $renderer
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): PrivateMessageThreadMemberFormatter {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('entity_display.repository'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return ($field_definition->getFieldStorageDefinition()->getTargetEntityTypeId() == 'private_message_thread' && $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'user');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    if ($this->getSetting('display_type') == 'label') {
      $format = $this->t('Displays members using their username, linked to the user account if the viewer has permission to access user profiles');
    }
    elseif ($this->getSetting('display_type') == 'entity') {
      $format = $this->t('Displays members using the %display_mode display mode of the user entity', ['%display_mode' => $this->getSetting('entity_display_mode')]);
    }

    $summary['format'] = $format;

    $members_prefix = $this->getSetting('members_prefix');
    if (empty($members_prefix)) {
      $summary['field_prefix'] = $this->t('The members list is shown without a prefix');
    }
    else {
      $summary['field_prefix'] = $this->t('The members list is prefixed with the text: %members_prefix.', ['%members_prefix' => $members_prefix]);
    }

    $separator = $this->getSetting('separator');
    if (empty($separator)) {
      $summary['separator'] = $this->t('No separator between the members list.');
    }
    else {
      $summary['separator'] = $this->t('The string "%separator" is used to split the members list.', ['%separator' => $separator]);
    }

    $display_current_user = $this->getSetting('display_current_user');
    if ($display_current_user) {
      $summary['display_current_user'] = $this->t('The current user is displayed.');
    }
    else {
      $summary['display_current_user'] = $this->t('The current user is hidden.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'display_type' => 'label',
      'entity_display_mode' => 'private_message_author',
      'members_prefix' => 'You',
      'separator' => ', ',
      'prefix_separator' => TRUE,
      'display_current_user' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['display_type'] = [
      '#title' => $this->t('Display Type'),
      '#type' => 'select',
      '#options' => [
        'label' => $this->t('Label'),
        'entity' => $this->t('Entity'),
      ],
      '#default_value' => $this->getSetting('display_type'),
      '#ajax' => [
        'wrapper' => 'private_message_thread_member_formatter_settings_wrapper',
        'callback' => [$this, 'ajaxCallback'],
      ],
    ];

    $element['entity_display_mode'] = [
      '#prefix' => '<div id="private_message_thread_member_formatter_settings_wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($this->entityDisplayRepository->getViewModes('user') as $display_mode_id => $display_mode) {
      $options[$display_mode_id] = $display_mode['label'];
    }

    $setting_key = 'display_type';
    if ($value = $form_state->getValue(
      [
        'fields',
        $this->getFieldName(),
        'settings_edit_form',
        'settings',
        $setting_key,
      ])
    ) {
      $display_type = $value;
    }
    else {
      $display_type = $this->getSetting('display_type');
    }

    if ($display_type == 'entity') {
      $element['entity_display_mode']['#type'] = 'select';
      $element['entity_display_mode']['#title'] = $this->t('View mode');
      $element['entity_display_mode']['#options'] = $options;
      $element['entity_display_mode']['#default_value'] = $this->getSetting('entity_display_mode');
    }
    else {
      $element['entity_display_mode']['#markup'] = '';
    }

    $element['members_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field prefix'),
      '#default_value' => $this->getSetting('members_prefix'),
    ];

    $element['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Separator'),
      '#default_value' => $this->getSetting('separator'),
    ];

    $element['prefix_separator'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add separator after prefix'),
      '#escription' => $this->t('No separator will be shown if the prefix is empty.'),
      '#default_value' => $this->getSetting('prefix_separator'),
    ];

    $element['display_current_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display current user'),
      '#default_value' => $this->getSetting('display_current_user'),
    ];

    return $element;
  }

  /**
   * Ajax callback for settings form.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state): array {
    return $form['fields'][$this->getFieldName()]['plugin']['settings_edit_form']['settings']['entity_display_mode'];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $access_profiles = $this->currentUser->hasPermission('access user profiles');
    $users = [];
    $display_current_user = $this->getSetting('display_current_user');

    $view_builder = $this->entityTypeManager->getViewBuilder('user');
    foreach ($items as $delta => $item) {
      $user = $item->entity;
      if ($user) {
        if ($user->id() != $this->currentUser->id() || ($user->id() == $this->currentUser->id() && $display_current_user)) {
          if ($this->getSetting('display_type') == 'label') {
            $url = Url::fromRoute('entity.user.canonical', ['user' => $user->id()]);
            if ($access_profiles && $url->access()) {
              $users[$user->id()] = new FormattableMarkup('<a href=":link">@username</a>', [':link' => $url->toString(), '@username' => $user->getDisplayName()]);
            }
            else {
              $users[$user->id()] = $user->getDisplayName();
            }
          }
          elseif ($this->getSetting('display_type') == 'entity') {
            $renderable = $view_builder->view($user, $this->getSetting('entity_display_mode'));
            $users[$user->id()] = $this->renderer->render($renderable);
          }
        }
      }
      else {
        $users['Missing-' . $delta] = '<em>' . $this->t('User Deleted') . '</em>';
      }
    }

    $element = [
      '#prefix' => '<div class="private-message-recipients">',
      '#suffix' => '</div>',
      '#markup' => '',
    ];

    $separator = $this->getSetting('separator');
    $prefix_separator = $this->getSetting('prefix_separator');

    $members_prefix = $this->getSetting('members_prefix');
    if (strlen($members_prefix)) {
      $first_separator = $prefix_separator && (count($users) > 0) ? $separator : '';
      $element['#markup'] .= '<span>' . $this->t($members_prefix) . $first_separator . '</span>';
    }

    $element['#markup'] .= implode($separator, $users);

    return $element;
  }

  /**
   * Retrieve the name of the field.
   */
  protected function getFieldName(): string {
    return $this->fieldDefinition->getItemDefinition()->getFieldDefinition()->getName();
  }

}
