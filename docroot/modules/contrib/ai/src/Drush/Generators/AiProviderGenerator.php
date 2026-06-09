<?php

declare(strict_types=1);

namespace Drupal\ai\Drush\Generators;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates an AI Provider plugin.
 */
#[Generator(
  name: 'plugin:ai:provider',
  description: 'Generates an AI Provider plugin',
  aliases: ['ai-provider'],
  templatePath: __DIR__ . '/../../../templates/Plugin/_ai-provider',
  type: GeneratorType::MODULE_COMPONENT,
)]
final class AiProviderGenerator extends BaseGenerator implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private readonly AiProviderPluginManager $aiProviderManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('ai.provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $interviewer = $this->createInterviewer($vars);

    $vars['machine_name'] = $interviewer->askMachineName();
    $vars['name'] = $interviewer->askName();

    $vars['plugin_label'] = $interviewer->askPluginLabel('Provider label');
    $vars['plugin_id'] = $interviewer->askPluginId();
    $vars['class'] = $interviewer->askPluginClass(default: '{plugin_label|camelize}Provider');

    // Dynamically discover available operation types.
    $operation_types = $this->discoverOperationTypes();
    $vars['operation_types'] = $interviewer->choice(
      'Operation types to support (comma-separated for multiple)',
      $operation_types,
      'chat',
      TRUE
    );

    // Normalize to array if single value.
    if (!\is_array($vars['operation_types'])) {
      $vars['operation_types'] = [$vars['operation_types']];
    }

    // Determine base class based on provider type.
    $base_class_options = [
      'AiProviderClientBase' => 'AiProviderClientBase (standard provider)',
      'OpenAiBasedProviderClientBase' => 'OpenAiBasedProviderClientBase (OpenAI-compatible API)',
    ];
    $vars['base_class'] = $interviewer->choice('Base class to extend', $base_class_options, 'AiProviderClientBase');

    $vars['has_config_form'] = $interviewer->confirm('Generate configuration form?', TRUE);

    // Generate the provider plugin class.
    $assets->addFile('src/Plugin/AiProvider/{class}.php', 'ai-provider.twig');

    // Generate module files if this is a new module.
    if ($interviewer->confirm('Generate module files (info.yml, routing, etc.)?', TRUE)) {
      $assets->addFile('{machine_name}.info.yml', 'module-info.twig');
      $assets->addFile('{machine_name}.routing.yml', 'module-routing.twig');
      $assets->addFile('{machine_name}.links.menu.yml', 'module-links-menu.twig');
      $assets->addFile('config/schema/{machine_name}.schema.yml', 'config-schema.twig');
      $assets->addFile('definitions/api_defaults.yml', 'api-defaults.twig');

      if ($vars['has_config_form']) {
        $assets->addFile('src/Form/{class}ConfigForm.php', 'config-form.twig');
      }
    }
  }

  /**
   * Discovers available operation types via the AI provider plugin manager.
   *
   * Uses AiProviderPluginManager::getOperationTypes() for dynamic discovery
   * based on #[OperationType] attributes, with a hardcoded fallback.
   *
   * @return array<string, string>
   *   An associative array of operation type id => label.
   */
  private function discoverOperationTypes(): array {
    // Try to get operation types from the plugin manager (dynamic discovery).
    try {
      $discovered = $this->aiProviderManager->getOperationTypes();
      if (!empty($discovered)) {
        $operation_types = [];
        foreach ($discovered as $id => $definition) {
          $label = \is_array($definition) ? ($definition['label'] ?? $id) : $definition;
          $operation_types[$id] = (string) $label;
        }
        \ksort($operation_types);
        return $operation_types;
      }
    }
    catch (\Exception) {
      // Fall through to hardcoded list.
    }

    // Fallback: hardcoded list of common operation types.
    return [
      'audio_to_audio' => 'Audio to Audio',
      'chat' => 'Chat',
      'embeddings' => 'Embeddings',
      'image_and_audio_to_video' => 'Image and Audio to Video',
      'image_classification' => 'Image Classification',
      'image_to_image' => 'Image to Image',
      'image_to_video' => 'Image to Video',
      'moderation' => 'Moderation',
      'object_detection' => 'Object Detection',
      'rerank' => 'Rerank',
      'speech_to_speech' => 'Speech to Speech',
      'speech_to_text' => 'Speech to Text',
      'summarization' => 'Summarization',
      'text_to_image' => 'Text to Image',
      'text_to_speech' => 'Text to Speech',
      'translate_text' => 'Translate Text',
    ];
  }

}
