<?php

namespace Drupal\private_message\Plugin\Field\FieldFormatter;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Defines the private message thread message field formatter.
 *
 * @FieldFormatter(
 *   id = "private_message_thread_message_formatter",
 *   label = @Translation("Private Message Messages"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class PrivateMessageThreadMessageFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

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
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected CsrfTokenGenerator $csrfTokenGenerator;

  /**
   * The user manager service.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected UserStorageInterface $userManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $config;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfTokenGenerator
   *   The CSRF token generator.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
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
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    CsrfTokenGenerator $csrfTokenGenerator,
    ConfigFactoryInterface $configFactory,
    EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->csrfTokenGenerator = $csrfTokenGenerator;
    $this->config = $configFactory->get('private_message.settings');
    $this->userManager = $entityTypeManager->getStorage('user');
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): PrivateMessageThreadMessageFormatter {
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
      $container->get('csrf_token'),
      $container->get('config.factory'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return ($field_definition->getFieldStorageDefinition()->getTargetEntityTypeId() == 'private_message_thread' && $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'private_message');
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'message_count' => 5,
      'ajax_previous_load_count' => 5,
      'message_order' => 'asc',
      'ajax_refresh_rate' => 20,
      'view_mode' => 'default',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $settings = $this->getSettings();

    $summary['message_count'] = $this->t('Number of threads to show on load: @count', ['@count' => $settings['message_count']]);
    $summary['ajax_previous_load_count'] = $this->t('Number of threads to show when clicking load previous: @count', ['@count' => $settings['ajax_previous_load_count']]);
    $summary['message_order'] = $this->t('Order of messages: @order', ['@order' => $this->translateKey('order', $settings['message_order'])]);
    if ($settings['ajax_refresh_rate']) {
      $summary['ajax_refresh_rate'] = $this->t('Ajax refresh rate: @count seconds', ['@count' => $settings['ajax_refresh_rate']]);
    }
    else {
      $summary['ajax_refresh_rate'] = $this->t('Ajax refresh rate: Ajax refresh disabled');
    }

    $summary['view_mode'] = $this->t('Private Message View Mode: @view_mode', ['@view_mode' => $settings['view_mode']]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['message_count'] = [
      '#title' => $this->t('Message Count'),
      '#type' => 'number',
      '#default_value' => $this->getSetting('message_count'),
      '#description' => $this->t('The number of messages to display on load'),
    ];

    $element['ajax_previous_load_count'] = [
      '#title' => $this->t('Load Previous Ajax Count'),
      '#type' => 'number',
      '#default_value' => $this->getSetting('ajax_previous_load_count'),
      '#description' => $this->t('The number of previous messages to load using ajax when clicking the load previous link'),
    ];

    $element['ajax_refresh_rate'] = [
      '#title' => $this->t('Ajax refresh rate'),
      '#type' => 'number',
      '#default_value' => $this->getSetting('ajax_refresh_rate'),
      '#description' => $this->t('The number of seconds between checks for new messages. Set to zero to disable. Note that a lower number will cause more requests, use more bandwidth, and cause more strain on the server. As such, it is not recommended to set a value lower than five (5) seconds.'),
    ];

    $element['message_order'] = [
      '#type' => 'radios',
      '#title' => $this->t('Message direction'),
      '#options' => [
        'asc' => $this->translateKey('order', 'asc'),
        'desc' => $this->translateKey('order', 'desc'),
      ],
      '#description' => $this->t('Whether to show messages first to last, or last to first'),
      '#default_value' => $this->getSetting('message_order'),
    ];

    $element['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Private Message view mode'),
      '#options' => $this->entityDisplayRepository->getViewModeOptions('private_message', TRUE),
      '#default_value' => $this->getSetting('view_mode'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $private_message_thread = $items->getEntity();

    $element = [
      '#prefix' => '<div class="private-message-thread-messages">',
      '#suffix' => '</div>',
    ];

    $view_builder = $this->entityTypeManager->getViewBuilder('private_message');

    $user = $this->userManager->load($this->currentUser->id());
    $messages = $private_message_thread->filterUserDeletedMessages($user);
    $total = count($messages);
    $messages = array_slice($messages, -1 * $this->getSetting('message_count'));

    foreach ($messages as $message) {
      $element[$message->id()] = $view_builder->view($message, $this->getSetting('view_mode'));
    }

    if ($this->getSetting('message_order') == 'desc') {
      $element = array_reverse($element);
    }

    $new_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_new_messages']);
    $token = $this->csrfTokenGenerator->get($new_url->getInternalPath());
    $new_url->setOptions(['query' => ['token' => $token]]);

    $prev_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_old_messages']);
    $token = $this->csrfTokenGenerator->get($prev_url->getInternalPath());
    $prev_url->setOptions(['query' => ['token' => $token]]);

    $load_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'load_thread']);
    $load_token = $this->csrfTokenGenerator->get($load_url->getInternalPath());
    $load_url->setOptions(['query' => ['token' => $load_token]]);

    $element['#attached']['drupalSettings']['privateMessageThread'] = [
      'threadId' => (int) $private_message_thread->id(),
      'newMessageCheckUrl' => $new_url->toString(),
      'previousMessageCheckUrl' => $prev_url->toString(),
      'messageOrder' => $this->getSetting('message_order'),
      'refreshRate' => $this->getSetting('ajax_refresh_rate') * 1000,
      'loadThreadUrl' => $load_url->toString(),
      'previousLoadCount' => $this->getSetting('ajax_previous_load_count'),
      'messageCount' => $this->getSetting('message_count'),
      'messageTotal' => $total,
    ];

    $element['#attached']['library'][] = 'private_message/private_message_thread_script';
    $style_disabled = $this->config->get('remove_css');
    if (!$style_disabled) {
      $element['#attached']['library'][] = 'private_message/private_message_thread_style';
    }

    return $element;
  }

  /**
   * Translates a given key.
   *
   * @param string $type
   *   The type of string being translated.
   * @param string $value
   *   The value to be translated.
   *
   * @return mixed
   *   - If a translated value exists for the given type/value combination, a
   *     \Drupal\Core\StringTranslation\TranslatableMarkup object containing the
   *     translated value is returned.
   *   - If only the type exists, but not the value, the untranslated value as
   *     a string is returned.
   *   - If the type does not exist, the untranslated value is returned.
   */
  private function translateKey($type, $value) {
    if ($type == 'order') {
      $keys = [
        'asc' => $this->t('Ascending'),
        'desc' => $this->t('Descending'),
      ];

      return $keys[$value] ?? $value;
    }

    return $value;
  }

}
