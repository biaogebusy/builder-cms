<?php
/**
 * 模块设置表单
 *
 */

namespace Drupal\yunke_pay\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

class PaySettingsForm extends FormBase {

  public function getFormId() {
    return 'yunke_pay_PaySettings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $markup = "Yunke Pay Module <br>developed:未来很美（www.will-nice.com)<br>developer:云客（www.indrupal.com)";
    $declaration = \Drupal::moduleHandler()->getModule("yunke_pay")->getPath() . '/README.txt';
    if (file_exists($declaration) && is_readable($declaration)) {
      $info = file_get_contents($declaration);
      $info = str_replace("\n", "<br>", $info);
    }
    $markup = Markup::create($info);
    $form['notice'] = [
      '#markup' => $markup,
    ];
    return $form;

  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    //进行配置正确性验证
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {


  }

}
