<?php
/**
 * Usage demo of yunke QR code module
 * 二维码使用示例
 * Developed by: yunke(email:phpworld@qq.com);
 * From company: 未来很美(深圳）科技有限公司(http://www.will-nice.com)
 */

namespace Drupal\yunke_qrcode\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class QRCodeDemoForm extends FormBase {

  public function getFormId() {
    return 'QR_code_demo_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#title'] = 'yunke_qrcode Demo';

    $form['text'] = [
      '#type'          => 'textarea',
      '#title'         => 'text',
      '#description'   => 'The content encoded by QR code',
      '#default_value' => "Developed by:yunke(phpworld@qq.com); \nFrom company:未来很美科技(http://www.will-nice.com)",
      '#maxlength'     => 4296,
      '#required'      => TRUE,
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['width'] = [
      '#type'          => 'number',
      '#title'         => 'width',
      '#description'   => 'QR code width',
      '#required'      => TRUE,
      '#default_value' => 300,
      '#min'           => 10,
      '#max'           => 2000,
      '#step'          => 1,
      '#field_suffix'  => 'px',
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['height'] = [
      '#type'          => 'number',
      '#title'         => 'height',
      '#description'   => 'QR code height',
      '#required'      => TRUE,
      '#default_value' => 300,
      '#min'           => 10,
      '#max'           => 2000,
      '#step'          => 1,
      '#field_suffix'  => 'px',
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $correctLevelOptions = [
      '1' => 'L',
      '0' => 'M',
      '3' => 'Q',
      '2' => 'H',
    ];
    $form['correctLevel'] = [
      '#type'          => 'select',
      '#title'         => 'correct level',
      '#description'   => 'This affects the anti-pollution ability of QR code',
      '#required'      => TRUE,
      '#options'       => $correctLevelOptions,
      '#default_value' => 1,
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['background'] = [
      '#type'          => 'color',
      '#title'         => 'background color',
      '#required'      => TRUE,
      '#default_value' => '#ffffff',
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['foreground'] = [
      '#type'          => 'color',
      '#title'         => 'foreground color',
      '#required'      => TRUE,
      '#default_value' => '#33CC99',
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $tagOptions = [
      'div'  => 'div',
      'span' => 'span',
      'p'    => 'p',
    ];
    $form['tag'] = [
      '#type'          => 'select',
      '#title'         => 'tag',
      '#description'   => 'The HTML tag that places the QR code',
      '#required'      => TRUE,
      '#options'       => $tagOptions,
      '#default_value' => 'div',
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $renderOptions = [
      'canvas' => 'canvas',
      'table'  => 'table',
    ];
    $form['render'] = [
      '#type'          => 'select',
      '#title'         => 'QR Code element',
      '#description'   => 'Presentation of QR code element',
      '#required'      => TRUE,
      '#options'       => $renderOptions,
      '#default_value' => 'canvas',
      '#attributes'    => [
        'autocomplete' => 'off',
      ],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => 'Generate QR Code',
      '#button_type' => 'primary',
      '#ajax'        => [
        'callback' => '::qrcode',
        'wrapper'  => 'qrcode-result-wrapper',
        'prevent'  => 'click',
        'method'   => 'html',
        'progress' => [
          'type'    => 'throbber',
          'message' => 'generating...',
        ],
      ],
    ];

    $form['result'] = [
      '#type'       => 'details',
      '#title'      => 'QR code',
      '#open'       => TRUE,
      '#attributes' => ['id' => 'qrcode-result-wrapper'],
    ];
    $form['result']['qrcode'] = [
      '#type' => 'yunke_qrcode',
      '#text' => 'http://www.will-nice.com',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $result['text'] = trim($form_state->getValue('text'));
    $result['width'] = (int) $form_state->getValue('width');
    $result['height'] = (int) $form_state->getValue('height');
    $result['correctLevel'] = (int) $form_state->getValue('correctLevel');
    $result['background'] = $form_state->getValue('background');
    $result['foreground'] = $form_state->getValue('foreground');
    $result['tag'] = $form_state->getValue('tag');
    $result['render'] = $form_state->getValue('render');
    $form_state->set('result', $result);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  public function qrcode(array &$form, FormStateInterface $form_state) {
    $result = $form_state->get('result');
    $return = [
      '#type'         => 'yunke_qrcode',
      '#text'         => $result['text'],
      '#width'        => $result['width'],
      '#height'       => $result['height'],
      '#correctLevel' => $result['correctLevel'],
      '#background'   => $result['background'],
      '#foreground'   => $result['foreground'],
      '#tag'          => $result['tag'],
      '#render'       => $result['render'],
    ];
    return $return;
  }

}
