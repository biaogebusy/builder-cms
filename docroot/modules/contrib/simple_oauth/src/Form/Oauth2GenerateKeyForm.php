<?php

namespace Drupal\simple_oauth\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\simple_oauth\Service\KeyGeneratorServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to generate keys.
 *
 * @internal
 */
class Oauth2GenerateKeyForm extends FormBase {

  /**
   * The key generator.
   *
   * @var \Drupal\simple_oauth\Service\KeyGeneratorServiceInterface
   */
  private KeyGeneratorServiceInterface $keyGen;

  /**
   * The simple_oauth logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Oauth2GenerateKeyForm constructor.
   *
   * @param \Drupal\simple_oauth\Service\KeyGeneratorServiceInterface $key_generator_service
   *   The key generator.
   * @param \Psr\Log\LoggerInterface $logger
   *   The simple_oauth logger channel.
   */
  public function __construct(KeyGeneratorServiceInterface $key_generator_service, LoggerInterface $logger) {
    $this->keyGen = $key_generator_service;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simple_oauth.key.generator'),
      $container->get('logger.channel.simple_oauth')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oauth2_generate_key';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pubk_id = NULL, $pk_id = NULL) {
    // Hidden public key id.
    $form['key_settings']['pubk_id'] = [
      '#type' => 'hidden',
      '#required' => TRUE,
      '#value' => $pubk_id,
    ];

    // Hidden private key id.
    $form['key_settings']['pk_id'] = [
      '#type' => 'hidden',
      '#required' => TRUE,
      '#value' => $pk_id,
    ];

    // Error Messages container.
    $form['key_settings']['message'] = [
      '#markup' => '<div id="key-error-message" class="messages messages--error"></div>',
      '#hidden' => TRUE,
    ];

    $disclaimer = '<p>'
      . $this->t('This is the directory where the public and private keys will be stored after generation. This <strong>SHOULD</strong> be located outside of your webroot to avoid making them public unintentionally.')
      . '</p><p>'
      . $this->t('Any keys already present in this directory will be deleted.')
      . '</p>';
    // Private Key Path.
    $form['key_settings']['directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory for the keys'),
      '#description' => $disclaimer,
      '#required' => TRUE,
      '#attributes' => [
        'id' => "dir_path",
      ],
    ];

    // Submit.
    $form['key_settings']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate'),
      '#ajax' => [
        'callback' => '::generateKeys',
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * Generate public and private keys.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response.
   */
  public function generateKeys(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Get all the values.
    $values = $form_state->getValues();

    // Get Private key path.
    $dir_path = $values['directory'];
    if (!isset($dir_path)) {
      $response->addCommand(new InvokeCommand('#key-error-message', 'show'));
      return $response->addCommand(new HtmlCommand('#key-error-message', $this->t('The directory is required.')));
    }

    try {
      // Generate keys.
      $this->keyGen->generateKeys($dir_path);
    }
    catch (\Exception $exception) {
      // If exception log it and return an error message.
      Error::logException($this->logger, $exception);
      $response->addCommand(new InvokeCommand('#key-error-message', 'show'));
      return $response->addCommand(new HtmlCommand('#key-error-message', $exception->getMessage()));
    }

    // Close dialog.
    $response->addCommand(new CloseDialogCommand());

    // Update private key field if id was supplied on the build form.
    if (isset($values['pk_id'])) {
      $response->addCommand(new InvokeCommand('#' . $values['pk_id'], 'val', [$dir_path . '/private.key']));
    }
    if (isset($values['pubk_id'])) {
      $response->addCommand(new InvokeCommand('#' . $values['pubk_id'], 'val', [$dir_path . '/public.key']));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

}
