<?php

namespace Drupal\ai_ckeditor\Plugin\AiCKEditor;

use Drupal\ai\Utility\Textarea;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Drupal\ai_ckeditor\Command\AiRequestCommand;
use Drupal\taxonomy\Entity\Term;

/**
 * Plugin to convert tone of selected text.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_tone',
  label: new TranslatableMarkup('Tone'),
  description: new TranslatableMarkup('Convert tone of selected text.'),
  module_dependencies: ['taxonomy'],
)]
final class Tone extends AiCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'autocreate' => FALSE,
      'provider' => NULL,
      'tone_vocabulary' => NULL,
      'use_description' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();

    if (empty($vocabularies)) {
      return [
        '#markup' => 'You must add at least one taxonomy vocabulary before you can configure this plugin.',
      ];
    }

    $vocabulary_options = [];

    foreach ($vocabularies as $vocabulary) {
      $vocabulary_options[$vocabulary->id()] = $vocabulary->label();
    }

    $form['tone_vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose default vocabulary for tone options'),
      '#options' => $vocabulary_options,
      '#description' => $this->t('Select the vocabulary that contains tone options.'),
      '#default_value' => $this->configuration['tone_vocabulary'],
    ];

    $form['autocreate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow autocreate'),
      '#description' => $this->t('If enabled, users with access to this format are able to autocreate new terms in the chosen vocabulary, instead of a select list.'),
      '#default_value' => $this->configuration['autocreate'] ?? FALSE,
    ];

    $form['use_description'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use term description for tone description'),
      '#description' => $this->t('If enabled and a description field is filled out, the tone will use this description to explain how the AI should rewrite in that tone of voice.'),
      '#default_value' => $this->configuration['use_description'] ?? FALSE,
    ];

    $options = $this->aiProviderManager->getSimpleProviderModelOptions('chat', FALSE);
    $form['provider'] = [
      '#type' => 'select',
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
      '#title' => $this->t('AI provider'),
      '#options' => $options,
      '#default_value' => $this->configuration['provider'] ?? $this->aiProviderManager->getSimpleDefaultProviderOptions('chat'),
      '#description' => $this->t('Select which provider to use for this plugin. See the <a href=":link">Provider overview</a> for details about each provider.', [':link' => '/admin/config/ai/providers']),
    ];
    $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
    $prompt_tone = $prompts_config->get('prompts.tone');
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Change tone prompt'),
      '#default_value' => $prompt_tone,
      '#description' => $this->t('This prompt will be used to change the tone of voice. {{ tone }} is the target tone of voice that is chosen.'),
      '#states' => [
        'required' => [
          ':input[name="editor[settings][plugins][ai_ckeditor_ai][plugins][ai_ckeditor_tone][enabled]"]' => ['checked' => TRUE],
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
  protected function getGenerateButtonLabel() {
    return $this->t('Change the tone');
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectedTextLabel() {
    return $this->t('Selected text to convert');
  }

  /**
   * {@inheritdoc}
   */
  protected function getAiResponseLabel() {
    return $this->t('Suggested conversion');
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['provider'] = $form_state->getValue('provider');
    $this->configuration['autocreate'] = (bool) $form_state->getValue('autocreate');
    $this->configuration['tone_vocabulary'] = $form_state->getValue('tone_vocabulary');
    $this->configuration['use_description'] = (bool) $form_state->getValue('use_description');
    $newPrompt = $form_state->getValue('prompt');
    $prompts_config = $this->getConfigFactory()->getEditable('ai_ckeditor.settings');
    $prompts_config->set('prompts.tone', $newPrompt)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []) {
    $form = parent::buildCkEditorModalForm($form, $form_state);

    $form['tone'] = [
      '#type' => $this->configuration['autocreate'] ? 'entity_autocomplete' : 'select',
      '#title' => $this->t('Choose tone'),
      '#tags' => FALSE,
      '#required' => TRUE,
      '#description' => $this->t('Selecting one of the options will adjust/reword the body content to be appropriate for the target audience.'),
    ];

    if ($this->configuration['autocreate']) {
      $form['tone']['#target_type'] = 'taxonomy_term';
      $form['tone']['#selection_settings'] = [
        'target_bundles' => [$this->configuration['tone_vocabulary']],
      ];
    }
    else {
      $form['tone']['#options'] = $this->getTermOptions($this->configuration['tone_vocabulary']);
    }

    if ($this->configuration['autocreate'] && $this->account->hasPermission('create terms in ' . $this->configuration['tone_vocabulary'])) {
      $form['tone']['#autocreate'] = [
        'bundle' => $this->configuration['tone_vocabulary'],
      ];
    }

    return $form;
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
      if (is_array($values['plugin_config']['tone']) && reset($values['plugin_config']['tone']) instanceof Term) {
        $term = reset($values['plugin_config']['tone']);
      }
      else {
        $term = $this->entityTypeManager->getStorage('taxonomy_term')
          ->load($values['plugin_config']['tone']);
      }

      if (empty($term)) {
        throw new \Exception('Term could not be loaded.');
      }

      if ($term->isNew() && $this->configuration['autocreate'] && $this->account->hasPermission('create terms in ' . $this->configuration['tone_vocabulary'])) {
        $term->save();
      }
      $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
      $prompt = $prompts_config->get('prompts.tone');
      $prompt = str_replace('{{ tone }}', $term->label(), $prompt);
      if ($this->configuration['use_description'] && !empty($term->getDescription())) {
        $prompt .= 'That tone can described as: ' . strip_tags($term->getDescription());
      }
      $prompt .= "\n\nThe text that we want to change is the following:\n" . $values['plugin_config']['selected_text'];
      $response = new AjaxResponse();
      $values = $form_state->getValues();
      $response->addCommand(new AiRequestCommand($prompt, $values['editor_id'], $this->pluginDefinition['id'], 'ai-ckeditor-response'));
      return $response;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_ckeditor')->error("There was an error in the Tone AI plugin for CKEditor.");
      return $form['plugin_config']['response_wrapper']['response_text']['#value'] = 'There was an error in the Tone AI plugin for CKEditor.';
    }
  }

  /**
   * Helper function to get all terms as an options array.
   *
   * @param string $vid
   *   The vocabulary ID.
   *
   * @return array
   *   The options array.
   */
  protected function getTermOptions(string $vid): array {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid);
    $options = [];

    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    return $options;
  }

}
