<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiFunctionGroup;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionGroup;
use Drupal\ai\Service\FunctionCalling\FunctionGroupInterface;

/**
 * The Information Tools.
 */
#[FunctionGroup(
  id: 'information_tools',
  group_name: new TranslatableMarkup('Information Tools'),
  description: new TranslatableMarkup('These are information tools that are meant to provide agents with more information to take decisions or answer questions.'),
)]
final class InformationTools implements FunctionGroupInterface {
}
