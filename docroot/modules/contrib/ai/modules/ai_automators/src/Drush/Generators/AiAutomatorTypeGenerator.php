<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Drush\Generators;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates an AI Automator Type plugin.
 */
#[Generator(
  name: 'plugin:ai:automator-type',
  description: 'Generates an AI Automator Type plugin',
  aliases: ['ai-automator-type'],
  templatePath: __DIR__ . '/../../../templates/Plugin/_ai-automator-type',
  type: GeneratorType::MODULE_COMPONENT,
)]
final class AiAutomatorTypeGenerator extends BaseGenerator implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private readonly FieldTypePluginManagerInterface $fieldTypePluginManager,
    private readonly ModuleExtensionList $moduleExtensionList,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('plugin.manager.field.field_type'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $interviewer = $this->createInterviewer($vars);

    $vars['machine_name'] = $interviewer->askMachineName();
    $vars['name'] = $interviewer->askName();

    $vars['plugin_label'] = $interviewer->askPluginLabel('Plugin label');
    $vars['plugin_id'] = $interviewer->askPluginId();
    $vars['class'] = $interviewer->askPluginClass(default: '{plugin_label|camelize}');

    // Dynamically discover installed field types for the field_rule.
    $field_types = [];
    foreach ($this->fieldTypePluginManager->getDefinitions() as $id => $definition) {
      $label = $definition['label'] ?? $id;
      $field_types[$id] = (string) $label;
    }
    \asort($field_types);
    $vars['field_rule'] = $interviewer->choice('Field rule (target field type)', $field_types);

    $vars['target'] = $interviewer->ask('Target entity type (for entity_reference/file, leave empty otherwise)', '');

    // Dynamically discover available PluginBaseClasses.
    $base_classes = $this->discoverBaseClasses();
    $vars['base_class'] = $interviewer->choice('Base class to extend', $base_classes);

    $vars['needs_prompt'] = $interviewer->confirm('Does this automator need a prompt?', TRUE);

    $assets->addFile('src/Plugin/AiAutomatorType/{class}.php', 'ai-automator-type.twig');
  }

  /**
   * Discovers available PluginBaseClasses from the ai_automators module.
   *
   * Scans the src/PluginBaseClasses/ directory for PHP class files and
   * returns them as options for the generator prompt.
   *
   * @return array<string, string>
   *   An associative array of class name => label.
   */
  private function discoverBaseClasses(): array {
    $base_classes = [];

    try {
      $module_path = $this->moduleExtensionList->getPath('ai_automators');
      $dir = $module_path . '/src/PluginBaseClasses';

      if (\is_dir($dir)) {
        $files = \scandir($dir);
        foreach ($files as $file) {
          if (\str_ends_with($file, '.php')) {
            $class_name = \basename($file, '.php');
            $base_classes[$class_name] = $class_name;
          }
        }
        \ksort($base_classes);
      }
    }
    catch (\Exception) {
      // Fallback if module path cannot be resolved.
    }

    // Ensure RuleBase is always available as a fallback.
    if (empty($base_classes)) {
      $base_classes['RuleBase'] = 'RuleBase';
    }

    return $base_classes;
  }

}
