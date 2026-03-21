<?php

namespace Drupal\simple_oauth\Entity\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\simple_oauth\Service\Filesystem\FileSystemChecker;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The settings form.
 *
 * @internal
 */
class Oauth2TokenSettingsForm extends ConfigFormBase {

  /**
   * The file system checker.
   *
   * @var \Drupal\simple_oauth\Service\Filesystem\FileSystemChecker
   */
  protected $fileSystemChecker;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Oauth2TokenSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\simple_oauth\Service\Filesystem\FileSystemChecker $file_system_checker
   *   The simple_oauth.filesystem service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, FileSystemChecker $file_system_checker, MessengerInterface $messenger) {
    parent::__construct($configFactory);
    $this->fileSystemChecker = $file_system_checker;
    $this->messenger = $messenger;
  }

  /**
   * Creates the form.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\simple_oauth\Entity\Form\Oauth2TokenSettingsForm
   *   The form.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('simple_oauth.filesystem_checker'),
      $container->get('messenger')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'oauth2_token_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simple_oauth.settings'];
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $this->config('simple_oauth.settings');
    $settings->set('access_token_expiration', $form_state->getValue('access_token_expiration'));
    $settings->set('authorization_code_expiration', $form_state->getValue('authorization_code_expiration'));
    $settings->set('refresh_token_expiration', $form_state->getValue('refresh_token_expiration'));
    $settings->set('token_cron_batch_size', $form_state->getValue('token_cron_batch_size'));
    $settings->set('public_key', $form_state->getValue('public_key'));
    $settings->set('private_key', $form_state->getValue('private_key'));
    $settings->set('remember_clients', $form_state->getValue('remember_clients'));
    $settings->set('use_implicit', $form_state->getValue('use_implicit'));
    $settings->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Defines the settings form for Access Token entities.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('simple_oauth.settings');
    $form['access_token_expiration'] = [
      '#type' => 'number',
      '#title' => $this->t('Access token expiration time'),
      '#description' => $this->t('The default value, in seconds, to be used as expiration time when creating new tokens.'),
      '#default_value' => $config->get('access_token_expiration'),
    ];
    $form['authorization_code_expiration'] = [
      '#type' => 'number',
      '#title' => $this->t('Authorization code expiration time'),
      '#description' => $this->t('The default value, in seconds, to be used as expiration time when creating new authorization codes. If you are not sure about this value, use the same value as above for <em>Access token expiration time</em>.'),
      '#default_value' => \Drupal::config('simple_oauth.settings')
        ->get('authorization_code_expiration'),
      '#weight' => 0,
    ];
    $form['refresh_token_expiration'] = [
      '#type' => 'number',
      '#title' => $this->t('Refresh token expiration time'),
      '#description' => $this->t('The default value, in seconds, to be used as expiration time when creating new tokens.'),
      '#default_value' => $config->get('refresh_token_expiration'),
    ];
    $form['token_cron_batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Token batch size.'),
      '#description' => $this->t('The number of expired token to delete per batch during cron cron.'),
      '#default_value' => $config->get('token_cron_batch_size') ?: 0,
    ];
    $form['public_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public Key'),
      '#description' => $this->t('The path to the public key file.'),
      '#default_value' => $config->get('public_key'),
      '#element_validate' => ['::validateExistingFile'],
      '#required' => TRUE,
      '#attributes' => ['id' => 'pubk'],
    ];
    $form['private_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Private Key'),
      '#description' => $this->t('The path to the private key file.'),
      '#default_value' => $config->get('private_key'),
      '#element_validate' => ['::validateExistingFile'],
      '#required' => TRUE,
      '#attributes' => ['id' => 'pk'],
    ];

    $form['remember_clients'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remember previously approved clients'),
      '#description' => $this->t('When enabled, autorized clients will be stored and a authorization requests for the same client with previously accepted scopes will automatically be accepted.'),
      '#default_value' => $config->get('remember_clients'),
    ];

    $form['actions'] = [
      'actions' => [
        '#cache' => ['max-age' => 0],
        '#weight' => 20,
      ],
    ];

    // Generate Key Modal Button if openssl extension is enabled.
    if ($this->fileSystemChecker->isExtensionEnabled('openssl')) {
      // Generate Modal Button.
      $form['actions']['generate']['keys'] = [
        '#type' => 'link',
        '#title' => $this->t('Generate keys'),
        '#url' => Url::fromRoute(
          'oauth2_token.settings.generate_key',
          [],
          ['query' => ['pubk_id' => 'pubk', 'pk_id' => 'pk']]
        ),
        '#attributes' => [
          'class' => ['use-ajax', 'button'],
        ],
      ];

      // Attach Drupal Modal Dialog library.
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    }
    else {
      // Generate Notice Info Message about enabling openssl extension.
      $this->messenger->addMessage(
        $this->t('Enabling the PHP OpenSSL Extension will permit you generate the keys from this form.'),
        'warning'
      );
    }
    $form['use_implicit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable the implicit grant?'),
      '#description' => $this->t('The implicit grant has the potential to be used in an insecure way. Only enable this if you understand the risks. See https://tools.ietf.org/html/rfc6819#section-4.4.2 for more information.'),
      '#default_value' => \Drupal::config('simple_oauth.settings')->get('use_implicit'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Validates if the file exists.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateExistingFile(array &$element, FormStateInterface $form_state, array &$complete_form) {
    if (!empty($element['#value'])) {
      $path = $element['#value'];
      // Does the file exist?
      if (!$this->fileSystemChecker->fileExist($path)) {
        $form_state->setError($element, $this->t('The %field file does not exist.', ['%field' => $element['#title']]));
      }
      // Is the file readable?
      if (!$this->fileSystemChecker->isReadable($path)) {
        $form_state->setError($element, $this->t('The %field file at the specified location is not readable.', ['%field' => $element['#title']]));
      }
    }
  }

}
