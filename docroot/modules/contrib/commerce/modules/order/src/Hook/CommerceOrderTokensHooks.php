<?php

namespace Drupal\commerce_order\Hook;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Hook implementations for Commerce Order.
 */
class CommerceOrderTokensHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommerceOrderTokensHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $entity_type = $this->entityTypeManager->getDefinition('commerce_order');
    assert($entity_type !== NULL);
    $info = [];

    $info['tokens']['commerce_order']['url'] = [
      'name' => $this->t('URL'),
      'description' => $this->t('The URL of the order.'),
    ];
    $info['tokens']['commerce_order']['admin-url'] = [
      'name' => $this->t('URL'),
      'description' => $this->t('The URL for administrators to view the order.'),
    ];

    return $info;
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];

    if ($type === 'commerce_order' && !empty($data['commerce_order'])) {
      $url_options = ['absolute' => TRUE];
      if (isset($options['langcode'])) {
        $url_options['language'] = $this->languageManager->getLanguage($options['langcode']);
      }

      $order = $data['commerce_order'];
      assert($order instanceof OrderInterface);

      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'url':
            $url = Url::fromRoute('entity.commerce_order.user_view', [
              'commerce_order' => $order->id(),
              'user' => $order->getCustomerId(),
            ], $url_options)->toString(TRUE);
            assert($url instanceof GeneratedUrl);
            $bubbleable_metadata->addCacheableDependency($url);
            $replacements[$original] = $url->getGeneratedUrl();
            break;

          case 'admin-url':
            $url = $order->toUrl('canonical', $url_options)->toString(TRUE);
            assert($url instanceof GeneratedUrl);
            $bubbleable_metadata->addCacheableDependency($url);
            $replacements[$original] = $url->getGeneratedUrl();
            break;
        }
      }
    }

    return $replacements;
  }

}
