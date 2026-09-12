<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Form;

use Drupal\ckeditor5_premium_features\Form\SharedBuildConfigFormBase;
use Drupal\ckeditor5_premium_features\Utility\CollaborationModuleIntegrator;
use Drupal\ckeditor5_premium_features\Utility\MentionsIntegrator;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryDefault;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryPluginManager;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationSenderPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\FilterFormatInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the configuration form of the "Export to Word" feature.
 */
class SettingsForm extends SharedBuildConfigFormBase {

  const NOTIFICATION_CONFIG = 'ckeditor5_premium_features_notifications.settings';

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryPluginManager $messageFactoryPluginManager
   *   Notification message factory manager.
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationSenderPluginManager $senderPluginManager
   *   Notification sender plugin manager.
   * @param \Drupal\ckeditor5_premium_features\Utility\MentionsIntegrator $mentionsIntegrator
   *   Mentions integrator service.
   */
  public function __construct(ConfigFactoryInterface $configFactory,
                              TypedConfigManagerInterface $typedConfigManager,
                              protected NotificationMessageFactoryPluginManager $messageFactoryPluginManager,
                              protected NotificationSenderPluginManager $senderPluginManager,
                              protected MentionsIntegrator $mentionsIntegrator,
                              protected CollaborationModuleIntegrator $collaborationModuleIntegrator) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('plugin.manager.notification_message_factory'),
      $container->get('plugin.manager.notification_sender'),
      $container->get('ckeditor5_premium_features.mention_integrator'),
      $container->get('ckeditor5_premium_features.collaboration_module_integrator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor5_premium_features_notifications_settings';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSettingsRouteName(): string {
    return 'ckeditor5_premium_features_notifications.form.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigId(): string {
    return self::NOTIFICATION_CONFIG;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form = parent::buildForm($form, $form_state);

    $config = $this->config(self::NOTIFICATION_CONFIG);

    // Collect plugins information.
    $messageFactoryDefinitions = $this->messageFactoryPluginManager->getDefinitions();
    $senderDefinitions = $this->senderPluginManager->getDefinitions();

    $form['message_factory_plugin'] = [
      '#type' => 'select',
      '#title' => 'Message content factory',
      '#description' => $this->t('Choose the plugin responsible for providing the notification messages templates.'),
      '#options' => array_map(function ($value) {
        return $value['label'];
      }, $messageFactoryDefinitions),
      '#default_value' => $config->get('message_factory_plugin'),
    ];
    $form['sender_plugin'] = [
      '#type' => 'select',
      '#title' => 'Message sender',
      '#description' => $this->t('Choose the plugin responsible for sending the notification messages.'),
      '#options' => array_map(function ($value) {
        return $value['label'];
      }, $senderDefinitions),
      '#default_value' => $config->get('sender_plugin'),
    ];

    $form['sender_bulk_interval'] = [
      '#type' => 'number',
      '#title' => 'Bulk message sending interval',
      '#description' => $this->t('Set the time interval (in minutes) that must elapse before sending the notification to the user.'),
      '#min' => 0,
      '#default_value' => $config->get('sender_bulk_interval') ?? 0,
      '#states' => [
        'visible' => [
          ':input[name="sender_plugin"]' => ['value' => 'ck5_notifications_email_bulk'],
        ],
      ],
    ];

    // @TODO Some label change would be in order as we have instant sender which have option for more instant sending :)
    $form['instant_comment_notifications'] = [
      '#type' => 'checkbox',
      '#title' => 'Instant comment notifications',
      '#description' => $this->t('When selected notifications for new comments will be sent just after comment is submitted, without waiting for entity save.'),
      '#default_value' => $config->get('instant_comment_notifications') ?? 0,
      '#states' => [
        'visible' => [
          ':input[name="sender_plugin"]' => ['value' => 'ck5_notifications_email_instant'],
        ],
      ],
      '#access' => $this->collaborationModuleIntegrator->isRtcEnabled(),
    ];

    $form = $this->addNotificationMessagesTabs($form, $form_state);

    $form['additional_info'] = [
      [
        '#markup' => 'The "Message body" field supports tokens that will be dynamically replaced by corresponding values.
      Ckeditor5_premium_features module adds a special token, that is design to store notification context: <br />',
      ],
      'list1' => [
        '#theme' => 'item_list',
        '#items' => [
          [
            '#markup' => '[ckeditor5_premium_notification:context]',
          ],
        ],
      ],
      [
        '#markup' => 'System tokens currently supported, relate to Node and User entities, for example [node:title], [node:url], [user:name].
      For more entities, please check the two sample lists below:',
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => [
          [
            '#markup' => '<a href="https://www.drupal.org/node/390482#token-node">Node tokens</a>',
          ],
          [
            '#markup' => '<a href="https://www.drupal.org/node/390482#drupal7tokenslist-token-user">User tokens</a>',
          ],
        ],
      ],
    ];

    $form['#submit'][] = '::checkInstantSendingValue';

    return $form;
  }

  /**
   * Ensures instant RTC sending checkbox is FALSE when RTC is disabled or
   * bulk sending plugin is selected.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function checkInstantSendingValue(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    if (!$this->collaborationModuleIntegrator->isRtcEnabled()
      || $values['sender_plugin'] !== 'ck5_notifications_email_instant') {
      $values['instant_comment_notifications'] = 0;

      $form_state->setValues($values);
    }
  }

  /**
   * Adds from elements for configuring message templates.
   */
  protected function addNotificationMessagesTabs($form) {
    $config = $this->config(self::NOTIFICATION_CONFIG);
    $form['verticaltabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Message types configuration'),
    ];

    $supportedMessageTypes = $this->getSupportedMessageTypes();

    foreach ($supportedMessageTypes as $messageType => $messageTitle) {
      $groupKey = $messageType . '__tab';
      // Create a grouping element using a fieldset.
      $form[$groupKey] = [
        '#type' => 'details',
        '#title' => $messageTitle,
        '#group' => 'verticaltabs',
      ];

      $form[$groupKey][$messageType . '__enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable'),
        '#description' => $this->t('Decide whether your system should support this type of notification.'),
        '#default_value' => $config->get($messageType . '__enabled'),
      ];

      $visibility = [
        '#states' => [
          'visible' => [
            'input[name="' . $messageType . '__enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form[$groupKey][$messageType . '__subject'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Subject'),
        '#description' => $this->t('Subject of the email that will be sent to users.'),
        '#default_value' => $config->get($messageType . '__subject') ?? $this->getPredefinedTitle($messageType),
      ] + $visibility;

      $messageConfig = $config->get($messageType . '__message');
      $form[$groupKey][$messageType . '__message'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Message body'),
        '#description' => $this->t('Body of the message sent to the users that collaborated on the updated node.'),
        '#default_value' => $messageConfig['value'] ?? $this->getPredefinedBodyMessage($messageType),
        '#format' => $messageConfig['format'] ?? $this->getTextFormatId('full_html'),
      ] + $visibility;

      if ($additional = $this->getNotificationAdditionalInstruction($messageType)) {
        $form[$groupKey][$messageType . '__additional_help'] = $additional + $visibility;
      }
    }

    return $form;
  }

  /**
   * Returns additional description specific for passed message type.
   *
   * @param string $messageType
   *   Type of message.
   *
   * @return array
   *   Render array with additional info.
   */
  protected function getNotificationAdditionalInstruction(string $messageType): array {
    return match ($messageType) {
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_STATUS => [
        '#type' => 'container',
        'intro' => [
          '#markup' => 'In this notification, you can use additional tokens with suggestion status:',
        ],
        'list' => [
          '#theme' => 'item_list',
          '#items' => [
            [
              '#markup' => '[suggestion:status] - replaced by system event key.',
            ],
            [
              '#markup' => '[suggestion:status-label] - replaced by translatable event user friendly label.',
            ],
          ],
        ],
      ],
      default => [],
    };
  }

  /**
   * Get predefined title for the notification message.
   *
   * @param string $messageType
   *   Type of message.
   *
   * @return string
   *   Predefined title.
   */
  protected function getPredefinedTitle(string $messageType): String {
    return match ($messageType) {
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_DEFAULT => 'The document was updated.',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_MENTION_COMMENT => 'You were mentioned in a comment.',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_MENTION_DOCUMENT => 'You were mentioned in a document body.',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_COMMENT_ADDED => 'New comment added.',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_THREAD_REPLY => 'A new Reply to a thread.',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_REPLY => 'A new Reply to a suggestion.',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_STATUS => 'Your suggestion status changed.',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_ADDED => 'New Suggestion added.',
      default => 'CKEditor5 notification',
    };
  }

  /**
   * Get predefined body for the notification message.
   *
   * @param string $messageType
   *   Type of message.
   *
   * @return string
   *   Predefined body.
   */
  protected function getPredefinedBodyMessage(string $messageType): String {
    return match ($messageType) {
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_DEFAULT => '<h3>Update notification</h3>
        <p>
          Changes were made to the document <a href="[node:url]"><strong>[node:title]</strong></a> by [user:name] (at [node:changed])
        </p>
        <p>
            [ckeditor5_premium_notification:context]
        </p>
        <p>
          Best regards,
        </p>',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_MENTION_COMMENT => '<h3>You were mentioned in a comment</h3>
        <p>
          User [user:name] mentioned you in the <a href="[node:url]"><strong>[node:title]</strong></a> document (at [node:changed])
        </p>
        <p>
            [ckeditor5_premium_notification:context]
        </p>
        <p>
          Best regards,
        </p>',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_MENTION_DOCUMENT => '<h3>You were mentioned in a document body</h3>
        <p>
          User [user:name] mentioned you in the [node:title] document (at [node:changed])
        </p>
        <p>
            [ckeditor5_premium_notification:context]
        </p>
        <p>
          Best regards,
        </p>',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_COMMENT_ADDED => '<h3>New comment added</h3>
        <p>
          User [user:name] added new comment in the [node:title] document (at [node:changed])
        </p>
        <p>
            [ckeditor5_premium_notification:context]
        </p>
        <p>
          Best regards,
        </p>',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_THREAD_REPLY => '<h3>A new Reply to a thread</h3>
        <p>
          User [user:name] replied to one of your threads in the [node:title] document (at [node:changed])
        </p>
        <p>
            [ckeditor5_premium_notification:context]
        </p>
        <p>
          Best regards,
        </p>',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_REPLY => '<h3>A new Reply to a suggestion</h3>
        <p>
          User [user:name] replied to one of your suggestion in the [node:title] document (at [node:changed])
        </p>
        <p>
            [ckeditor5_premium_notification:context]
        </p>
        <p>
          Best regards,
        </p>',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_STATUS => '<h3>Your suggestion status changed</h3>
        <p>
          User [user:name] [suggestion:status-label] your suggestion to the [node:title] document (at [node:changed])
        </p>
        <p>
            [ckeditor5_premium_notification:context]
        </p>
        <p>
          Best regards,
        </p>',
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_ADDED => '<h3>New suggestion added</h3>
        <p>
          User [user:name] added new suggestion in the [node:title] document (at [node:changed])
        </p>
        <p>
            [ckeditor5_premium_notification:context]
        </p>
        <p>
          Best regards,
        </p>',
      default => 'CKEditor5 notification default body. If you want to change it please go to configuration page.',
    };

  }

  /**
   * Check if the given text format is enabled and return its id.
   *
   * @param string $format
   *   Format.
   *
   * @return int|string|null
   *   Format id.
   */
  protected function getTextFormatId(string $format): int|string|null {
    $format = FilterFormat::load($format);
    if ($format instanceof FilterFormatInterface && $format->status()) {
      return $format->getOriginalId();
    }
    return NULL;
  }

  /**
   * Gets supported message types list.
   */
  protected function getSupportedMessageTypes(): array {
    $messageTypes = NotificationMessageFactoryDefault::getSupportedMessageTypes();

    if (!$this->mentionsIntegrator->isMentionInstalled()) {
      unset($messageTypes[NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_MENTION_COMMENT]);
      unset($messageTypes[NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_MENTION_DOCUMENT]);
    }

    return $messageTypes;
  }

}
