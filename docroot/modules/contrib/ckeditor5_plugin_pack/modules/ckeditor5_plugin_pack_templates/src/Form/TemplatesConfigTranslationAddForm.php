<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_templates\Form;

use Drupal\config_translation\Form\ConfigTranslationAddForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;

class TemplatesConfigTranslationAddForm extends ConfigTranslationAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RouteMatchInterface $route_match = NULL, $plugin_id = NULL, $langcode = NULL) {
    $form = parent::buildForm($form, $form_state, $route_match, $plugin_id, $langcode);

    foreach ($form["config_names"] as $key => $item) {
      if (!is_array($item)) {
        continue;
      }

      $form["config_names"][$key]['data']['source'] = [
        '#type' => 'textarea',
        '#title' => $this->t('HTML Code'),
        '#default_value' => $item['data']['source']['#markup'],
        '#attributes' => ['disabled' => 'disabled'],
        '#rows' => 15,
      ];
      $form["config_names"][$key]['data']['translation']['#rows'] = 15;
    }

    return $form;
  }
}
