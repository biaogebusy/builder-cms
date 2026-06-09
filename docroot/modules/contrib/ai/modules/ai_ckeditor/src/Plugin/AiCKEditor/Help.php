<?php

namespace Drupal\ai_ckeditor\Plugin\AiCKEditor;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;

/**
 * Plugin to display help and support.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_help',
  label: new TranslatableMarkup('Help and Support'),
  description: new TranslatableMarkup('Information on where to get AI help and support.'),
  module_dependencies: [],
)]
final class Help extends AiCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []) {
    return [
      '#markup' => '<p>' . $this->t('For help and support, please <a href=":href" target="_blank">visit the issue queue.</a>', [':href' => 'https://www.drupal.org/project/issues/ai?categories=All']) . '</p>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

}
