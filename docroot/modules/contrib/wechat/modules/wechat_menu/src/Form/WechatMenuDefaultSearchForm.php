<?php

/**
 * @file
 * Contains \Drupal\wechat_menu\Form\WechatMenuDefaultSearchForm.
 */

namespace Drupal\wechat_menu\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Default config form for wechat default search.
 */
class WechatMenuDefaultSearchForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wechat_menu_default_search';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['wechat_menu.default_search'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('wechat_menu.default_search');
	
    $form['view_name'] = array(
      '#type' => 'textfield',
      '#title' => t('Views Name'),
      '#description' => t('Machine name of search view.'),
      '#default_value' => $config->get('view_name'),
      '#required' => TRUE,
    );
	
    $form['view_display'] = array(
    '#type' => 'textfield',
    '#title' => t('Views Display'),
    '#description' => t('Machine name of views display'),
    '#default_value' => $config->get('view_display'),
    '#required' => TRUE,
    );
	
    $form['view_filter_identifier'] = array(
    '#type' => 'textfield',
    '#title' => t('Filter identifier'),
    '#description' => t('Filter identifier of seach Views'),
    '#default_value' => $config->get('view_filter_identifier'),
    '#required' => TRUE,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * Runs cron and reloads the page.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('wechat_menu.default_search')
      ->set('view_name', $form_state->getValue('view_name'))
      ->set('view_display', $form_state->getValue('view_display'))
      ->set('view_filter_identifier', $form_state->getValue('view_filter_identifier'))
      ->save();

    parent::submitForm($form, $form_state);

  }

}
