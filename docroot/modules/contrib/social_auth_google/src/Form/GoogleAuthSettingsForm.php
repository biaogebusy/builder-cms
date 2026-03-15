<?php

namespace Drupal\social_auth_google\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\social_auth\Form\SocialAuthSettingsForm;
use Drupal\social_auth\Plugin\Network\NetworkInterface;

/**
 * Settings form for Social Auth Google.
 */
class GoogleAuthSettingsForm extends SocialAuthSettingsForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'social_auth_google_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return array_merge(
      parent::getEditableConfigNames(),
      ['social_auth_google.settings']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NetworkInterface $network = NULL): array {
    /** @var \Drupal\social_auth\Plugin\Network\NetworkInterface $network */
    $network = $this->networkManager->createInstance('social_auth_google');
    $form = parent::buildForm($form, $form_state, $network);

    $config = $this->config('social_auth_google.settings');

    $form['network']['authorized_javascript_origin'] = [
      '#type' => 'textfield',
      '#disabled' => TRUE,
      '#title' => $this->t('Authorized Javascript Origin'),
      '#description' => $this->t('Copy this value to <em>Authorized Javascript Origins</em> field of your Google App settings.'),
      '#default_value' => $GLOBALS['base_url'],
    ];

    $form['network']['advanced']['scopes']['#description'] =
      $this->t('Define any additional scopes to be requested, separated by a comma (e.g.: https://www.googleapis.com/auth/youtube.upload,https://www.googleapis.com/auth/youtube.readonly).<br>
        The scopes  \'openid\' \'email\' and \'profile\' are added by default and always requested.<br>You can see the full list of valid scopes and their description <a href="@scopes">here</a>.', [
          '@scopes' => 'https://developers.google.com/apis-explorer/#p/',
        ]);

    $form['network']['advanced']['endpoints']['#description'] =
       $this->t('Define the Endpoints to be requested when user authenticates with Google for the first time<br>
        Enter each endpoint in different lines in the format <em>endpoint</em>|<em>name_of_endpoint</em>.<br><b>For instance:</b><br>
        /youtube/v3/playlists?maxResults=2&mine=true&part=snippet|playlists_list<br>'
    );

    $form['network']['advanced']['restricted_domain'] = [
      '#type' => 'textfield',
      '#required' => FALSE,
      '#title' => $this->t('Restricted Domain'),
      '#default_value' => $config->get('restricted_domain'),
      '#description' => $this->t('If you want to restrict the users to a specific domain, insert your domain here. For example mycollege.edu. Note that this works only for Google Apps hosted accounts.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $this->config('social_auth_google.settings')
      ->set('client_id', trim($values['client_id']))
      ->set('client_secret', trim($values['client_secret']))
      ->set('scopes', $values['scopes'])
      ->set('endpoints', $values['endpoints'])
      ->set('restricted_domain', $values['restricted_domain'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
