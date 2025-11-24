<?php

declare(strict_types=1);

namespace Drupal\private_message\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\private_message\Model\BlockType;
use Drupal\private_message\PluginManager\PrivateMessageConfigFormManagerInterface;

/**
 * Defines the configuration form for the private message module.
 */
class ConfigForm extends ConfigFormBase {

  use AutowireTrait;

  /**
   * Blocking type: passive.
   *
   * @deprecated in private_message:4.0.0 and is removed from
   *   private_message:5.0.0. Instead, use
   *   \Drupal\private_message\Model\BlockType::Passive.
   *
   * @see https://www.drupal.org/node/3490530
   */
  const PASSIVE = 'passive';

  /**
   * Blocking type: active.
   *
   * @deprecated in private_message:4.0.0 and is removed from
   *   private_message:5.0.0. Instead, use
   *   \Drupal\private_message\Model\BlockType::Active.
   *
   * @see https://www.drupal.org/node/3490530
   */
  const ACTIVE = 'active';

  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected readonly PrivateMessageConfigFormManagerInterface $privateMessageConfigFormManager,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'private_message_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('private_message.settings');

    $form['pm_core'] = [
      '#type' => 'details',
      '#title' => $this->t('Private message core'),
      '#open' => TRUE,
    ];

    $form['pm_core']['notifications'] = [
      '#type' => 'details',
      '#title' => $this->t('Notifications'),
      '#open' => TRUE,
    ];

    $form['pm_core']['notifications']['enable_notifications'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable notifications'),
      '#config_target' => new ConfigTarget(
        configName: 'private_message.settings',
        propertyPath: 'enable_notifications',
        toConfig: fn($value): bool => boolval($value),
      ),
    ];

    $form['pm_core']['notifications']['notify_by_default'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default action'),
      '#options' => [
        $this->t('Do not send notifications (users can opt-in)'),
        $this->t('Send notifications (users can opt-out)'),
      ],
      '#config_target' => new ConfigTarget(
        configName: 'private_message.settings',
        propertyPath: 'notify_by_default',
        fromConfig: fn($value): int => intval($value),
        toConfig: fn($value): bool => boolval($value),
      ),
      '#states' => [
        'visible' => [
          ':input[name="enable_notifications"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['pm_core']['notifications']['notify_when_using'] = [
      '#type' => 'radios',
      '#title' => $this->t('Send notifications of new messages in a thread'),
      '#options' => [
        'yes' => $this->t('For every private message'),
        'no' => $this->t('Only when the user is not viewing the thread'),
      ],
      '#config_target' => 'private_message.settings:notify_when_using',
      '#description' => $this->t("Whether or not notifications should be sent when the user is viewing a given thread. Users will be able to override this value on their profile settings page."),
      '#states' => [
        'visible' => [
          ':input[name="enable_notifications"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['pm_core']['notifications']['number_of_seconds_considered_away'] = [
      '#type' => 'number',
      '#title' => $this->t('The number of seconds after which a user should be considered as not viewing a thread'),
      '#config_target' => new ConfigTarget(
        configName: 'private_message.settings',
        propertyPath: 'number_of_seconds_considered_away',
        toConfig: fn($value): int => intval($value),
      ),
      '#description' => $this->t('When users have a private message thread open, calls to the server update the last time they have accessed the thread. This setting determines how many seconds after they have closed the thread, they should be considered as not accessing the thread anymore. Users will be able to override this value on their profile settings page.'),
      '#states' => [
        'visible' => [
          ':input[name="enable_notifications"]' => ['checked' => TRUE],
          ':input[name="notify_when_using"]' => ['value' => 'no'],
        ],
      ],
    ];

    $form['pm_core']['hide_recipient_field_when_prefilled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide recipient field when recipient is in the URL'),
      '#description' => $this->t('Links can be created to the private message page, passing the recipient in the URL. If this box is checked, the recipient field will be hidden when the recipient is passed in the URL.'),
      '#config_target' => new ConfigTarget(
        configName: 'private_message.settings',
        propertyPath: 'hide_recipient_field_when_prefilled',
        toConfig: fn($value): bool => boolval($value),
      ),
    ];

    $form['pm_core']['autofocus_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable autofocus'),
      '#description' => $this->t('This option allows you to put the autofocus in the message textarea.'),
      '#config_target' => new ConfigTarget(
        configName: 'private_message.settings',
        propertyPath: 'autofocus_enable',
        toConfig: fn($value): bool => boolval($value),
      ),
    ];

    $form['pm_core']['keys_send'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key that sends the message when pressed'),
      '#description' => $this->t(
        'This field allows you to set up some keys that will send the message instead of pressing the submit button. Just enter the <a href="@key-list">KeyboardEvent.key</a> or the deprecated <a href="@keycode-list">KeyboardEvent.keyCode</a> for compatibility. You can separate entrees by a comma in order to support multiple keys. This feature doesn\'t work with wysiwyg, you have to use a simple textarea as a text editor.',
        [
          '@key-list' => 'https://developer.mozilla.org/en-US/docs/Web/API/KeyboardEvent/key/Key_Values',
          '@keycode-list' => 'https://developer.mozilla.org/en-US/docs/Web/API/KeyboardEvent/keyCode',
        ]),
      '#config_target' => 'private_message.settings:keys_send',
    ];

    $form['pm_core']['remove_css'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove the default CSS of the module'),
      '#description' => $this->t('This option can break the features of the module and it is only for developers who want to override the styles more easily.'),
      '#config_target' => new ConfigTarget(
        configName: 'private_message.settings',
        propertyPath: 'remove_css',
        toConfig: fn($value): bool => boolval($value),
      ),
    ];

    $form['pm_labels'] = [
      '#type' => 'details',
      '#title' => $this->t('Private message labels'),
      '#open' => TRUE,
    ];

    $form['pm_labels']['create_message_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Text To Create Private Message"),
      '#config_target' => 'private_message.settings:create_message_label',
    ];

    $form['pm_labels']['save_message_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Text to submit a new message"),
      '#config_target' => 'private_message.settings:save_message_label',
      '#description' => $this->t('The label of the button to send a new message.'),
    ];

    $form['pm_block'] = [
      '#type' => 'details',
      '#title' => $this->t('Private Message Bans'),
      '#open' => TRUE,
    ];

    $form['pm_block']['ban_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t("Blocking mode"),
      '#config_target' => 'private_message.settings:ban_mode',
      '#options' => BlockType::asOptions(),
      BlockType::Passive->value => [
        '#description' => $this->t('Blocked members do not know they are blocked and can message the user that blocked them. The user who blocked them will not see the message.'),
      ],
      BlockType::Active->value => [
        '#description' => $this->t('Blocked members cannot message users that blocked them and instead a message is shown.'),
      ],
    ];

    $form['pm_block']['ban_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Button label to ban a user"),
      '#config_target' => 'private_message.settings:ban_label',
    ];

    $form['pm_block']['unban_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Button label to unban a user"),
      '#config_target' => 'private_message.settings:unban_label',
    ];

    $form['pm_block']['ban_page_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Text to go to the ban page"),
      '#config_target' => 'private_message.settings:ban_page_label',
    ];

    $form['pm_block']['ban_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Text to show when a blocked user tries to send a message."),
      '#config_target' => 'private_message.settings:ban_message',
    ];

    $definitions = $this->privateMessageConfigFormManager->getDefinitions();
    foreach ($definitions as $definition) {
      /** @var \Drupal\private_message\Plugin\PrivateMessageConfigForm\PrivateMessageConfigFormPluginInterface $instance */
      $instance = $this->privateMessageConfigFormManager->createInstance($definition['id']);
      $form[$instance->getPluginId()] = [
        '#type' => 'details',
        '#title' => $instance->getPluginDefinition()['name'] ?? '',
        '#tree' => TRUE,
        '#open' => TRUE,
      ];
      foreach ($instance->buildForm($form_state) as $key => $element) {
        $form[$instance->getPluginId()][$key] = $element;
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $definitions = $this->privateMessageConfigFormManager->getDefinitions();
    foreach ($definitions as $definition) {
      $instance = $this->privateMessageConfigFormManager->createInstance($definition['id']);
      $instance->validateForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $definitions = $this->privateMessageConfigFormManager->getDefinitions();
    foreach ($definitions as $definition) {
      $instance = $this->privateMessageConfigFormManager->createInstance($definition['id']);
      $instance->submitForm($form_state->getValue($instance->getId()));
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'private_message.settings',
    ];
  }

}
