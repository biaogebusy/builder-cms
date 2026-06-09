<?php

namespace Drupal\ai_search\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\ai\AiVdbProviderPluginManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens for config entity creation events.
 */
class NewServerEventSubscriber implements EventSubscriberInterface {

  /**
   * The constructor.
   *
   * @param \Drupal\ai\AiVdbProviderPluginManager $vdbProvider
   *   The VDB provider.
   */
  public function __construct(
    protected readonly AiVdbProviderPluginManager $vdbProvider,
  ) {
  }

  /**
   * Makes sure that the server is setup on config import/recipe apply.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The config entity save event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    // Only check configs that are search api servers.
    if (str_starts_with($config->getName(), 'search_api.server.')) {
      $data = $config->getRawData();
      // Only VDB providers apply.
      if (
        isset($data['backend']) && $data['backend'] == 'search_api_ai_search' &&
        isset($data['backend_config']['database']) && $this->vdbProvider->hasDefinition($data['backend_config']['database'])
      ) {
        $vdb_provider = $this->vdbProvider->createInstance($data['backend_config']['database']);
        $this->vdbProvider->ensureCollectionExists($vdb_provider, $data['backend_config']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => 'onConfigSave',
    ];
  }

}
