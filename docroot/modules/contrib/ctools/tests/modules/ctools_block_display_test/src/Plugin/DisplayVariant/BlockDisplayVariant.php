<?php

namespace Drupal\ctools_block_display_test\Plugin\DisplayVariant;

use Drupal\ctools\Plugin\DisplayVariant\BlockDisplayVariant as BaseBlockDisplayVariant;

/**
 * Class used for testing.
 */
class BlockDisplayVariant extends BaseBlockDisplayVariant {

  /**
   * {@inheritdoc}
   */
  public function getRegionNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [];
  }

}
