<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Plugin\AiContentSuggestions;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_content_suggestions\AiContentSuggestionsPluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the ai_content_suggestions.
 *
 * @AiContentSuggestions(
 *   id = "readability",
 *   label = @Translation("Evaluate Readability"),
 *   description = @Translation("Allow an LLM to score the readability of the content."),
 *   operation_type = "chat"
 * )
 */
final class Readability extends AiContentSuggestionsPluginBase {

  /**
   * The Default prompt for this functionality.
   *
   * @var string
   */
  private string $defaultPrompt = 'Provide a Flesch score of the following text as well as one sentence description of how that score should be interpreted.
      Afterward, provide a brief list of suggested improvements to enhance readability, focusing on sentence length, word complexity, and overall structure.
      Return it like so (in html), don\'t answer with other things than the score, the brief explanation and suggestions. no pleasantries or greetings.
      Always answer in the following output format:
````
<p class="score">
<b>Score</b>: SCORE<br>
<b>Explanation</b>: ONE SENTENCE TO EXPLAIN<br>
<b>Suggestions</b>: <ul><li>SUGGESTION 1</li></ul>
</p>
````

    Apart form the formatted output, return nothing else.

      The input text:';

  /**
   * Configuration object for this plugin.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $promptConfig;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AiProviderPluginManager $providerPluginManager,
    ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $providerPluginManager, $configFactory);

    $this->promptConfig = $configFactory->getEditable('ai_content_suggestions.prompts');
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, array $fields): void {
    $form[$this->getPluginId()] = $this->getAlterFormTemplate($fields);
    $form[$this->getPluginId()][$this->getPluginId() . '_submit']['#value'] = $this->t('Score readability');
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(&$form): void {
    parent::buildSettingsForm($form);
    $prompt = $this->promptConfig->get($this->getPluginId());
    $form[$this->getPluginId()][$this->getPluginId() . '_prompt'] = [
      '#title' => $this->t('Evaluate readability prompt', []),
      '#type' => 'textarea',
      '#required' => TRUE,
      '#default_value' => $prompt ?? $this->defaultPrompt . PHP_EOL,
      '#parents' => ['plugins', $this->getPluginId(), $this->getPluginId() . '_prompt'],
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getPluginId() . '[' . $this->getPluginId() . '_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function saveSettingsForm(array &$form, FormStateInterface $form_state): void {
    $value = $form_state->getValue(['plugins', $this->getPluginId()]);
    $prompt = $value[$this->getPluginId() . '_prompt'];
    $this->promptConfig->set($this->getPluginId(), $prompt)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function updateFormWithResponse(array &$form, FormStateInterface $form_state): void {
    if ($value = $this->getTargetFieldValue($form_state)) {
      $readability_prompt = $this->promptConfig->get('readability');
      if (!empty($readability_prompt)) {
        $prompt = $readability_prompt;
      }
      else {
        $prompt = $this->defaultPrompt . '
      :\r\n
      ';
      }

      $output = $this->sendChat($prompt . $value . '"');
      $cleanedResult = trim(str_replace('````', "", trim($output)));
      $cleanedResult = trim(str_replace('```', "", trim($cleanedResult)));
      $message = (!empty($cleanedResult)) ? $cleanedResult : $this->t('No result could be generated.');
    }
    else {
      $message = $this->t('The selected field has no text. Please supply content to evaluate.');
    }
    $form[$this->getPluginId()]['response']['response']['#context']['response']['response'] = [
      '#markup' => $message,
      '#weight' => 100,
    ];
  }

}
