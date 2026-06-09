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
  id: 'drupal_actions',
  group_name: new TranslatableMarkup('Drupal Core Actions'),
  description: new TranslatableMarkup('These are the Drupal core actions - they are quite experimental still, so use with caution.'),
)]
final class DrupalActions implements FunctionGroupInterface {

}
