<?php

declare(strict_types=1);

namespace Drupal\ai\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;

/**
 * Code generator for AI Guardrail plugins.
 */
#[Generator(
  name: 'plugin:ai:guardrail',
  description: 'Generates an AI Guardrail plugin.',
  aliases: ['ai-guardrail'],
  templatePath: __DIR__ . '/../../../templates/Plugin/_ai-guardrail',
  type: GeneratorType::MODULE_COMPONENT,
)]
class GuardrailGenerator extends BaseGenerator {

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $ir = $this->createInterviewer($vars);
    $vars['machine_name'] = $ir->askMachineName();
    $vars['php_prefix'] = '<?php';

    $vars['label'] = $ir->ask('Guardrail label (human-readable)', 'My Guardrail');
    $vars['plugin_id'] = $ir->ask(
      'Plugin ID (snake_case)',
      mb_strtolower(str_replace([' ', '-', '.'], '_', $vars['label'])),
    );
    $vars['description'] = $ir->ask('Description', 'A custom guardrail plugin.');

    $vars['non_deterministic'] = $ir->confirm(
      "Is this a non-deterministic guardrail?\n"
      . "  Deterministic guardrails use fixed rules (regex, length, blocklist) - same input always gives same result.\n"
      . "  Non-deterministic guardrails call AI internally (topic classification, toxicity detection) - results may vary.\n"
      . 'Non-deterministic?',
      FALSE,
    );

    // Derive class name from label.
    $vars['class'] = str_replace(' ', '', ucwords(str_replace('_', ' ', $vars['plugin_id'])));

    if ($vars['non_deterministic']) {
      $assets->addFile('src/Plugin/AiGuardrail/{class}.php', 'non_deterministic.twig');
    }
    else {
      $assets->addFile('src/Plugin/AiGuardrail/{class}.php', 'deterministic.twig');
    }
  }

}
