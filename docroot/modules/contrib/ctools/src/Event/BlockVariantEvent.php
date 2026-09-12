<?php

namespace Drupal\ctools\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\ctools\Plugin\BlockVariantInterface;

/**
 * An event for interacting with block variants.
 */
class BlockVariantEvent extends Event {

  /**
   * The block being acted upon.
   *
   * @var \Drupal\Core\Block\BlockPluginInterface
   */
  protected $block;

  /**
   * The variant acting on the block.
   *
   * @var \Drupal\ctools\Plugin\BlockVariantInterface
   */
  protected $variant;

  /**
   * BlockVariantEvent constructor.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block
   *   The block plugin.
   * @param \Drupal\ctools\Plugin\BlockVariantInterface $variant
   *   The variant plugin.
   */
  public function __construct(BlockPluginInterface $block, BlockVariantInterface $variant) {
    $this->block = $block;
    $this->variant = $variant;
  }

  /**
   * Gets the block plugin.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface
   *   The block plugin.
   */
  public function getBlock() {
    return $this->block;
  }

  /**
   * Gets the variant plugin.
   *
   * @return \Drupal\ctools\Plugin\BlockVariantInterface
   *   The variant plugin.
   */
  public function getVariant() {
    return $this->variant;
  }

}
