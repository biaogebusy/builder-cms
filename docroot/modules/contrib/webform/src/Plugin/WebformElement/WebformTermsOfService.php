<?php

namespace Drupal\webform\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformHtmlEditor;
use Drupal\webform\Element\WebformTermsOfService as WebformTermsOfServiceElement;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'terms_of_service' element.
 *
 * @WebformElement(
 *   id = "webform_terms_of_service",
 *   default_key = "terms_of_service",
 *   label = @Translation("Terms of service"),
 *   description = @Translation("Provides a terms of service element."),
 *   category = @Translation("Advanced elements"),
 * )
 */
class WebformTermsOfService extends Checkbox {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $properties = [
      'title' => $this->t('I agree to the {terms of service}.'),
      'terms_type' => WebformTermsOfServiceElement::TERMS_MODAL,
      'terms_title' => '',
      'terms_content' => '',
      'terms_link' => '',
      'terms_link_target' => '',
    ] + parent::defineDefaultProperties();
    unset(
      $properties['field_prefix'],
      $properties['field_suffix'],
      $properties['description'],
      $properties['description_display'],
      $properties['title_display']
    );
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineTranslatableProperties() {
    return array_merge(parent::defineTranslatableProperties(), ['terms_title', 'terms_content', 'terms_link', 'terms_link_target']);
  }

  /* ************************************************************************ */

  /**
   * {@inheritdoc}
   */
  public function initialize(array &$element) {
    // Set default #title.
    if (empty($element['#title'])) {
      $element['#title'] = $this->getDefaultProperty('title');
    }

    // Backup #title and remove curly brackets.
    // Curly brackets are used to add link to #title when it is rendered.
    // @see \Drupal\webform\Element\WebformTermsOfService::preRenderCheckbox
    $element['#_webform_terms_of_service_title'] = $element['#title'];
    $element['#title'] = str_replace(['{', '}'], ['', ''], $element['#title']);

    parent::initialize($element);
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, ?WebformSubmissionInterface $webform_submission = NULL) {
    // Restore #title with curly brackets.
    if (isset($element['#_webform_terms_of_service_title'])) {
      $element['#title'] = $element['#_webform_terms_of_service_title'];
      unset($element['#_webform_terms_of_service_title']);
    }

    parent::prepare($element, $webform_submission);

    if (isset($element['#terms_content'])) {
      $element['#terms_content'] = WebformHtmlEditor::checkMarkup($element['#terms_content']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preview() {
    return [
      '#type' => $this->getTypeName(),
      '#title' => $this->t('I agree to the {terms of service}.'),
      '#required' => TRUE,
      '#terms_type' => WebformTermsOfServiceElement::TERMS_SLIDEOUT,
      '#terms_content' => '<em>' . $this->t('These are the terms of service.') . '</em>',
      '#terms_link' => '/node/1',
      '#terms_link_target' => '_self',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['element']['title']['#description'] = $this->t('In order to create a link to your terms, wrap the words where you want your link to be in curly brackets.');

    $form['terms_of_service'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Terms of service settings'),
    ];
    $form['terms_of_service']['terms_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Terms display'),
      '#options' => [
        WebformTermsOfServiceElement::TERMS_MODAL => $this->t('Modal'),
        WebformTermsOfServiceElement::TERMS_SLIDEOUT => $this->t('Slideout'),
        WebformTermsOfServiceElement::TERMS_LINK => $this->t('Link'),
      ],
    ];
    // Modal/slideout fields.
    $form['terms_of_service']['terms_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Terms title'),
      '#states' => [
        'visible' => [
          ':input[name="properties[terms_type]"]' => [
            ['value' => WebformTermsOfServiceElement::TERMS_MODAL],
            'or',
            ['value' => WebformTermsOfServiceElement::TERMS_SLIDEOUT],
          ],
        ],
      ],
    ];
    $form['terms_of_service']['terms_content'] = [
      '#type' => 'webform_html_editor',
      '#title' => $this->t('Terms content'),
      '#states' => [
        'visible' => [
          ':input[name="properties[terms_type]"]' => [
            ['value' => WebformTermsOfServiceElement::TERMS_MODAL],
            'or',
            ['value' => WebformTermsOfServiceElement::TERMS_SLIDEOUT],
          ],
        ],
        'required' => [
          ':input[name="properties[terms_type]"]' => [
            ['value' => WebformTermsOfServiceElement::TERMS_MODAL],
            'or',
            ['value' => WebformTermsOfServiceElement::TERMS_SLIDEOUT],
          ],
        ],
      ],
    ];
    // Link fields.
    $form['terms_of_service']['terms_link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Terms link'),
      '#description' => $this->t('Enter the URL or path of the terms of service.'),
      '#states' => [
        'visible' => [
          ':input[name="properties[terms_type]"]' => ['value' => WebformTermsOfServiceElement::TERMS_LINK],
        ],
        'required' => [
          ':input[name="properties[terms_type]"]' => ['value' => WebformTermsOfServiceElement::TERMS_LINK],
        ],
      ],
    ];
    $form['terms_of_service']['terms_link_target'] = [
      '#type' => 'select',
      '#title' => $this->t('Terms link target'),
      '#options' => [
        '' => $this->t('Current window (_self)'),
        '_blank' => $this->t('New window (_blank)'),
        'parent' => $this->t('Parent window (_parent)'),
        'top' => $this->t('Topmost window (_top)'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="properties[terms_type]"]' => ['value' => WebformTermsOfServiceElement::TERMS_LINK],
        ],
      ],
    ];
    return $form;
  }

}
