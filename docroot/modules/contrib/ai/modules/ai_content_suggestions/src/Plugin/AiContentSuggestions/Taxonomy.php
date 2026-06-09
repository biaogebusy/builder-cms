<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Plugin\AiContentSuggestions;

use Drupal\ai\Entity\AiPrompt;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_content_suggestions\AiContentSuggestionsPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the ai_content_suggestions.
 *
 * @AiContentSuggestions(
 *   id = "taxonomy_suggest",
 *   label = @Translation("Suggest taxonomy tags"),
 *   description = @Translation("Allow an LLM to suggest SEO-friendly taxonomy tags for the content."),
 *   operation_type = "chat"
 * )
 */
final class Taxonomy extends AiContentSuggestionsPluginBase {

  /**
   * Configuration object for this plugin.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $promptConfig;

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
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AiProviderPluginManager $providerPluginManager,
    ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $providerPluginManager, $configFactory);
    $this->promptConfig = $configFactory->getEditable('ai_content_suggestions.prompts');
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, array $fields): void {
    $form[$this->getPluginId()] = $this->getAlterFormTemplate($fields);
    $form[$this->getPluginId()][$this->getPluginId() . '_submit']['#value'] = $this->t('Suggest taxonomy terms');

    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();

    if ($options = $this->getRelevantVocabularies($form_object->getEntity())) {

      // Create a checkbox, to select if a source vocabulary must be used.
      $form[$this->getPluginId()]['use_source_vocabulary'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use source vocabulary'),
        '#description' => $this->t('Check this box if you want to use a source vocabulary to suggest terms.'),
        '#weight' => 2,
      ];

      $form[$this->getPluginId()]['source_vocabulary'] = [
        '#type' => 'select',
        '#title' => $this->t('Choose vocabulary'),
        '#description' => $this->t('Optionally, select which vocabulary do you want to find the terms in.'),
        '#options' => $options,
        '#states' => [
          'visible' => [
            ':input[name="' . $this->getPluginId() . '[use_source_vocabulary]"]' => ['checked' => TRUE],
          ],
        ],
        '#weight' => 3,
      ];
      $form[$this->getPluginId()]['use_source_vocabulary_hierarchy'] = [
        '#type' => 'checkbox',
        '#title' => $this->t("Use source vocabulary's full hierarchy"),
        '#description' => $this->t("Check this box if you want to take into account the selected vocabulary's hierarchy, if such exists."),
        '#states' => [
          'visible' => [
            ':input[name="' . $this->getPluginId() . '[use_source_vocabulary]"]' => ['checked' => TRUE],
          ],
        ],
        '#weight' => 4,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(&$form): void {
    parent::buildSettingsForm($form);
    $prompt = $this->promptConfig->get($this->getPluginId() . '_open');
    $form[$this->getPluginId()][$this->getPluginId() . '_prompt_open'] = [
      '#title' => $this->t('Suggest taxonomy prompt (not limited to vocabulary)'),
      '#type' => 'ai_prompt',
      '#prompt_types' => ['suggest_tags'],
      '#required' => TRUE,
      '#default_value' => $prompt ?? 'suggest_tags__suggest_tags_default',
      '#parents' => ['plugins', $this->getPluginId(), $this->getPluginId() . '_prompt_open'],
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getPluginId() . '[' . $this->getPluginId() . '_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $prompt = $this->promptConfig->get($this->getPluginId() . '_from_voc');
    $form[$this->getPluginId()][$this->getPluginId() . '_prompt_from_voc'] = [
      '#title' => $this->t('Suggest taxonomy prompt (limited to vocabulary)'),
      '#type' => 'ai_prompt',
      '#prompt_types' => ['suggest_vocabulary'],
      '#required' => TRUE,
      '#default_value' => $prompt ?? 'suggest_vocabulary__suggest_vocabulary_default',
      '#parents' => ['plugins', $this->getPluginId(), $this->getPluginId() . '_prompt_from_voc'],
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
    $values = $form_state->getValues();
    $value = NestedArray::getValue($values, ['plugins', $this->getPluginId()]);
    $prompt_open = $value[$this->getPluginId() . '_prompt_open'];
    $this->promptConfig->set($this->getPluginId() . '_open', $prompt_open)->save();
    $prompt_from_voc = $value[$this->getPluginId() . '_prompt_from_voc'];
    $this->promptConfig->set($this->getPluginId() . '_from_voc', $prompt_from_voc)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function updateFormWithResponse(array &$form, FormStateInterface $form_state): void {
    if ($value = $this->getTargetFieldValue($form_state)) {

      // This will also default to FALSE if the field is missing, but that seems
      // a sensible action of the vocabulary settings may be missing as well.
      if ($this->getFormFieldValue('use_source_vocabulary', $form_state)) {
        $source_vocabulary = $this->getFormFieldValue('source_vocabulary', $form_state);
        $use_source_vocabulary_hierarchy = $this->getFormFieldValue('use_source_vocabulary_hierarchy', $form_state);
        $terms_json = $this->getTermsJson($source_vocabulary, $use_source_vocabulary_hierarchy);

        // Build our prompt.
        $tax_prompt_id = $this->promptConfig->get($this->getPluginId() . '_from_voc');
        $tax_prompt = AiPrompt::load($tax_prompt_id)->getPrompt();
        $prompt = '';
        if (!empty($tax_prompt)) {
          $prompt = $tax_prompt;
        }
        $prompt = $prompt . '\r\n"""' . $value . '"""\r\n\r\n';

        if ($use_source_vocabulary_hierarchy) {
          $prompt .= 'The words must be relevant and selected from the leaf nodes of this json tree, they must take into account the full hierarchy. They must be returned in a multilevel html list, containing the whole chain of names, without the IDs:\r\n ' . $terms_json;
        }
        else {
          $prompt .= 'The words must be relevant and selected from this json list, and must return in a comma delimited list:\r\n ' . $terms_json;
        }
      }
      else {
        $tax_prompt_id = $this->promptConfig->get($this->getPluginId() . '_open');
        $tax_prompt = AiPrompt::load($tax_prompt_id)->getPrompt();
        $prompt = '';
        if (!empty($tax_prompt)) {
          $prompt = $tax_prompt;
        }
        $prompt = $prompt . '\r\n"""' . $value . '"""';
      }

      $message = $this->sendChat($prompt);
    }
    else {
      $message = $this->t('The selected field has no text. Please supply content to the field.');
    }

    $form[$this->getPluginId()]['response']['response']['#context']['response']['response'] = [
      '#markup' => $message,
      '#weight' => 50,
    ];
  }

  /**
   * Get the relevant vocabularies.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being edited.
   *
   * @return array
   *   The relevant vocabularies.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getRelevantVocabularies(EntityInterface $entity): array {
    // Get all vocabularies.
    $all_vocabularies = $this->entityTypeManager
      ->getStorage('taxonomy_vocabulary')
      ->loadMultiple();

    $vocabularies_options = [];
    foreach ($all_vocabularies as $vocabulary_id => $vocabulary) {
      // Check if the vocabulary has any terms.
      $query = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->getQuery();
      $query->condition('vid', $vocabulary_id);
      $query->accessCheck();
      $terms = $query->execute();
      if (!empty($terms)) {
        $vocabularies_options[$vocabulary_id] = $vocabulary->label();
      }
    }

    return $vocabularies_options;
  }

  /**
   * Get the terms in a JSON format.
   *
   * @param mixed $source_vocabulary
   *   The source vocabulary.
   * @param bool|int $use_source_vocabulary_hierarchy
   *   Whether to use the source vocabulary hierarchy.
   *
   * @return string|false
   *   The JSON representation of the terms.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTermsJson(mixed $source_vocabulary, bool|int $use_source_vocabulary_hierarchy = FALSE): bool|string {

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
    if ($use_source_vocabulary_hierarchy) {
      foreach ($terms_tree as $term) {
        $tid = $term->tid;

        if (!in_array($tid, $accessible_terms)) {
          continue;
        }

        $term_object = [];
        $term_object['name'] = $term->name;

        if (count($term->parents) > 1 || $term->parents[0] != 0) {
          $term_object['parents'] = $term->parents;
        }
        else {
          $term_object['parents'] = [];
        }

        $terms[$tid] = $term_object;
      }
    }
    else {
      foreach ($terms_tree as $term) {
        $tid = $term->tid;
        if (!in_array($tid, $accessible_terms)) {
          continue;
        }
        $terms[] = $term->name;
      }
    }

    return Json::encode($terms);
  }

}
