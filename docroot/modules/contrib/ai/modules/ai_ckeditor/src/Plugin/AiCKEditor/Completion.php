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
 * Plugin to do AI completion.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_completion',
  label: new TranslatableMarkup('Generate with AI'),
  description: new TranslatableMarkup('Get ideas and text completion assistance from AI.'),
  module_dependencies: [],
)]
final class Completion extends AiCKEditorPluginBase {

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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
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
    $prompt_complete = $prompts_config->get('prompts.complete');
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Completion pre prompt'),
      '#default_value' => $prompt_complete ?? '',
      '#description' => $this->t('This prompt will be prepended before the user prompt. This field may be left empty too.'),
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['provider'] = $form_state->getValue('provider');
    $newPrompt = $form_state->getValue('prompt');
    $prompts_config = $this->getConfigFactory()->getEditable('ai_ckeditor.settings');
    $prompts_config->set('prompts.complete', $newPrompt)->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function needsSelectedText() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []): array {
    $form = parent::buildCkEditorModalForm($form, $form_state);

    $form['text_to_submit'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What would you like to ask or get ideas for?'),
      '#default_value' => '',
      '#required' => TRUE,
      // Ensure this comes before the generate button.
      '#weight' => 5,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxGenerate(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $values = $form_state->getValues();
    $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
    $prompt_complete = $prompts_config->get('prompts.complete');
    if (!empty($prompt_complete)) {
      $prompt = $prompt_complete . PHP_EOL . $values['plugin_config']['text_to_submit'];
    }
    else {
      $prompt = $values['plugin_config']['text_to_submit'];
    }
    $response->addCommand(new AiRequestCommand($prompt, $values["editor_id"], $this->pluginDefinition['id'], 'ai-ckeditor-response'));

    return $response;
  }

}
