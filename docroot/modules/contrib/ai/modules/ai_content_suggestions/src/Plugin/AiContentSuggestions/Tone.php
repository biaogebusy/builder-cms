<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Plugin\AiContentSuggestions;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_content_suggestions\AiContentSuggestionsPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the ai_content_suggestions.
 *
 * @AiContentSuggestions(
 *   id = "tone",
 *   label = @Translation("Alter tone"),
 *   description = @Translation("Allow an LLM to provide tone suggestions about the content."),
 *   operation_type = "chat"
 * )
 */
final class Tone extends AiContentSuggestionsPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The ai provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * Configuration object for this plugin.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $toneConfig;

  /**
   * The Default prompt for this functionality.
   *
   * @var string
   */
  private string $defaultPrompt = 'Change the tone of the following text to be {{ tone }} using the same language as the following text:';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Configuration object for this plugin.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $promptConfig;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AiProviderPluginManager $providerPluginManager,
    ConfigFactoryInterface $configFactory,
    EntityTypeManager $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $providerPluginManager, $configFactory);
    $this->config = $configFactory->get('ai_content_suggestions.settings');
    $this->providerManager = $providerPluginManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->toneConfig = $configFactory->getEditable('ai_content_suggestions.tone');
    $this->promptConfig = $configFactory->getEditable('ai_content_suggestions.prompts');
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, array $fields): void {
    $form[$this->getPluginId()] = $this->getAlterFormTemplate($fields);
    $config = $this->toneConfig;
    $options = [
      'friendly' => $this->t('Friendly'),
      'professional' => $this->t('Professional'),
      'helpful' => $this->t('Helpful'),
      'easier for a high school educated reader' => $this->t('High school level reader'),
      'easier for a college educated reader' => $this->t('College level reader'),
      'explained to a five year old' => $this->t("Explain like I'm 5"),
    ];
    if ($config->get($this->getPluginId() . '_taxonomy_enabled') && $config->get($this->getPluginId() . '_taxonomy') !== NULL && $config->get($this->getPluginId() . '_taxonomy') != '') {
      $terms = $this->getTerms($config->get($this->getPluginId() . '_taxonomy'));
      $terms = array_combine($terms, $terms);
      $options = $terms;
    }
    $form[$this->getPluginId()]['tone'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose tone'),
      '#description' => $this->t('Selecting one of the options will adjust/reword the body content to be appropriate for the target audience.'),
      '#options' => $options,
      '#weight' => 0,
    ];
    $form[$this->getPluginId()][$this->getPluginId() . '_submit']['#value'] = $this->t('Adjust Tone');
  }

  /**
   * Get the terms in array format.
   *
   * @param string $source_vocabulary
   *   The source vocabulary.
   *
   * @return array|false
   *   The array of the terms.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTerms(string $source_vocabulary): array|bool {

    // Use the loadTree to avoid loading all the terms.
    /** @var \Drupal\taxonomy\TermStorage $terms_storage */
    $terms_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms_tree = $terms_storage->loadTree($source_vocabulary);

    // Now run an extra entity query, to ensure access check.
    $query = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getQuery();
    $query->condition('vid', $source_vocabulary);
    $query->accessCheck();

    $accessible_terms = $query->execute();

    $terms = [];
    foreach ($terms_tree as $term) {
      $tid = $term->tid;
      if (!in_array($tid, $accessible_terms)) {
        continue;
      }
      $terms[] = $term->name;
    }
    return $terms;
  }

  /**
   * {@inheritdoc}
   */
  public function updateFormWithResponse(array &$form, FormStateInterface $form_state): void {
    $tone_prompt = $this->promptConfig->get($this->getPluginId());
    if (!empty($tone_prompt)) {
      $prompt = $tone_prompt;
    }
    else {
      $prompt = $this->defaultPrompt . '\r\n';
    }

    if ($value = $this->getTargetFieldValue($form_state)) {
      if ($tone = $this->getFormFieldValue('tone', $form_state)) {
        $prompt = str_replace('{{ tone }}', $tone, $prompt);
        $message = $this->sendChat($prompt . $value . '"');
      }
      else {
        $message = $this->t('Please select a tone for the LLM to suggest.');
      }
    }
    else {
      $message = $this->t('The selected field has no text. Please supply content to the field.');
    }

    $form[$this->getPluginId()]['response']['response']['#context']['response']['response'] = [
      '#markup' => $message,
      '#weight' => 100,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array &$form): void {
    parent::buildSettingsForm($form);

    $prompt = $this->promptConfig->get($this->getPluginId());
    $form[$this->getPluginId()][$this->getPluginId() . '_prompt'] = [
      '#title' => $this->t('Tone of voice prompt', []),
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

    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    $vocabulary_options = [];
    foreach ($vocabularies as $vocabulary) {
      $terms_exist = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
        ->condition('vid', $vocabulary->id())
        ->range(0, 1)
        ->accessCheck()
        ->execute();

      if (!empty($terms_exist)) {
        $vocabulary_options[$vocabulary->id()] = $vocabulary->label();
      }
    }

    if (!empty($vocabulary_options)) {
      $form[$this->getPluginId()][$this->getPluginId() . '_taxonomy_enabled'] = [
        '#parents' => [
          'plugins',
          $this->getPluginId(),
          $this->getPluginId() . '_taxonomy_enabled',
        ],
        '#type' => 'checkbox',
        '#title' => $this->t('Choose own vocabulary for tone of voice options.'),
        '#description' => $this->t('Keeping this unselected falls back to default tone of voice options (Friendly, Professional, High school, College, Five year old).'),
        '#default_value' => !!$this->toneConfig->get($this->getPluginId() . '_taxonomy_enabled'),
        '#states' => [
          'visible' => [
            ':input[name="' . $this->getPluginId() . '[' . $this->getPluginId() . '_enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form[$this->getPluginId()][$this->getPluginId() . '_taxonomy'] = [
        '#parents' => [
          'plugins',
          $this->getPluginId(),
          $this->getPluginId() . '_taxonomy',
        ],
        '#type' => 'select',
        '#title' => $this->t('Choose vocabulary for tone options'),
        '#options' => $vocabulary_options,
        '#description' => $this->t('Select the vocabulary that contains tone options.'),
        '#default_value' => $this->toneConfig->get($this->getPluginId() . '_taxonomy'),
        '#states' => [
          'visible' => [
            ':input[name="' . $this->getPluginId() . '[' . $this->getPluginId() . '_enabled]"]' => ['checked' => TRUE],
            ':input[name="plugins[' . $this->getPluginId() . '][' . $this->getPluginId() . '_taxonomy_enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveSettingsForm(array &$form, FormStateInterface $form_state): void {
    $value = $form_state->getValue(['plugins', $this->getPluginId()]);
    $taxonomy = $value[$this->getPluginId() . '_taxonomy'] ?? '';
    $this->toneConfig->set($this->getPluginId() . '_taxonomy', $taxonomy)->save();
    $taxonomy_enabled = $value[$this->getPluginId() . '_taxonomy_enabled'] ?? 0;
    $this->toneConfig->set($this->getPluginId() . '_taxonomy_enabled', (bool) $taxonomy_enabled)->save();
    $prompt = $value[$this->getPluginId() . '_prompt'] ?? $this->defaultPrompt;
    $this->promptConfig->set($this->getPluginId(), $prompt)->save();
  }

}
