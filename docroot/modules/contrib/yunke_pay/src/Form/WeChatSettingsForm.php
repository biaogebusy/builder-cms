<?php
/**
 * 微信支付设置表单
 *
 */

namespace Drupal\yunke_pay\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class WeChatSettingsForm extends FormBase {

  public function getFormId() {
    return 'yunke_pay_Settings_WeChat_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#weChatConfig'] = $this->config('yunke_pay.WeChat');
    $form['notice'] = [
      '#markup' => $this->t('Set WeChat API information. Before setting, it is recommended that you put your site in maintenance mode'),
    ];
    $form['appId'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('APP ID'),
      '#required'      => TRUE,
      '#default_value' => $form['#weChatConfig']->get('appId'),
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['appSecretKey'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('APP secret key'),//API密钥
      '#description'   => $this->t('App developer secret, JSAPI need it to get user\'s openID when paying in the WeChat browser'),
      '#default_value' => $form['#weChatConfig']->get('appSecretKey'),
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['merchantId'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Merchant ID'),//商户号
      '#required'      => TRUE,
      '#default_value' => $form['#weChatConfig']->get('merchantId'),
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['apiSecretKey'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('API secret key'),//API密钥
      '#description'   => $this->t('A 32 character string, Set and get it from the WeChat payment platform'),
      '#required'      => TRUE,
      '#default_value' => $form['#weChatConfig']->get('apiSecretKey'),
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['apiV3SecretKey'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('API v3 secret key'),//APIv3密钥
      '#description'   => $this->t('A 32 character string, Set and get it from the WeChat payment platform'),
      '#required'      => TRUE,
      '#default_value' => $form['#weChatConfig']->get('apiV3SecretKey'),
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['merchantSerialNumber'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Certificate Serial Number'),//商户API证书序列号
      '#description'   => $this->t('Merchant API Certificate Serial Number'),
      '#default_value' => $form['#weChatConfig']->get('merchantSerialNumber'),
      '#required'      => TRUE,
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['merchantPrivateKey'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Private Key'),//商户下载的私钥文件内容
      '#description'   => $this->t('The contents of the merchant private key, to see apiclient_key.pem'),
      '#default_value' => $form['#weChatConfig']->get('merchantPrivateKey'),
      '#required'      => TRUE,
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];


    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Submit'),
      '#button_type' => 'primary',
    ];
    return $form;

  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    //进行配置正确性验证
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $weChatConfig = $this->configFactory()->getEditable('yunke_pay.WeChat');
    $weChatConfig->set('appId', $form_state->getValue('appId'));
    $weChatConfig->set('appSecretKey', $form_state->getValue('appSecretKey'));
    $weChatConfig->set('merchantId', $form_state->getValue('merchantId'));
    $weChatConfig->set('apiSecretKey', $form_state->getValue('apiSecretKey'));
    $weChatConfig->set('apiV3SecretKey', $form_state->getValue('apiV3SecretKey'));
    $weChatConfig->set('merchantSerialNumber', $form_state->getValue('merchantSerialNumber'));
    $weChatConfig->set('merchantPrivateKey', str_replace("\r\n", "\n", $form_state->getValue('merchantPrivateKey')));
    $weChatConfig->save();
    $this->messenger()->addStatus($this->t('Configurations saved successfully'));
    if (!\Drupal::service('yunke_pay.pay.wechat')->updateWechatCertificate()) {
      $this->messenger()->addError($this->t('Wechat platform certificates update failed ! To check the system log'));
    }
  }

}
