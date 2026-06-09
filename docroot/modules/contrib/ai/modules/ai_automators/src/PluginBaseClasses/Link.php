<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Guardrail\AiGuardrailHelper;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\HostnameFilter;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a base class that can be used for LLMs simple link rules.
 */
class Link extends RuleBase {

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
   * @param \Drupal\ai\Service\HostnameFilter $hostnameFilter
   *   The hostname filter.
   */
  final public function __construct(
    AiProviderPluginManager $pluginManager,
    AiProviderFormHelper $formHelper,
    PromptJsonDecoderInterface $promptJsonDecoder,
    AiGuardrailHelper $aiGuardrailHelper,
    protected HostnameFilter $hostnameFilter,
  ) {
    parent::__construct($pluginManager, $formHelper, $promptJsonDecoder, $aiGuardrailHelper);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai.provider'),
      $container->get('ai.form_helper'),
      $container->get('ai.prompt_json_decode'),
      $container->get('ai.guardrail_helper'),
      $container->get('ai.hostname_filter_service'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can help find link in text.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text return all links listed.\n\nContext:\n{{ raw_context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      if (!$this->getJsonSchema()) {
        $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": {\"uri\": \"The raw url\", \"title\": \"The link text if available\"}}]";
      }
      $prompts[$key] = $prompt;
    }
    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      $values = $this->runChatMessage($prompt, $automatorConfig, $instance, $entity);
      if (!empty($values)) {
        $total = array_merge_recursive($total, $values);
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, $automatorConfig) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    // Has to have a link an be valid.
    if (empty($value['uri']) || !filter_var($value['uri'], FILTER_VALIDATE_URL)) {
      return FALSE;
    }
    // If link text is required it has to be set.
    if (empty($value['title']) && $config['title'] == 2) {
      return FALSE;
    }

    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    // Set plain text mode, so it catches none html/markdown links.
    $this->hostnameFilter->setPlainTextMode(TRUE);
    foreach ($values as $key => $value) {
      if ($config['title'] == 0) {
        $value['title'] = '';
      }
      // If title is not empty, we need to run the hostname filter.
      if (!empty($value['title'])) {
        $value['uri'] = $this->hostnameFilter->filterText($value['uri']);
      }
      $values[$key] = $value;
      // Remove the link if no uri exists after filter.
      if (empty($value['uri'])) {
        unset($values[$key]);
      }
    }
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

}
