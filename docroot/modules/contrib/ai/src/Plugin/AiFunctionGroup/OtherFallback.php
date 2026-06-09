<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiFunctionGroup;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionGroup;
use Drupal\ai\Service\FunctionCalling\FunctionGroupInterface;

/**
 * The Drupal actions.
 */
#[FunctionGroup(
  id: 'other_fallback',
  group_name: new TranslatableMarkup('Other'),
  description: new TranslatableMarkup('These are the tools without any group assigned.'),
  weight: 1000,
)]
final class OtherFallback implements FunctionGroupInterface {

}
