<?php

declare(strict_types=1);

namespace Drupal\private_message\Model;

/**
 * Blocking type.
 */
enum BlockType: string {

  case Passive = 'passive';
  case Active = 'active';

  /**
   * Returns the enum as options.
   *
   * @return array
   *   Options.
   */
  public static function asOptions(): array {
    return [
      self::Passive->value => t('Passive'),
      self::Active->value => t('Active'),
    ];
  }

}
