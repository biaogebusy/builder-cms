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
 * Plugin to summarize the selected text.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_summarize',
  label: new TranslatableMarkup('Summarize'),
  description: new TranslatableMarkup('Summarize the currently selected text.'),
  module_dependencies: [],
)]
final class Summarize extends AiCKEditorPluginBase {

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
    $prompt_summarise = $prompts_config->get('prompts.summarise');
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Summarise prompt'),
      '#default_value' => $prompt_summarise,
      '#description' => $this->t('This prompt will be used to summarise the text.'),
      '#states' => [
        'required' => [
          ':input[name="editor[settings][plugins][ai_ckeditor_ai][plugins][ai_ckeditor_summarize][enabled]"]' => ['checked' => TRUE],
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
    $prompts_config->set('prompts.summarise', $newPrompt)->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getGenerateButtonLabel() {
    return $this->t('Summarize');
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectedTextLabel() {
    return $this->t('Selected text to summarize');
  }

  /**
   * {@inheritdoc}
   */
  protected function getAiResponseLabel() {
    return $this->t('Suggested summary');
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
    $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
    $prompt = $prompts_config->get('prompts.summarise');
    try {
      $prompt .= '"' . $values['plugin_config']['selected_text'] . '"';
      $response = new AjaxResponse();
      $values = $form_state->getValues();
      $response->addCommand(new AiRequestCommand($prompt, $values['editor_id'], $this->pluginDefinition['id'], 'ai-ckeditor-response'));
      return $response;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_ckeditor')->error("There was an error in the Summarize AI plugin for CKEditor.");
      return $form['plugin_config']['response_wrapper']['response_text']['#value'] = 'There was an error in the Summarize AI plugin for CKEditor.';
    }
  }

}
