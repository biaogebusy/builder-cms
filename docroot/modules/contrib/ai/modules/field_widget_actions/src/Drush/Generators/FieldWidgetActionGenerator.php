<?php

declare(strict_types=1);

namespace Drupal\field_widget_actions\Drush\Generators;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\WidgetPluginManager;
use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates a Field Widget Action plugin.
 */
#[Generator(
  name: 'plugin:ai:field-widget-action',
  description: 'Generates a Field Widget Action plugin',
  aliases: ['ai-field-widget-action'],
  templatePath: __DIR__ . '/../../../templates/Plugin/_field-widget-action',
  type: GeneratorType::MODULE_COMPONENT,
)]
final class FieldWidgetActionGenerator extends BaseGenerator implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private readonly WidgetPluginManager $widgetPluginManager,
    private readonly FieldTypePluginManagerInterface $fieldTypePluginManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('plugin.manager.field.widget'),
      $container->get('plugin.manager.field.field_type'),
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
    $vars['class'] = $interviewer->askPluginClass(suffix: 'Action');

    $vars['category'] = $interviewer->ask('Plugin category', '{name}');
    $vars['description'] = $interviewer->ask('Plugin description', 'Provides a field widget action.');

    // Dynamically discover installed widget types.
    $widget_types = [];
    foreach ($this->widgetPluginManager->getDefinitions() as $id => $definition) {
      $label = $definition['label'] ?? $id;
      $widget_types[$id] = (string) $label;
    }
    \asort($widget_types);
    $vars['widget_type_keys'] = $interviewer->choice('Widget types (comma-separated numbers for multiple)', $widget_types, multiselect: TRUE);

    // Dynamically discover installed field types.
    $field_types = [];
    foreach ($this->fieldTypePluginManager->getDefinitions() as $id => $definition) {
      $label = $definition['label'] ?? $id;
      $field_types[$id] = (string) $label;
    }
    \asort($field_types);
    $vars['field_type_keys'] = $interviewer->choice('Field types (comma-separated numbers for multiple)', $field_types, multiselect: TRUE);

    $vars['multiple'] = $interviewer->confirm('Show button per-delta (multiple)?', TRUE);
    $vars['use_ajax'] = $interviewer->confirm('Use AJAX callback?', TRUE);
    $vars['configurable'] = $interviewer->confirm('Add a configuration form?', FALSE);

    $assets->addFile('src/Plugin/FieldWidgetAction/{class}.php', 'field-widget-action.twig');
  }

}
