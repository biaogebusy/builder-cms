<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_productivity_pack\Form\ContentTemplates;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * CKEditor5 Content Template form.
 */
class CKEditor5TemplateEntityForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $availableFormats = $this->getAvailableTextFormats();

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the CKEditor5 Content Template.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => 'Drupal\ckeditor5_premium_features_productivity_pack\Entity\CKEditor5Template::load',
      ],
      '#disabled' => !$this->entity->isNew(),
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#rows' => 3,
    ];

    $form['icon'] = [
      '#type' => 'textarea',
      '#title' => $this->t('SVG icon code'),
      '#default_value' => $this->entity->get('icon'),
      '#description' => $this->t('The SVG code.<br /><strong>Attributes: "viewBox" and "xmlns" are required</strong>'),
      '#required' => FALSE,
    ];

    $form['icon_preview_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Icon preview'),
      '#open' => FALSE,
    ];

    $form['icon_preview_container']['icon_preview_button'] = [
      '#type' => 'button',
      '#id' => 'cke5-icon-preview-button',
      '#executes_submit_callback' => FALSE,
      '#ajax' => ['callback' => [$this, 'iconPreview']],
      '#value' => $this->t('Refresh Icon Preview'),
    ];

    $form['icon_preview_container']['template_preview'] = [
      '#type' => 'container',
      '#id' => 'cke5-content-icon-container',
    ];

    $form['data'] = [
      '#type' => 'textarea',
      '#id' => 'cke5-template-data',
      '#title' => $this->t('HTML Code'),
      '#default_value' => $this->entity->get('data'),
      '#description' => $this->t('The HTML code for CKEditor5 template'),
      '#required' => TRUE,
      '#rows' => 15,
    ];

    $form['template_preview_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Template preview'),
      '#open' => FALSE,
    ];

    $form['template_preview_container']['preview_button'] = [
      '#type' => 'button',
      '#id' => 'cke5-template-preview-button',
      '#executes_submit_callback' => FALSE,
      '#ajax' => ['callback' => [$this, 'templatePreview']],
      '#value' => $this->t('Refresh Template preview'),
    ];

    $form['template_preview_container']['template_preview'] = [
      '#type' => 'container',
      '#id' => 'cke5-content-template-container',
    ];

    $form['textFormats'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available For'),
      '#default_value' => $this->entity->get('textFormats') ?? [],
      '#options' => $availableFormats,
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => !$this->entity->isNew() ? $this->entity->status() : TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new CKEditor5 Content Template: %label.', $message_args)
      : $this->t('Updated CKEditor5 Content Template: %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * Gets list of TextFormats with CKEditor5.
   */
  protected function getAvailableTextFormats(): array {
    $availableFormats = [];
    $filterFormats = filter_formats();
    foreach ($filterFormats as $format) {
      $editor = $this->entityTypeManager->getStorage('editor')->load($format->id());
      if ($editor && $editor->getEditor() === 'ckeditor5') {
        $availableFormats[$format->id()] = $format->label();
      }
    }
    return $availableFormats;
  }

  /**
   * Refresh preview of the template.
   *
   * @param array $form
   *   The Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function templatePreview(array $form, FormStateInterface $form_state): AjaxResponse {
    $data = $form_state->getValue('data');
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#cke5-content-template-container', $data));
    return $response;
  }

  /**
   * Refresh preview of the icon.
   *
   * @param array $form
   *   The Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function iconPreview(array $form, FormStateInterface $form_state): AjaxResponse {
    $data = $form_state->getValue('icon');
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#cke5-content-icon-container', $data));
    return $response;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if (str_contains($trigger['#id'], 'cke5-template-preview-button') || str_contains($trigger['#id'], 'cke5-icon-preview-button')) {
      $form_state->clearErrors();
      return;
    }
    parent::validateForm($form, $form_state);
    $iconField = $form_state->getValue('icon');
    if ($iconField && !preg_match('/^<svg\b[^>]*\s*(?=.*viewBox=\"\b[^>]*\")(?=.*xmlns=\"\b[^>]*\").*?>[\s\S]*?<\/svg>/', $iconField)) {
      $form_state->setErrorByName('icon', $this->t('Wrong icon format. Make sure that svg code is valid and contains proper <strong>viewBox</strong> and <strong>xmlns</strong> attributes.'));
    }
  }

}
