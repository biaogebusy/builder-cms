<?php

namespace Drupal\commerce_price\Repository;

use CommerceGuys\Intl\NumberFormat\NumberFormatRepository as ExternalNumberFormatRepository;
use CommerceGuys\Intl\NumberFormat\NumberFormatRepositoryInterface;
use Drupal\commerce_price\Event\NumberFormatDefinitionEvent;
use Drupal\commerce_price\Event\PriceEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the number format repository.
 *
 * Number formats are stored inside the parent class, which is extended here
 * to allow the definitions to be altered via events.
 */
class NumberFormatRepository extends ExternalNumberFormatRepository implements NumberFormatRepositoryInterface {

  /**
   * Creates a NumberFormatRepository instance.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(protected EventDispatcherInterface $eventDispatcher) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function processDefinition(string $locale, array $definition): array {
    $definition = parent::processDefinition($locale, $definition);
    // Let the definition be altered.
    $event = new NumberFormatDefinitionEvent($definition);
    $this->eventDispatcher->dispatch($event, PriceEvents::NUMBER_FORMAT);
    $definition = $event->getDefinition();

    return $definition;
  }

}
