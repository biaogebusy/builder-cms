<?php

declare(strict_types = 1);

namespace Drupal\sms_user\Form;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\sms\Entity\PhoneNumberSettingsInterface;
use Drupal\sms\Provider\PhoneNumberVerificationInterface;

/**
 * Provides a general settings form for SMS User.
 */
class AdminSettingsForm extends ConfigFormBase {

  /**
   * Constructs a new AdminSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\sms\Provider\PhoneNumberVerificationInterface $phoneNumberVerificationProvider
   *   The phone number verification provider.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected PhoneNumberVerificationInterface $phoneNumberVerificationProvider,
    MessengerInterface $messenger,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('sms.phone_number.verification'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sms_user_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $op = NULL, $domain = NULL) {
    $form['#attached']['library'][] = 'sms_user/admin';
    $config = $this->config('sms_user.settings');

    // Active hours.
    $form['active_hours'] = [
      '#type' => 'details',
      '#title' => $this->t('Active hours'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['active_hours']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable active hours'),
      '#description' => $this->t('Active hours will suspend transmission of automated SMS messages until the users local time is between any of these hours. The site default timezone is used if a user has not selected a timezone. Active hours are not applied to SMS messages created as a result of direct user action. Messages which are already queued are not retroactively updated.'),
      '#default_value' => $config->get('active_hours.status'),
    ];

    $form['active_hours']['days_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="active_hours[status]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['active_hours']['days_container']['days'] = [
      '#type' => 'table',
      '#header' => [
        'day' => $this->t('Day'),
        'start' => $this->t('Start time'),
        'end' => $this->t('End time'),
      ],
      '#parents' => ['active_hours', 'days'],
    ];

    // Convert configuration into days.
    $day_defaults = [];
    foreach ($config->get('active_hours.ranges') as $range) {
      $start = new DrupalDateTime($range['start']);
      $end = new DrupalDateTime($range['end']);
      $start_day = strtolower($start->format('l'));

      $day_defaults[$start_day]['start'] = $start->format('G');

      if (new DrupalDateTime($start_day . ' +1 day') == $end) {
        $day_defaults[$start_day]['end'] = 24;
      }
      else {
        $day_defaults[$start_day]['end'] = $end->format('G');
      }
    }

    // Prepare options for select fields.
    $hours = [];
    for ($i = 0; $i < 24; $i++) {
      $hours[$i] = DrupalDateTime::datePad($i) . ':00';
    }
    $hours[0] = $this->t('- Start of day -');
    $end_hours = $hours;
    unset($end_hours[0]);
    $end_hours[24] = $this->t('- End of day -');

    $date = new \DateTime('next Sunday');
    for ($i = 0; $i < 7; $i++) {
      $row = ['#tree' => TRUE];
      $day = $date->format('l');
      $day_lower = strtolower($day);

      $row['day']['#plain_text'] = $day;

      // @todo convert to 'datetime' after
      // https://www.drupal.org/node/2703941 is fixed.
      $row['start'] = [
        '#type' => 'select',
        '#title' => $this->t('Start time for @day', ['@day' => $day]),
        '#title_display' => 'invisible',
        '#default_value' => $day_defaults[$day_lower]['start'] ?? -1,
        '#options' => $hours,
        '#empty_option' => $this->t('- Suspend messages for this day -'),
        '#empty_value' => -1,
      ];
      $row['end'] = [
        '#type' => 'select',
        '#title' => $this->t('Start time for @day', ['@day' => $day]),
        '#title_display' => 'invisible',
        '#default_value' => $day_defaults[$day_lower]['end'] ?? 24,
        '#options' => $end_hours,
        '#states' => [
          'invisible' => [
            ':input[name="active_hours[days][' . $day_lower . '][start]"]' => ['value' => '-1'],
          ],
        ],
      ];

      $date->modify('+1 day');
      $form['active_hours']['days_container']['days'][$day_lower] = $row;
    }

    // Account registration.
    $form['account_registration'] = [
      '#type' => 'details',
      '#title' => $this->t('Account creation'),
      '#description' => $this->t('New accounts can be created and associated with a phone number.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    if ($config->get('account_registration.unrecognized_sender.status')) {
      $radio_value = 'all';
    }
    elseif ($config->get('account_registration.incoming_pattern.status')) {
      $radio_value = 'incoming_pattern';
    }
    else {
      $radio_value = 'none';
    }

    $user_phone_settings_exist = $this->phoneNumberVerificationProvider
      ->getPhoneNumberSettings('user', 'user') instanceof PhoneNumberSettingsInterface;
    if (!$user_phone_settings_exist) {
      $this->messenger()->addMessage($this->t('There are no phone number settings configured for the user entity type. Some features cannot operate without these settings. <a href=":add">Add phone number settings</a>.', [
        ':add' => Url::fromRoute('entity.phone_number_settings.add')->toString(),
      ]), 'warning');
    }

    // The parent 'radios' form element for our account registration behaviour.
    $form['account_registration']['behaviour'] = [
      '#type' => 'radios',
      '#title' => $this->t('Account registration via SMS'),
      '#options' => [
        'none' => $this->t('Disabled'),
        'all' => $this->t('All unrecognised phone numbers'),
        'incoming_pattern' => $this->t('Incoming message based on pattern'),
      ],
      '#required' => TRUE,
      '#default_value' => $radio_value,
    ];

    // Modify the radio button for the 'Disabled' option.
    $form['account_registration']['behaviour']['none'] = [
      '#description' => $this->t('Disable account creation via SMS.'),
      '#return_value' => 'none',
    ];

    // Modify the radio button for the 'All unrecognised phone numbers' option.
    $form['account_registration']['behaviour']['all'] = [
      '#description' => $this->t('Automatically create a Drupal account for all phone numbers not associated with an existing account.'),
      '#return_value' => 'all',
      '#disabled' => !$user_phone_settings_exist,
    ];

    // Dynamically show form elements if the 'all' radio button is selected.
    // This container holds a checkbox which, if checked, will be accompanied
    // by a textarea.
    $form['account_registration']['behaviour']['all_options'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['sms_user-radio-indent'],
      ],
      '#parents' => ['account_registration', 'all_options'],
      '#tree' => TRUE,
      '#states' => [
        // Show only when the 'all' radio button is selected.
        'visible' => [
          ':input[name="account_registration[behaviour]"]' => ['value' => 'all'],
        ],
      ],
    ];
    $form['account_registration']['behaviour']['all_options']['reply_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable reply message'),
      '#default_value' => $config->get('account_registration.unrecognized_sender.reply.status'),
    ];
    // Show the accompanying textarea only if the 'reply_status' checkbox
    // is selected.
    $form['account_registration']['behaviour']['all_options']['reply'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="account_registration[behaviour][all_options][reply_status]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['account_registration']['behaviour']['all_options']['reply']['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reply message'),
      '#description' => $this->t('Send a message after a new account is created. In addition to the tokens listed below, [user:password] is also available.'),
      '#default_value' => $config->get('account_registration.unrecognized_sender.reply.message'),
      '#states' => [
        'visible' => [
          ':input[name="account_registration[behaviour][all_options][reply_status]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['account_registration']['behaviour']['all_options']['reply']['tokens'] = $this->buildTokenElement();

    // Modify radio button for the 'Incoming message based on pattern' option.
    $form['account_registration']['behaviour']['incoming_pattern'] = [
      '#description' => $this->t('Automatically create a Drupal account if message is received in a specified format.'),
      '#return_value' => 'incoming_pattern',
      '#disabled' => !$user_phone_settings_exist,
    ];

    // Dynamically show form elements if the 'incoming_pattern' radio button is
    // selected. This container holds a textarea and two checkboxs. The second
    // checkbox, if checked, will be accompanied by two message textareas.
    $form['account_registration']['behaviour']['incoming_pattern_options'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['sms_user-radio-indent'],
      ],
      '#parents' => ['account_registration', 'incoming_pattern_options'],
      '#tree' => TRUE,
      '#states' => [
        // Show only when the 'incoming_pattern' radio button is selected.
        'visible' => [
          ':input[name="account_registration[behaviour]"]' => ['value' => 'incoming_pattern'],
        ],
      ],
    ];
    $form['account_registration']['behaviour']['incoming_pattern_options']['incoming_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Incoming message'),
      '#description' => $this->t('You should use at least one placeholder: [email], [password], or [username]. If password is omitted: a random password will be generated. If username is omitted: a random username will be generated. If email address is omitted: no email address will be associated with the account.'),
      '#default_value' => $config->get('account_registration.incoming_pattern.incoming_messages.0'),
    ];
    $form['account_registration']['behaviour']['incoming_pattern_options']['send_activation_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send activation email'),
      '#description' => $this->t('Send activation email if an [email] placeholder is present, and [password] placeholder is omitted.'),
      '#default_value' => $config->get('account_registration.incoming_pattern.send_activation_email'),
    ];

    $form['account_registration']['behaviour']['incoming_pattern_options']['reply_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable reply message'),
      '#default_value' => $config->get('account_registration.incoming_pattern.reply.status'),
    ];
    // Show the two accompanying textareas only if the 'reply_status' checkbox
    // is selected.
    $form['account_registration']['behaviour']['incoming_pattern_options']['reply'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['sms_user-radio-indent'],
      ],
      '#states' => [
        'visible' => [
          ':input[name="account_registration[behaviour][incoming_pattern_options][reply_status]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['account_registration']['behaviour']['incoming_pattern_options']['reply']['message_success'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reply message (success)'),
      '#description' => $this->t('Send a message after a new account is successfully created. In addition to the tokens listed below, [user:password] is also available.'),
      '#default_value' => $config->get('account_registration.incoming_pattern.reply.message'),
    ];
    $form['account_registration']['behaviour']['incoming_pattern_options']['reply']['message_failure'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reply message (failure)'),
      '#description' => $this->t('Send a message if a new account could not be created. Such reasons include: username already taken, email already used. In addition to the tokens listed below, [error] is also available.'),
      '#default_value' => $config->get('account_registration.incoming_pattern.reply.message_failure'),
    ];
    $form['account_registration']['behaviour']['incoming_pattern_options']['reply']['tokens'] = $this->buildTokenElement();

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Active hours.
    foreach ($form_state->getValue(['active_hours', 'days']) as $day => $row) {
      foreach ($row as $position => $hour) {
        if ($hour == -1) {
          $form_state->unsetValue(['active_hours', 'days', $day]);
          continue 2;
        }
        elseif ($hour == 24) {
          $str = $day . ' +1 day';
        }
        else {
          $str = $day . ' ' . $hour . ':00';
        }
        $form_state->setValue(['active_hours', 'days', $day, $position], $str);
      }
    }

    // Ensure at least one enabled.
    if ($form_state->getValue(['active_hours', 'status']) && empty($form_state->getValue(['active_hours', 'days']))) {
      // Show error on all start elements.
      foreach (Element::children($form['active_hours']['days_container']['days']) as $day) {
        $form_state->setError($form['active_hours']['days_container']['days'][$day]['start'], $this->t('If active hours hours are enabled there must be at least one enabled day.'));
      }
    }

    // Ensure end times are greater than start times.
    foreach ($form_state->getValue(['active_hours', 'days']) as $day => $row) {
      $start = new DrupalDateTime($row['start']);
      $end = new DrupalDateTime($row['end']);
      if ($end < $start) {
        $form_state->setError($form['active_hours']['days_container']['days'][$day]['end'], $this->t('End time must be greater than start time.'));
      }
    }

    // Account registration.
    $account_registration = $form_state->getValue(['account_registration']);
    if (!empty($account_registration['all_options']['reply_status']) && empty($account_registration['all_options']['reply']['message'])) {
      // Reply is enabled, but empty reply.
      $form_state->setError($form['account_registration']['behaviour']['all_options']['reply']['message'], $this->t('Reply message must have a value if reply is enabled.'));
    }

    // Incoming message.
    $incoming_message = $account_registration['incoming_pattern_options']['incoming_message'];
    if ($account_registration['behaviour'] == 'incoming_pattern' && empty($incoming_message)) {
      // Empty incoming message.
      $form_state->setError($form['account_registration']['behaviour']['incoming_pattern_options']['incoming_message'], $this->t('Incoming message must be filled if using pre-incoming_pattern option.'));
    }
    elseif (!empty($incoming_message)) {
      $contains_email = strpos($incoming_message, '[email]') !== FALSE;
      $contains_password = strpos($incoming_message, '[password]') !== FALSE;
      $activation_email = $account_registration['incoming_pattern_options']['send_activation_email'];
      if ($activation_email && !$contains_email) {
        // Email placeholder must be present if activation email is on.
        $form_state->setError($form['account_registration']['behaviour']['incoming_pattern_options']['send_activation_email'], $this->t('Activation email cannot be sent if [email] placeholder is missing.'));
      }
      if ($activation_email && $contains_email && $contains_password) {
        // Check if password and email occur at the same time.
        $form_state->setError($form['account_registration']['behaviour']['incoming_pattern_options']['send_activation_email'], $this->t('Activation email cannot be sent if [password] placeholder is present.'));
      }

      // Make sure there is a separator between placeholders so regex capture
      // groups work correctly.
      $placeholders = ['[username]', '[email]', '[password]'];
      $regex_placeholder = [];
      foreach ($placeholders as $placeholder) {
        $regex_placeholder[] = preg_quote($placeholder);
      }

      $regex = '/(' . implode('|', $regex_placeholder) . '+)/';
      $last_word_is_placeholder = FALSE;
      foreach (preg_split($regex, $incoming_message, -1, PREG_SPLIT_DELIM_CAPTURE) as $word) {
        if ($word === '') {
          continue;
        }
        $this_word_is_placeholder = in_array($word, $placeholders);
        if ($last_word_is_placeholder && $this_word_is_placeholder) {
          $form_state->setError($form['account_registration']['behaviour']['incoming_pattern_options']['incoming_message'], $this->t('There must be a separator between placeholders.'));
        }
        $last_word_is_placeholder = $this_word_is_placeholder;
      }
    }

    // Replies.
    if (!empty($account_registration['incoming_pattern_options']['reply_status']) && empty($account_registration['incoming_pattern_options']['reply']['message_success'])) {
      // Reply is enabled, but empty reply.
      $form_state->setError($form['account_registration']['behaviour']['incoming_pattern_options']['reply']['message_success'], $this->t('Reply message must have a value if reply is enabled.'));
    }
    if (!empty($account_registration['incoming_pattern_options']['reply_status']) && empty($account_registration['incoming_pattern_options']['reply']['message_failure'])) {
      // Reply is enabled, but empty reply.
      $form_state->setError($form['account_registration']['behaviour']['incoming_pattern_options']['reply']['message_failure'], $this->t('Reply message must have a value if reply is enabled.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('sms_user.settings');

    // Account Registration.
    $account_registration = $form_state->getValue('account_registration');
    $behaviour = $account_registration['behaviour'];

    $config
      ->set('account_registration.unrecognized_sender.status', $behaviour == 'all')
      ->set('account_registration.incoming_pattern.status', $behaviour == 'incoming_pattern')
      ->set('account_registration.unrecognized_sender.reply.status', $account_registration['all_options']['reply_status'])
      ->set('account_registration.unrecognized_sender.reply.message', $account_registration['all_options']['reply']['message'])
      ->set('account_registration.incoming_pattern.incoming_messages.0', $account_registration['incoming_pattern_options']['incoming_message'])
      ->set('account_registration.incoming_pattern.reply.status', $account_registration['incoming_pattern_options']['reply_status'])
      ->set('account_registration.incoming_pattern.reply.message', $account_registration['incoming_pattern_options']['reply']['message_success'])
      ->set('account_registration.incoming_pattern.reply.message_failure', $account_registration['incoming_pattern_options']['reply']['message_failure'])
      ->set('account_registration.incoming_pattern.send_activation_email', $account_registration['incoming_pattern_options']['send_activation_email'])
      // Active Hours.
      ->set('active_hours.status', (boolean) $form_state->getValue(['active_hours', 'status']))
      // Days make sense for this form, however storage uses generic 'range'
      // term. Remove keys so it is a raw sequence.
      ->set('active_hours.ranges', array_values($form_state->getValue(['active_hours', 'days'])))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sms_user.settings'];
  }

  /**
   * Build a token element.
   *
   * @return array
   *   A render array.
   */
  protected function buildTokenElement() {
    $tokens = ['sms-message', 'user'];

    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $module_handler */
    $module_handler = \Drupal::service('module_handler');
    if ($module_handler->moduleExists('token')) {
      return [
        '#theme' => 'token_tree_link',
        '#token_types' => $tokens,
      ];
    }
    else {
      foreach ($tokens as &$token) {
        $token = "[$token:*]";
      }
      return [
        '#markup' => $this->t('Available tokens include: @token_types', ['@token_types' => implode(' ', $tokens)]),
      ];
    }
  }

}
