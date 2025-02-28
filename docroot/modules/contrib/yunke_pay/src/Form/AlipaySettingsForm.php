<?php
/**
 * 支付宝支付API设置表单
 *
 */

namespace Drupal\yunke_pay\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class AlipaySettingsForm extends FormBase {

  public function getFormId() {
    return 'yunke_pay_Settings_Alipay_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#alipayConfig'] = $this->config('yunke_pay.Alipay');
    $form['notice'] = [
      '#markup' => $this->t('Set Alipay API information. Before setting, it is recommended that you put your site in maintenance mode'),
    ];
    $form['appId'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('APP ID'),
      '#required'      => TRUE,
      '#default_value' => $form['#alipayConfig']->get('appId'),
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['merchantPrivateKey'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('APP Private Key'),//应用私钥
      '#description'   => $this->t('The contents of the app private key'),
      '#default_value' => $form['#alipayConfig']->get('merchantPrivateKey'),
      '#required'      => TRUE,
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['merchantCert'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('APP Cert'),//应用公钥证书
      '#description'   => $this->t('The contents of the app cert , example: appCertPublicKey_2021002146640411.crt'),
      '#default_value' => $form['#alipayConfig']->get('merchantCert'),
      '#required'      => TRUE,
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['alipayCert'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Alipay Cert'),//下载的支付宝公钥证书内容
      '#description'   => $this->t('The contents of alipayCert , example: alipayCertPublicKey_RSA2.crt'),
      '#default_value' => $form['#alipayConfig']->get('alipayCert'),
      '#required'      => TRUE,
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['alipayRootCert'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Alipay Root Cert'),//下载的支付宝根证书内容
      '#description'   => $this->t('The contents of Alipay Root Cert , example: alipayRootCert.crt'),
      '#default_value' => $form['#alipayConfig']->get('alipayRootCert'),
      '#required'      => TRUE,
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['protocol'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('protocol'),//网关协议
      '#description'   => $this->t('gateway protocol, example: https'),
      '#required'      => TRUE,
      '#default_value' => $form['#alipayConfig']->get('protocol'),
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['gatewayHost'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('gateway Host'),//网关地址
      '#description'   => $this->t('gateway Host, example: openapi.alipay.com'),
      '#required'      => TRUE,
      '#default_value' => $form['#alipayConfig']->get('gatewayHost'),
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['signType'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('sign Type'),//
      '#description'   => $this->t('Signature algorithm type, example: RSA2 / RSA'),
      '#required'      => TRUE,
      '#default_value' => $form['#alipayConfig']->get('signType'),
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
    $alipayConfig = $this->configFactory()->getEditable('yunke_pay.Alipay');
    $alipayConfig->set('appId', $form_state->getValue('appId'));
    $alipayConfig->set('merchantPrivateKey', $form_state->getValue('merchantPrivateKey'));
    $alipayConfig->set('merchantCert', str_replace("\r\n", "\n", $form_state->getValue('merchantCert')));
    $alipayConfig->set('alipayCert', str_replace("\r\n", "\n", $form_state->getValue('alipayCert')));
    $alipayConfig->set('alipayRootCert', str_replace("\r\n", "\n", $form_state->getValue('alipayRootCert')));
    $alipayConfig->set('protocol', $form_state->getValue('protocol'));
    $alipayConfig->set('gatewayHost', $form_state->getValue('gatewayHost'));
    $alipayConfig->set('signType', $form_state->getValue('signType'));
    $alipayConfig->save();
    $this->messenger()->addStatus($this->t('Configurations saved successfully'));
  }

}
