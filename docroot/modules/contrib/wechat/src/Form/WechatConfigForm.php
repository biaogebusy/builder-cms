<?php

/**
 * @file
 * Contains \Drupal\wechat\Form\WechatConfigForm.
 */

namespace Drupal\wechat\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Default config form for wechat.
 */
class WechatConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wechat_default_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['wechat.default'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('wechat.default');
	
	$powered_by = t('Build by <a href=":yaiyuan">YAIYUAN</a>. You could find <a href=":document">official document</a> at here.', array(':yaiyuan'=> 'http://www.yaiyuan.com', ':document' => 'http://www.thinkindrupal.com/drupal8/wechat'));
    $form['powered_by'] = array('#markup' => '<p>' . $powered_by . '</p>');
	
	$form['token'] = array(
      '#type' => 'textfield',
      '#title' => t('Token'),
      '#description' => t('The token that you use in wechat public platform.'),
      '#default_value' => $config->get('token'),
      '#required' => TRUE,
    );
    $form['appid'] = array(
      '#type' => 'textfield',
      '#title' => t('AppID'),
      '#description' => t('Account APPID'),
      '#default_value' => $config->get('appid'),
      '#required' => TRUE,
    );
    $form['appsecret'] = array(
      '#type' => 'textfield',
      '#title' => t('AppSecret'),
      '#description' => t('Account AppSecret'),
      '#default_value' => $config->get('appsecret'),
      '#required' => TRUE,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * Runs cron and reloads the page.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('wechat.default')
      ->set('token', $form_state->getValue('token'))
      ->set('appid', $form_state->getValue('appid'))
      ->set('appsecret', $form_state->getValue('appsecret'))
      ->save();

    parent::submitForm($form, $form_state);

  }

}
