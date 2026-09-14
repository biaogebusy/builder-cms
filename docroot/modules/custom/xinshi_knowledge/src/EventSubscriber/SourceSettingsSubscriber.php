<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\xinshi_knowledge\Service\DocumentRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** The Harness source allowlist also scopes the owned Search API index. */
final class SourceSettingsSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly DocumentRepository $documents) {}

  public static function getSubscribedEvents(): array {
    return [ConfigEvents::SAVE => 'saved'];
  }

  public function saved(ConfigCrudEvent $event): void {
    if ($event->getConfig()->getName() === 'xinshi_ai.settings') {
      $this->documents->syncContentTypes();
    }
  }

}
