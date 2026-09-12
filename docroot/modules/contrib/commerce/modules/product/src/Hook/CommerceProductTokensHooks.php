<?php

namespace Drupal\commerce_product\Hook;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;

/**
 * Hook implementations for Commerce Product.
 */
class CommerceProductTokensHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommerceProductTokensHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity type manager.
   * @param \Drupal\token\Token $token
   *   The token.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Token $token,
  ) {
  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $info = [];

    $info['tokens']['commerce_product']['default_variation'] = [
      'name' => $this->t('Default product variation'),
      'description' => $this->t('Returns the default product variation for a product.'),
      'type' => 'commerce_product_variation',
    ];

    $info['tokens']['commerce_product']['current_variation'] = [
      'name' => $this->t('Current product variation'),
      'description' => $this->t('Returns the current product variation for a product which can change based on the query string.'),
      'type' => 'commerce_product_variation',
    ];

    return $info;
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];

    if ($type === 'commerce_product' && !empty($data['commerce_product'])) {
      $commerce_product = $data['commerce_product'];
      assert($commerce_product instanceof ProductInterface);
      if (($default_variation_tokens = $this->token->findWithPrefix($tokens, 'default_variation'))) {
        $default_variation = $commerce_product->getDefaultVariation();
        if ($default_variation) {
          $bubbleable_metadata->addCacheableDependency($default_variation);
        }
        $replacements += $this->token->generate('commerce_product_variation', $default_variation_tokens, ['commerce_product_variation' => $default_variation], $options, $bubbleable_metadata);
      }

      if (($current_variation_tokens = $this->token->findWithPrefix($tokens, 'current_variation'))) {
        /** @var \Drupal\commerce_product\ProductVariationStorageInterface $variation_storage */
        $variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');
        $current_variation = $variation_storage->loadFromContext($commerce_product);
        if ($current_variation) {
          $bubbleable_metadata->addCacheableDependency($current_variation);
        }
        $replacements += $this->token->generate('commerce_product_variation', $current_variation_tokens, ['commerce_product_variation' => $current_variation], $options, $bubbleable_metadata);
      }
    }

    return $replacements;
  }

}
