<?php

namespace Drupal\swiftmailer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Configuration form for SwiftMailer message settings.
 */
class MessagesForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'swiftmailer_messages_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'swiftmailer.message',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('swiftmailer.message');

    $form['#tree'] = TRUE;

    $form['description'] = [
      '#markup' => '<p>' . $this->t('This page allows you to configure how e-mail messages are formatted.
        The Default PHP mailer can only send plain text e-mails. This module can send HTML e-mails if you
        use the recommended settings below.') . '</p>',
    ];

    $form['content_type'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content type'),
      '#description' => $this->t('Select the content type. This module will convert content if necessary.
        The <em>Keep existing</em> option is less recommended because many applications do not set the
        existing content type reliably. Some applications (including Simplenews newsletter)
        allow case-by-case configuration of content type that overrides this value.'),
    ];

    $options = [
      SWIFTMAILER_FORMAT_HTML => $this->t('HTML (recommended)'),
      SWIFTMAILER_FORMAT_PLAIN => $this->t('Plain Text'),
      '' => $this->t('Keep existing'),
    ];
    $form['content_type']['type'] = [
      '#type' => 'radios',
      '#options' => $options,
      '#default_value' => $config->get('content_type'),
    ];

    $form['html_convert'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('HTML formatting'),
      '#description' => $this->t('Text format to use when converting a plain text e-mail to HTML.'),
    ];

    // The filter will operate on plain text so only show formats that escape
    // HTML.
    foreach (filter_formats($this->currentUser()) as $format) {
      if ($format->filters('filter_html_escape')->status) {
        $formats[$format->id()] = $format->label();
      }
    }

    $form['html_convert']['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Text format'),
      '#options' => $formats,
      '#default_value' => $config->get('text_format') ?: filter_fallback_format(),
      '#description' => $this->t('The list of available formats is restricted to those that escape HTML.'),
    ];

    $form['generate_plain'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Plain Text Version'),
      '#description' => $this->t('An alternative plain text version can be generated based on the HTML version if no plain text version
        has been explicitly set. The plain text version will be used by e-mail clients not capable of displaying HTML content.'),
    ];

    $form['generate_plain']['mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate alternative plain text version (recommended).'),
      '#default_value' => $config->get('generate_plain'),
      '#description' => $this->t('Please refer to @link for more details about how the alternative plain text version will be generated.', ['@link' => Link::fromTextAndUrl('html2text', Url::fromUri('http://www.chuggnutt.com/html2text'))->toString()]),
    ];

    $form['character_set'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Character Set'),
      '#description' => '<p>' . $this->t('E-mails need to carry details about the character set which the
        receiving client should use to understand the content of the e-mail.
        The default character set is UTF-8.') . '</p>',
    ];

    $form['character_set']['type'] = [
      '#type' => 'select',
      '#options' => swiftmailer_get_character_set_options(),
      '#default_value' => $config->get('character_set'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('swiftmailer.message');
    $config->set('content_type', $form_state->getValue(['content_type', 'type']));
    $config->set('text_format', $form_state->getValue(['html_convert', 'format']));
    $config->set('generate_plain', $form_state->getValue(['generate_plain', 'mode']));
    $config->set('character_set', $form_state->getValue(['character_set', 'type']));

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
