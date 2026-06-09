<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Guardrail\AiGuardrailHelper;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a base class that can be used for LLMs simple address rules.
 */
class Address extends RuleBase {

  const ADDRESSING_COMPONENT_FIELD_OVERRIDE = '\CommerceGuys\Addressing\AddressFormat\FieldOverride';
  const ADDRESSING_COMPONENT_ADDRESS_FIELD = '\CommerceGuys\Addressing\AddressFormat\AddressField';

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\AiProviderPluginManager $provider
   *   The AI provider plugin manager.
   * @param \Drupal\ai\Service\AiProviderFormHelper $formHelper
   *   The AI provider form helper.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $promptJsonDecoder
   *   The prompt JSON decoder.
   * @param \Drupal\ai\Guardrail\AiGuardrailHelper $aiGuardrailHelper
   *   The AI guardrail helper.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    AiProviderPluginManager $provider,
    AiProviderFormHelper $formHelper,
    PromptJsonDecoderInterface $promptJsonDecoder,
    AiGuardrailHelper $aiGuardrailHelper,
    ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($provider, $formHelper, $promptJsonDecoder, $aiGuardrailHelper);
    $this->moduleHandler = $moduleHandler;
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
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can help find address in text.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text return all addresses listed.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition): bool {
    if ($this->checkAddressClasses() && $this->addressModuleEnabled()) {
      return parent::ruleIsAllowed($entity, $fieldDefinition);
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {

    if (!$this->checkAddressClasses()) {
      return [];
    }
    if (!$this->addressModuleEnabled()) {
      return [];
    }
    $addressFieldOverrideClass = self::ADDRESSING_COMPONENT_FIELD_OVERRIDE;

    $labels = $this->getGenericFieldLabels();
    $fields = $this->getAllAddressFields();

    $promptJsonFields = [
      "country_code" => "The 2 letters based country code in ISO 3166-1 alpha-2 format (required)",
    ];
    $fieldOverrides = $this->getFieldOverrides($fieldDefinition);

    // Prepare a table with the fields and their labels.
    // without hidden fields
    // with required fields marked as such.
    foreach ($fields as $field) {
      if (isset($labels[$field])) {
        $required = FALSE;
        if (isset($fieldOverrides[$field])) {
          if ($fieldOverrides[$field] == $addressFieldOverrideClass::HIDDEN) {
            continue;
          }
          elseif ($fieldOverrides[$field] == $addressFieldOverrideClass::REQUIRED) {
            $required = TRUE;
          }
        }
        $property_name = $this->getPropertyName($field);
        if (!empty($property_name)) {
          $promptJsonFields[$property_name] = $labels[$field] . ($required ? ' (required)' : '');
        }
      }
    }

    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not make up any data, only transfer data that is in the original context. Do not include any explanations, do not provide any markup just pure json, do not format the output with any surroundings, only provide a RFC8259 compliant JSON response following this format without deviation. :\n";
      $prompt .= json_encode([$promptJsonFields], JSON_PRETTY_PRINT);
      $prompts[$key] = $prompt;
    }
    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      // Create new messages.
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
  public function runChatMessage(string $prompt, array $automatorConfig, $instance, ?ContentEntityInterface $entity = NULL) {
    $text = $this->runRawChatMessage($prompt, $automatorConfig, $instance, $entity);
    // Normalize the response.
    $json = $this->promptJsonDecoder->decode($text);
    if (!is_array($json)) {
      throw new AiAutomatorResponseErrorException('The response was not a valid JSON response. The response was: ' . $text->getText());
    }
    return $json;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Should be an array.
    if (!is_array($value)) {
      return FALSE;
    }
    // Check if the address component classes are available.
    if (!$this->checkAddressClasses()) {
      return FALSE;
    }
    // Country code is always mandatory.
    if (!isset($value['country_code'])) {
      return FALSE;
    }

    $addressFieldOverrideClass = self::ADDRESSING_COMPONENT_FIELD_OVERRIDE;

    // Get all possible fields and fields overrides to verify that required
    // fields are present.
    $fields = $this->getAllAddressFields();
    $fieldOverrides = $this->getFieldOverrides($fieldDefinition);
    foreach ($fields as $field) {
      if (isset($fieldOverrides[$field]) &&
        $fieldOverrides[$field] == $addressFieldOverrideClass::REQUIRED) {
        $property_name = $this->getPropertyName($field);
        if (!empty($property_name) && empty($value[$property_name])) {
          return FALSE;
        }
      }
    }

    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * Local implementation of AddressItem::getFieldOverrides().
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The target field definition.
   *
   * @return array|false
   *   Array of field overrides or FALSE if there are missing classes
   */
  protected function getFieldOverrides(FieldDefinitionInterface $fieldDefinition): array|FALSE {

    if (!$this->checkAddressClasses()) {
      return FALSE;
    }
    $addressFieldOverrideClass = self::ADDRESSING_COMPONENT_FIELD_OVERRIDE;
    $addressFormatClass = self::ADDRESSING_COMPONENT_ADDRESS_FIELD;

    $field_overrides = [];

    if ($fields = $fieldDefinition->getSetting('fields')) {
      $unused_fields = array_diff($addressFormatClass::getAll(), $fields);
      foreach ($unused_fields as $field) {
        $field_overrides[$field] = $addressFieldOverrideClass::HIDDEN;
      }
    }
    else {
      if ($overrides = $fieldDefinition->getSetting('field_overrides')) {
        foreach ($overrides as $field => $data) {
          $field_overrides[$field] = $data['override'];
        }
      }
    }
    return $field_overrides;
  }

  /**
   * Get all possible address fields.
   *
   * @return array
   *   Array of all possible address fields
   *
   * @throws \ReflectionException
   */
  protected function getAllAddressFields(): array {
    if (!$this->checkAddressClasses()) {
      return [];
    }
    $addressFieldClass = self::ADDRESSING_COMPONENT_ADDRESS_FIELD;
    return $addressFieldClass::getAll();
  }

  /**
   * Get generic field labels from address module.
   *
   * @return array
   *   Array of generic field labels keyed by field name.
   */
  protected function getGenericFieldLabels(): array {
    if (!$this->addressModuleEnabled()) {
      return [];
    }
    $labelHelperClass = '\Drupal\address\LabelHelper';
    return $labelHelperClass::getGenericFieldLabels();
  }

  /**
   * Get property name for the given field.
   *
   * @param string $field
   *   Address field name.
   *
   * @return string
   *   Property name for the given field. Empty string if FieldHelper class
   *   is not available.
   */
  protected function getPropertyName(string $field): string {
    if (!$this->addressModuleEnabled()) {
      return '';
    }
    $fieldHelperClass = '\Drupal\address\FieldHelper';
    return $fieldHelperClass::getPropertyName($field);
  }

  /**
   * Checks that the address module is enabled.
   *
   * @return bool
   *   True if the address module is enabled false otherwise
   */
  protected function addressModuleEnabled() {
    return $this->moduleHandler->moduleExists('address');
  }

  /**
   * Check if Address component classes are available.
   *
   * Do not "use" directly classes as we are not sure that address field
   * form address module is enabled even if this plugin definition targets
   * only "address" field type.
   *
   * @return bool
   *   True if the required classes are available false otherwise
   */
  protected function checkAddressClasses() {
    return class_exists(self::ADDRESSING_COMPONENT_FIELD_OVERRIDE) &&
      class_exists(self::ADDRESSING_COMPONENT_ADDRESS_FIELD);
  }

}
