<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\xinshi_analytics\EvidenceEpochs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Config changes invalidate evidence even if the old configuration is restored later. */
final class EvidenceConfigurationSubscriber implements EventSubscriberInterface {

  /** Constructs the subscriber with durable invalidation storage. */
  public function __construct(private readonly EvidenceEpochs $epochs) {}

  /** Does not try to infer which configuration a project's access hooks consult. */
  public function changed(): void {
    $this->epochs->configurationChanged();
  }

  /** {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [ConfigEvents::SAVE => 'changed', ConfigEvents::DELETE => 'changed',
      ConfigEvents::RENAME => 'changed'];
  }

}
