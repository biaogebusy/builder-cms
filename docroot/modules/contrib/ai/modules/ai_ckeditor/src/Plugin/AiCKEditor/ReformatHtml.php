<?php

namespace Drupal\ai_ckeditor\Plugin\AiCKEditor;

use Drupal\ai\Utility\Textarea;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Drupal\ai_ckeditor\Command\AiRequestCommand;

/**
 * Plugin to reformat the selected HTML.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_reformat_html',
  label: new TranslatableMarkup('Reformat HTML'),
  description: new TranslatableMarkup('Reformat the HTML of the selected markup.'),
  module_dependencies: [],
)]
final class ReformatHtml extends AiCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'provider' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = $this->aiProviderManager->getSimpleProviderModelOptions('chat', FALSE);
    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('AI provider'),
      '#options' => $options,
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
      '#default_value' => $this->configuration['provider'] ?? $this->aiProviderManager->getSimpleDefaultProviderOptions('chat'),
      '#description' => $this->t('Select which provider to use for this plugin. See the <a href=":link">Provider overview</a> for details about each provider.', [':link' => '/admin/config/ai/providers']),
    ];

    $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
    $prompt_reformat = $prompts_config->get('prompts.reformat');
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reformat prompt'),
      '#default_value' => $prompt_reformat,
      '#description' => $this->t('This prompt will be used to reformat the html.'),
      '#states' => [
        'required' => [
          ':input[name="editor[settings][plugins][ai_ckeditor_ai][plugins][ai_ckeditor_reformat_html][enabled]"]' => ['checked' => TRUE],
        ],
      ],
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['provider'] = $form_state->getValue('provider');
    $newPrompt = $form_state->getValue('prompt');
    $prompts_config = $this->getConfigFactory()->getEditable('ai_ckeditor.settings');
    $prompts_config->set('prompts.reformat', $newPrompt)->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getGenerateButtonLabel() {
    return $this->t('Reformat');
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectedTextLabel() {
    return $this->t('Selected text/markup to reformat');
  }

  /**
   * {@inheritdoc}
   */
  protected function getAiResponseLabel() {
    return $this->t('Suggested markup');
  }

  /**
   * Generate text callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The result of the AJAX operation.
   */
  public function ajaxGenerate(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    try {
      $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
      $prompt = $prompts_config->get('prompts.reformat');
      $prompt = $prompt . '\r\n"' . $values["plugin_config"]["selected_text"];
      $response = new AjaxResponse();
      $values = $form_state->getValues();
      $response->addCommand(new AiRequestCommand($prompt, $values["editor_id"], $this->pluginDefinition['id'], 'ai-ckeditor-response'));
      return $response;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_ckeditor')->error("There was an error in the Reformat HTML plugin for CKEditor.");
      return $form['plugin_config']['response_wrapper']['response_text']['#value'] = 'There was an error in the Reformat HTML plugin for CKEditor.';
    }
  }

}
