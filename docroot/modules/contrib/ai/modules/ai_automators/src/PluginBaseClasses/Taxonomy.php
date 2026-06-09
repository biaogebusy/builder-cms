<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Guardrail\AiGuardrailHelper;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a base class that can be used for LLMs taxonomy rules.
 */
class Taxonomy extends RuleBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The prompt json decoder.
   *
   * @var \Drupal\ai\service\PromptJsonDecoder\PromptJsonDecoderInterface
   */
  protected PromptJsonDecoderInterface $promptJsonDecoder;

  /**
   * Constructs a new AiClientBase abstract class.
   *
   * @param \Drupal\ai\AiProviderPluginManager $pluginManager
   *   The plugin manager.
   * @param \Drupal\ai\Service\AiProviderFormHelper $formHelper
   *   The form helper.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $promptJsonDecoder
   *   The prompt json decoder.
   * @param \Drupal\ai\Guardrail\AiGuardrailHelper $aiGuardrailHelper
   *   The AI guardrail helper.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  final public function __construct(
    AiProviderPluginManager $pluginManager,
    AiProviderFormHelper $formHelper,
    PromptJsonDecoderInterface $promptJsonDecoder,
    AiGuardrailHelper $aiGuardrailHelper,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
  ) {
    parent::__construct($pluginManager, $formHelper, $promptJsonDecoder, $aiGuardrailHelper);
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai.provider'),
      $container->get('ai.form_helper'),
      $container->get('ai.prompt_json_decode'),
      $container->get('ai.guardrail_helper'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This helps to choose or create categories.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text choose up to {{ max_amount }} categories from the category context that fits the text.\n\nCategory options:\n{{ value_options_comma }}\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function tokens(ContentEntityInterface $entity) {
    $tokens = parent::tokens($entity);
    $tokens['value_options_comma'] = 'A comma separated list of all value options.';
    $tokens['value_options_nl'] = 'A new line separated list of all value options.';
    $tokens['value_options_nl_description'] = 'A new line separated list of all value options, with term descriptions.';
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta = 0) {
    $tokens = parent::generateTokens($entity, $fieldDefinition, $automatorConfig, $delta);
    $list = $this->getTaxonomyList($entity, $fieldDefinition);
    $values = array_values($list);

    $tokens['value_options_comma'] = implode(', ', $values);
    $tokens['value_options_nl'] = implode("\n", $values);
    $tokens['value_options_nl_description'] = implode("\n", $this->getTaxonomyList($entity, $fieldDefinition, TRUE));
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);
    $settings = $fieldDefinition->getConfig($entity->bundle())->getSettings();

    $form['automator_clean_up'] = [
      '#type' => 'select',
      '#title' => 'Text Manipulation',
      '#description' => $this->t('These are possible text manipulations to run on each created tag.'),
      '#options' => [
        '' => $this->t('None'),
        'lowercase' => $this->t('lowercase'),
        'uppercase' => $this->t('UPPERCASE'),
        'first_char' => $this->t('First character uppercase'),
      ],
      '#default_value' => $defaultValues["automator_clean_up"] ?? '',
      '#weight' => 23,
    ];

    if ($settings['handler_settings']['auto_create']) {
      $form['automator_search_similar_tags'] = [
        '#type' => 'checkbox',
        '#title' => 'Find similar tags',
        '#description' => $this->t('This will use your LLM to find similar tags. Meaning if the tag "Whirlpool" exists and the system wants to store "Whirlpool Bath" it will store it as "Whirlpool". This uses extra calls and is slower and more costly.'),
        '#default_value' => $defaultValues["automator_search_similar_tags"] ?? FALSE,
        '#weight' => 23,
      ];
    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"requested value\"}]";
      $prompts[$key] = $prompt;
    }
    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      $values = $this->runChatMessage($prompt, $automatorConfig, $instance, $entity);

      if (!empty($values)) {
        // Clean value.
        if ($automatorConfig['clean_up']) {
          $values = $this->cleanUpValues($values, $automatorConfig['clean_up']);
        }
        // Check for similar.
        if (!empty($automatorConfig['search_similar_tags'])) {
          $values = $this->searchSimilarTags($values, $entity, $fieldDefinition, $automatorConfig);
        }
        $total = array_merge_recursive($total, $values);
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $settings = $fieldDefinition->getConfig($entity->bundle())->getSettings();

    // If it's auto create and its a text field, create.
    if ($settings['handler_settings']['auto_create'] && is_string($value)) {
      return TRUE;
    }

    $list = $this->getTaxonomyList($entity, $fieldDefinition);
    $values = array_values($list);

    // Has to be in the list.
    if (!in_array($value, $values)) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $settings = $fieldDefinition->getConfig($entity->bundle())->getSettings();

    $list = $this->getTaxonomyList($entity, $fieldDefinition);
    // If it's not in the keys, go through values.
    $newValues = [];
    foreach ($values as $key => $value) {
      foreach ($list as $tid => $name) {
        if ($value == $name) {
          $newValues[$key] = $tid;
        }
      }

      // If auto create, we create new ones.
      if (!isset($newValues[$key]) && $settings['handler_settings']['auto_create']) {
        $term = $this->generateTag($value, $settings);
        if ($term) {
          $newValues[$key] = $term->id();
        }
      }
    }

    // Then set the value.
    $entity->set($fieldDefinition->getName(), $newValues);
    return TRUE;
  }

  /**
   * Looks for similar tags using any model.
   *
   * @param array $values
   *   The values to search for.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $automatorConfig
   *   The configuration.
   */
  public function searchSimilarTags(array $values, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, $automatorConfig) {
    $list = $this->getTaxonomyList($entity, $fieldDefinition);

    $prompt = "Based on the list of available categories and the list of new categories, could you see somewhere where a new category is not the exact same word, but is contextually similar enough to an old one. For instance \"AMG E55\" would connect to \"Mercedes AMG E55\".\n";
    $prompt .= "If they are the same, you do not need to point them out. In those cases point it out. Only find one suggestion per new category. Be very careful and don't make to crude assumptions.\n\n";
    $prompt .= "Do not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"available_category\": \"The available category\", \"new_category\": \"The new category\"}]\n\n";
    $prompt .= "List of available categories:\n" . implode("\n", $list) . "\n\n";
    $prompt .= "List of new categories:\n" . implode("\n", $values) . "\n\n";
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    $data = $this->runChatMessage($prompt, $automatorConfig, $instance, $entity);
    // If there is a response, we use it.
    if (!empty($data)) {
      foreach ($data as $change) {
        // If it's not the same, we change it.
        if (!empty($change['available_category']) && !empty($change['new_category']) && $change['new_category'] != $change['available_category']) {
          foreach ($values as $key => $val) {
            if ($val == $change['new_category']) {
              $values[$key] = $change['available_category'];
            }
          }
        }
      }
      // Do a last sweep so we don't have doublets.
      $values = array_unique($values);
    }

    return $values;
  }

  /**
   * Helper function to clean up values.
   *
   * @param array $values
   *   The values to clean up.
   * @param string $cleanUp
   *   The clean up type.
   *
   * @return array
   *   The cleaned up values.
   */
  public function cleanUpValues(array $values, $cleanUp) {
    $newValues = [];
    foreach ($values as $key => $value) {
      if ($cleanUp == 'lowercase') {
        $newValues[$key] = strtolower($value);
      }
      elseif ($cleanUp == 'uppercase') {
        $newValues[$key] = strtoupper($value);
      }
      elseif ($cleanUp == 'first_char') {
        $newValues[$key] = ucfirst($value);
      }
    }
    return $newValues;
  }

  /**
   * Helper function to get possible values.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param bool $withDescriptions
   *   If we should include descriptions.
   *
   * @return array
   *   Array of tid as key and name as value.
   */
  protected function getTaxonomyList(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, $withDescriptions = FALSE) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    /** @var \Drupal\taxonomy\TermStorage */
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $returnTerms = [];
    // Get vocabularies and get taxonomies from that.
    foreach ($config['handler_settings']['target_bundles'] as $vid) {
      $terms = $storage->loadTree($vid);
      foreach ($terms as $term) {
        $returnTerms[$term->tid] = $withDescriptions ? $term->name . ' - ' . $term->description__value : $term->name;
      }
    }
    return $returnTerms;
  }

  /**
   * Helper function to generate new tags.
   *
   * @param string $name
   *   The name of the taxonomy.
   * @param array $settings
   *   The field config settings.
   *
   * @return \Drupal\taxonomy\Entity\Term|null
   *   A taxonomy term.
   */
  protected function generateTag($name, array $settings) {
    /** @var \Drupal\taxonomy\TermStorage */
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $bundle = !empty($settings['handler_settings']['auto_create_bundle']) ? $settings['handler_settings']['auto_create_bundle'] : key($settings['handler_settings']['target_bundles']);

    if (!$name || !$bundle) {
      return NULL;
    }
    $term = $storage->create([
      'vid' => $bundle,
      'name' => $name,
      'status' => 1,
      'uid' => $this->currentUser->id(),
    ]);
    $term->save();
    return $term;
  }

}
