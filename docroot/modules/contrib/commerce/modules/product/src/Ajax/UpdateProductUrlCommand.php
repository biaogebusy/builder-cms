<?php

namespace Drupal\commerce_product\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command for updating the product url on variation change.
 *
 * @ingroup ajax
 */
class UpdateProductUrlCommand implements CommandInterface {

  /**
   * The variation ID.
   *
   * @var int
   */
  protected $variationId;

  /**
   * Constructs a new UpdateProductUrlCommand object.
   *
   * @param int $variation_id
   *   The variation ID.
   */
  public function __construct($variation_id) {
    $this->variationId = $variation_id;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'updateProductUrl',
      'variation_id' => $this->variationId,
    ];
  }

}
