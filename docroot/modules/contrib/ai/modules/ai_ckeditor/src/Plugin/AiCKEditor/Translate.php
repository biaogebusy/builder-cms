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
 * Plugin to translate the language of selected text.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_translate',
  label: new TranslatableMarkup('Translate'),
  description: new TranslatableMarkup('Translate the selected text into other languages.'),
  module_dependencies: ['taxonomy'],
)]
final class Translate extends AiCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'autocreate' => FALSE,
      'provider' => NULL,
      'translate_vocabulary' => NULL,
      'use_description' => FALSE,
      'language_source' => 'tax',
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

    $form['language_source'] = [
      '#type' => 'select',
      '#options' => [
        'lang' => $this->t('Language'),
        'tax' => $this->t('Taxonomy term'),
      ],
      '#title' => $this->t('Use languages or taxonomy terms for language selection.'),
      '#default_value' => $this->configuration['language_source'] ?? FALSE,
    ];

    $form['translate_vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose default vocabulary for translation options'),
      '#options' => $vocabulary_options,
      '#description' => $this->t('Select the vocabulary that contains translation options.'),
      '#default_value' => $this->configuration['translate_vocabulary'],
      '#states' => [
        'visible' => [
          'select[name="editor[settings][plugins][ai_ckeditor_ai][plugins][ai_ckeditor_translate][language_source]"]' => ['value' => 'tax'],
        ],
      ],
    ];

    $form['autocreate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow autocreate'),
      '#description' => $this->t('If enabled, users with access to this format are able to autocreate new terms in the chosen vocabulary, instead of a select list..'),
      '#default_value' => $this->configuration['autocreate'] ?? FALSE,
      '#states' => [
        'visible' => [
          'select[name="editor[settings][plugins][ai_ckeditor_ai][plugins][ai_ckeditor_translate][language_source]"]' => ['value' => 'tax'],
        ],
      ],
    ];

    $form['use_description'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use term description for translation context'),
      '#description' => $this->t('If enabled and a description field is filled out, the translation will use this description to explain things the AI should think about when translating.'),
      '#default_value' => $this->configuration['use_description'] ?? FALSE,
      '#states' => [
        'visible' => [
          'select[name="editor[settings][plugins][ai_ckeditor_ai][plugins][ai_ckeditor_translate][language_source]"]' => ['value' => 'tax'],
        ],
      ],
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
    $prompt_translate = $prompts_config->get('prompts.translate');
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Change translation prompt'),
      '#default_value' => $prompt_translate,
      '#description' => $this->t('This prompt will be used to translate the text. {{ lang }} is the target language that is chosen.'),
      '#states' => [
        'required' => [
          ':input[name="editor[settings][plugins][ai_ckeditor_ai][plugins][ai_ckeditor_translate][enabled]"]' => ['checked' => TRUE],
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['provider'] = $form_state->getValue('provider');
    $this->configuration['language_source'] = $form_state->getValue('language_source');
    $this->configuration['autocreate'] = (bool) $form_state->getValue('autocreate');
    $this->configuration['translate_vocabulary'] = $form_state->getValue('translate_vocabulary');
    $this->configuration['use_description'] = (bool) $form_state->getValue('use_description');
    $newPrompt = $form_state->getValue('prompt');
    $prompts_config = $this->getConfigFactory()->getEditable('ai_ckeditor.settings');
    $prompts_config->set('prompts.translate', $newPrompt)->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getGenerateButtonLabel() {
    return $this->t('Translate');
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectedTextLabel() {
    return $this->t('Selected text to translate');
  }

  /**
   * {@inheritdoc}
   */
  protected function getAiResponseLabel() {
    return $this->t('Suggested translation');
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []) {
    $form = parent::buildCkEditorModalForm($form, $form_state);

    $autocreate = $this->configuration['autocreate'] && $this->configuration['language_source'] == 'tax';

    $form['language'] = [
      '#type' => $autocreate ? 'entity_autocomplete' : 'select',
      '#title' => $this->t('Choose language'),
      '#tags' => FALSE,
      '#required' => TRUE,
      '#weight' => 3,
      '#description' => $this->t('Selecting one of the options will translate the selected text.'),
    ];

    if ($autocreate) {
      $form['language']['#target_type'] = 'taxonomy_term';
      $form['language']['#selection_settings'] = [
        'target_bundles' => [$this->configuration['translate_vocabulary']],
      ];

      if ($this->account->hasPermission('create terms in ' . $this->configuration['translate_vocabulary'])) {
        $form['language']['#autocreate'] = [
          'bundle' => $this->configuration['translate_vocabulary'],
        ];
      }
    }
    else {
      if ($this->configuration['language_source'] == 'tax') {
        $form['language']['#options'] = $this->getTermOptions($this->configuration['translate_vocabulary']);
      }
      else {
        $site_languages = $this->languageManager->getLanguages();
        $form['language']['#options'] = [];
        foreach ($site_languages as $langcode => $language) {
          $form['language']['#options'][$langcode] = $language->getName();
        }
        // Set default value if only one language is available.
        if (count($form['language']['#options']) === 1) {
          $form['language']['#default_value'] = key($form['language']['#options']);
        }
      }
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
      $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
      $prompt = $prompts_config->get('prompts.translate');

      if ($this->configuration['language_source'] == 'lang') {
        $site_languages = $this->languageManager->getLanguages();
        $langName = $site_languages[$values['plugin_config']['language']]->getName();
        $prompt = str_replace('{{ lang }}', $langName . ' (' . $values['plugin_config']['language'] . ')', $prompt);
      }
      else {
        // Handle taxonomy terms.
        if (is_array($values['plugin_config']['language']) && reset($values['plugin_config']['language']) instanceof Term) {
          $term = reset($values['plugin_config']['language']);
        }
        else {
          $term = $this->entityTypeManager->getStorage('taxonomy_term')
            ->load($values['plugin_config']['language']);
        }

        if (empty($term)) {
          throw new \Exception('Language term could not be loaded.');
        }

        if ($term->isNew() && $this->configuration['autocreate'] && $this->account->hasPermission('create terms in ' . $this->configuration['translate_vocabulary'])) {
          $term->save();
        }

        $prompt = str_replace('{{ lang }}', $term->label(), $prompt);
        if ($this->configuration['use_description'] && !empty($term->getDescription())) {
          $prompt .= ' Translation context: ' . strip_tags($term->getDescription());
        }
      }

      $prompt .= "\n\nThe text that we want to translate is the following:\n" . $values['plugin_config']['selected_text'];
      $response = new AjaxResponse();
      $response->addCommand(new AiRequestCommand($prompt, $values["editor_id"], $this->pluginDefinition['id'], 'ai-ckeditor-response'));
      return $response;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_ckeditor')->error("There was an error in the Translate AI plugin for CKEditor: @message", [
        '@message' => $e->getMessage(),
      ]);

      return $form['plugin_config']['response_wrapper']['response_text']['#value'] = 'There was an error in the Translate AI plugin for CKEditor.';
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
    /** @var \Drupal\taxonomy\TermStorageInterface $voc */
    $voc = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $voc->loadTree($vid);
    $options = [];

    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    return $options;
  }

}
