<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiFunctionGroup;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionGroup;
use Drupal\ai\Service\FunctionCalling\FunctionGroupInterface;

/**
 * The Modification Tools.
 */
#[FunctionGroup(
  id: 'modification_tools',
  group_name: new TranslatableMarkup('Modification Tools'),
  description: new TranslatableMarkup('These are tools that are meant to provide agents with the possibility to modify content or config on the website.'),
)]
final class ModificationTools implements FunctionGroupInterface {
}
