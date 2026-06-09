<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Plugin\AiFunctionGroup;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionGroup;
use Drupal\ai\Service\FunctionCalling\FunctionGroupInterface;

/**
 * The Drupal automator tools.
 */
#[FunctionGroup(
  id: 'automators_tools',
  group_name: new TranslatableMarkup('Automator tools'),
  description: new TranslatableMarkup('These are tools created using the AI Automators module.'),
)]
final class AutomatorTools implements FunctionGroupInterface {
}
