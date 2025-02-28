<?php

namespace Drupal\swiftmailer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\key\Entity\Key;
use Drupal\key\KeyInterface;

/**
 * Swift Mailer settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'swiftmailer_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'swiftmailer.transport',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('swiftmailer.transport');

    // Submitted form values should be nested.
    $form['#tree'] = TRUE;

    // Display a page description.
    $form['description'] = [
      '#markup' => '<p>' . $this->t('This page allows you to configure settings which determines how e-mail messages are sent.') . '</p>',
    ];

    $form['transport'] = [
      '#id' => 'transport',
      '#type' => 'details',
      '#title' => $this->t('Transport types'),
      '#description' => $this->t('Which transport type should Drupal use to send e-mails?'),
      '#open' => TRUE,
    ];

    // Display the currently configured transport type, or alternatively the
    // currently selected transport type if the user has chosen to configure
    // another transport type.
    $transport = $config->get('transport');
    $transport = ($form_state->hasValue(['transport', 'type'])) ? $form_state->getValue(['transport', 'type']) : $transport;

    $form['transport']['type'] = [
      '#type' => 'radios',
      '#options' => [
        SWIFTMAILER_TRANSPORT_SMTP => $this->t('SMTP'),
        SWIFTMAILER_TRANSPORT_SENDMAIL => $this->t('Sendmail'),
        SWIFTMAILER_TRANSPORT_SPOOL => $this->t('Spool'),
      ],
      '#default_value' => $transport,
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'transport_configuration',
        'method' => 'replace',
        'effect' => 'fade',
      ],
      '#description' => $this->t('Not sure which transport type to choose? The @documentation gives you a good overview of the various transport types.', ['@documentation' => Link::fromTextAndUrl((string) $this->t('Swift Mailer documentation'), Url::fromUri('https://swiftmailer.symfony.com/docs/sending.html#transport-types'))->toString()]),
    ];

    $form['transport']['configuration'] = [
      '#type' => 'item',
      '#id' => 'transport_configuration',
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP] = [
      '#type' => 'item',
      '#access' => $form['transport']['type']['#default_value'] == SWIFTMAILER_TRANSPORT_SMTP,
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['title'] = [
      '#markup' => '<h3>' . $this->t('SMTP transport options') . '</h3>',
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['description'] = [
      '#markup' => '<p>' . $this->t('This transport type will send all e-mails using a SMTP
      server of your choice. You need to specify which SMTP server
      to use. Please refer to the @documentation for more details
      about this transport type.',
          ['@documentation' => Link::fromTextAndUrl($this->t('Swift Mailer documentation'), Url::fromUri('https://swiftmailer.symfony.com/docs/sending.html#the-smtp-transport'))->toString()]) . '</p>',
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['server'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SMTP server'),
      '#description' => $this->t('The hostname or IP address at which the SMTP server can be reached.'),
      '#required' => TRUE,
      '#default_value' => $config->get('smtp_host'),
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
      '#description' => $this->t('The port at which the SMTP server can be reached (defaults to 25)'),
      '#default_value' => $config->get('smtp_port'),
      '#size' => 10,
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['encryption'] = [
      '#type' => 'select',
      '#title' => $this->t('Encryption'),
      '#options' => swiftmailer_get_encryption_options(),
      '#description' => $this->t('The type of encryption which should be used (if any)'),
      '#default_value' => $config->get('smtp_encryption'),
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['credential_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Credential provider'),
      '#options' => [
        'swiftmailer' => 'Swift Mailer',
      ],
      '#default_value' => $config->get('smtp_credential_provider'),
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'transport_configuration',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $smtp_credential_provider = $form_state->getValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SMTP, 'credential_provider'], $config->get('smtp_credential_provider'));

    if (\Drupal::moduleHandler()->moduleExists('key')) {
      $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['credential_provider']['#options']['key'] = 'Key Module';

      /** @var \Drupal\key\Plugin\KeyPluginManager $key_type */
      $key_type = \Drupal::service('plugin.manager.key.key_type');
      if ($key_type->hasDefinition('user_password')) {
        $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['credential_provider']['#options']['multikey'] = 'Key Module (user/password)';
      }
    }

    if ($smtp_credential_provider === 'swiftmailer') {
      $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['credentials']['swiftmailer']['username'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
        '#description' => $this->t('A username required by the SMTP server (leave blank if not required)'),
        '#default_value' => $config->get('smtp_credentials.swiftmailer.username'),
        '#attributes' => [
          'autocomplete' => 'off',
        ],
      ];

      $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['credentials']['swiftmailer']['password'] = [
        '#type' => 'password',
        '#title' => $this->t('Password'),
        '#description' => $this->t('A password required by the SMTP server (leave blank if not required)'),
        '#default_value' => $config->get('smtp_credentials.swiftmailer.password'),
        '#attributes' => [
          'autocomplete' => 'off',
        ],
      ];

      $current_password = $config->get('smtp_credentials.swiftmailer.password');
      if (!empty($current_password)) {
        $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['credentials']['swiftmailer']['password']['#description'] = $this->t('A password required by the SMTP server. <em>The currently set password is hidden for security reasons</em>.');
      }
      $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['credentials']['swiftmailer']['delete_password'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Delete the stored password'),
      ];
    }
    elseif ($smtp_credential_provider === 'key') {
      $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['credentials']['key']['username'] = [
        '#type' => 'key_select',
        '#title' => $this->t('Username'),
        '#description' => $this->t('A username required by the SMTP server.'),
        '#default_value' => $config->get('smtp_credentials.key.username'),
        '#empty_option' => $this->t('- Please select -'),
        '#key_filters' => ['type' => 'authentication'],
        '#required' => TRUE,
      ];
      $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['credentials']['key']['password'] = [
        '#type' => 'key_select',
        '#title' => $this->t('Password'),
        '#description' => $this->t('A password required by the SMTP server.'),
        '#default_value' => $config->get('smtp_credentials.key.password'),
        '#empty_option' => $this->t('- Please select -'),
        '#key_filters' => ['type' => 'authentication'],
        '#required' => TRUE,
      ];
    }
    elseif ($smtp_credential_provider === 'multikey') {
      $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SMTP]['credentials']['multikey']['user_password'] = [
        '#type' => 'key_select',
        '#title' => $this->t('User/password'),
        '#description' => $this->t('A username + password required by the SMTP server.'),
        '#default_value' => $config->get('smtp_credentials.multikey.user_password'),
        '#empty_option' => $this->t('- Please select -'),
        '#key_filters' => ['type' => 'user_password'],
        '#required' => TRUE,
      ];
    }

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SENDMAIL] = [
      '#type' => 'item',
      '#access' => $form['transport']['type']['#default_value'] == SWIFTMAILER_TRANSPORT_SENDMAIL,
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SENDMAIL]['title'] = [
      '#markup' => '<h3>' . $this->t('Sendmail transport options') . '</h3>',
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SENDMAIL]['description'] = [
      '#markup' => '<p>' . $this->t('This transport type will send all e-mails using a locally
      installed MTA such as Sendmail. You need to specify which
      locally installed MTA to use by providing a path to the
      MTA. If you do not provide any path then Swift Mailer
      defaults to /usr/sbin/sendmail. You can read more about
      this transport type in the @documentation.',
          ['@documentation' => Link::fromTextAndUrl($this->t('Swift Mailer documentation'), Url::fromUri('https://swiftmailer.symfony.com/docs/sending.html#the-sendmail-transport'))->toString()]) . '</p>',
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SENDMAIL]['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MTA path'),
      '#description' => $this->t('The absolute path to the locally installed MTA.'),
      '#default_value' => $config->get('sendmail_path'),
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SENDMAIL]['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Mode'),
      '#options' => ['bs' => 'bs', 't' => 't '],
      '#description' => $this->t('Not sure which option to choose? Go with <em>bs</em>. You can read more about the above two modes in the @documentation.', ['@documentation' => Link::fromTextAndUrl($this->t('Swift Mailer documentation'), Url::fromUri('https://swiftmailer.symfony.com/docs/sendmail-transport'))->toString()]),
      '#default_value' => $config->get('sendmail_mode'),
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SPOOL] = [
      '#type' => 'item',
      '#access' => $form['transport']['type']['#default_value'] == SWIFTMAILER_TRANSPORT_SPOOL,
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SPOOL]['title'] = [
      '#markup' => '<h3>' . $this->t('Spool transport options') . '</h3>',
    ];

    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SPOOL]['description'] = [
      '#markup' => '<p>' . $this->t('This transport does not attempt to send the email
    but instead saves the message to a spool file. Another process can then
    read from the spool and take care of sending the emails.') . '</p>',
    ];

    $spool_directory = $config->get('spool_directory');
    $form['transport']['configuration'][SWIFTMAILER_TRANSPORT_SPOOL]['directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Spool directory'),
      '#description' => $this->t('The absolute path to the spool directory.'),
      '#default_value' => !empty($spool_directory) ? $spool_directory : sys_get_temp_dir() . '/swiftmailer-spool',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue(['transport', 'type']) == SWIFTMAILER_TRANSPORT_SMTP) {
      if ($form_state->getValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SMTP, 'credential_provider']) == 'swiftmailer') {
        if (empty($form_state->getValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SMTP, 'credentials', 'swiftmailer', 'password']))) {
          $password = $this->config('swiftmailer.transport')->get('smtp_credentials.swiftmailer.password');
          $form_state->setValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SMTP, 'credentials', 'swiftmailer', 'password'], $password);
        }
        if ($form_state->getValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SMTP, 'credentials', 'swiftmailer', 'delete_password'])) {
          $form_state->setValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SMTP, 'credentials', 'swiftmailer', 'password'], NULL);
        }
        $form_state->unsetValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SMTP, 'credentials', 'swiftmailer', 'delete_password']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('swiftmailer.transport');

    if ($form_state->hasValue(['transport', 'type'])) {
      $config->set('transport', $form_state->getValue(['transport', 'type']));
      $messenger = \Drupal::messenger();

      switch ($form_state->getValue(['transport', 'type'])) {
        case SWIFTMAILER_TRANSPORT_SMTP:
          $config->set('smtp_host', $form_state->getValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SMTP, 'server']));
          $config->set('smtp_port', $form_state->getValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SMTP, 'port']));
          $config->set('smtp_encryption', $form_state->getValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SMTP, 'encryption']));
          $config->set('smtp_credential_provider', $form_state->getValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SMTP, 'credential_provider']));
          $config->set('smtp_credentials', [
            $config->get('smtp_credential_provider') => $form_state->getValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SMTP, 'credentials', $config->get('smtp_credential_provider')])
          ]);
          $config->save();
          $messenger->addStatus($this->t('Drupal has been configured to send all e-mails using the SMTP transport type.'));
          break;

        case SWIFTMAILER_TRANSPORT_SENDMAIL:
          $config->set('sendmail_path', $form_state->getValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SENDMAIL, 'path']));
          $config->set('sendmail_mode', $form_state->getValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SENDMAIL, 'mode']));
          $config->save();
          $messenger->addStatus($this->t('Drupal has been configured to send all e-mails using the Sendmail transport type.'));
          break;

        case SWIFTMAILER_TRANSPORT_SPOOL:
          $config->set('spool_directory', $form_state->getValue(['transport', 'configuration', SWIFTMAILER_TRANSPORT_SPOOL, 'directory']));
          $config->save();
          $messenger->addStatus($this->t('Drupal has been configured to send all e-mails using the Spool transport type.'));
          break;
      }
    }

  }

  /**
   * Ajax callback for the transport dependent configuration options.
   *
   * @return array
   *   The form element containing the configuration options.
   */
  public static function ajaxCallback($form, FormStateInterface $form_state) {
    return $form['transport']['configuration'];
  }

}
