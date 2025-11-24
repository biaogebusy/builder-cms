<?php

declare(strict_types=1);

namespace Drupal\queue_ui_derivatives_fixtures\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class Channel extends DeriverBase.
 *
 * This class provides the definition of a Queue with channel identifiers
 * mapped to their corresponding names.
 */
class Channel extends DeriverBase {

  use StringTranslationTrait;

  /**
   * An array mapping channel identifiers to their corresponding names.
   */
  private const LIST = [
    'channel_1' => 'One',
    'channel_2' => 'Two',
    'channel_3' => 'Three',
  ];

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach (self::LIST as $derivative_id => $derivative) {
      $this->derivatives[$derivative_id] = $base_plugin_definition;
      $this->derivatives[$derivative_id]['admin_label'] = $this->t('Channel @channel', ['@channel' => $derivative]);
    }
    return $this->derivatives;
  }

}
