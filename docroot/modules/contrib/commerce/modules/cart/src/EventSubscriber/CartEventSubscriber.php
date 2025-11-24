<?php

namespace Drupal\commerce_cart\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CartEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new CartEventSubscriber object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected MessengerInterface $messenger,
    TranslationInterface $string_translation,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [
      CartEvents::CART_ENTITY_ADD => 'displayAddToCartMessage',
    ];
    return $events;
  }

  /**
   * Displays an add to cart message.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The add to cart event.
   */
  public function displayAddToCartMessage(CartEntityAddEvent $event) {
    $order = $event->getCart();
    $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $order_type_storage->load($order->bundle());
    if ($order_type->getThirdPartySetting('commerce_cart', 'enable_cart_message', TRUE)) {
      $this->messenger->addMessage($this->t('@entity added to <a href=":url">your cart</a>.', [
        '@entity' => $event->getEntity()->label(),
        ':url' => Url::fromRoute('commerce_cart.page')->toString(),
      ]));
    }
  }

}
