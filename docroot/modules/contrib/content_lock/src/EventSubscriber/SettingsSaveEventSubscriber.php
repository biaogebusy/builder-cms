<?php

namespace Drupal\content_lock\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\views\Views;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class acts on config save event.
 */
class SettingsSaveEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected RouteBuilderInterface $routeBuilder,
    protected ConfigInstallerInterface $configInstaller,
  ) {
  }

  /**
   * On config save.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The save event.
   */
  public function onSave(ConfigCrudEvent $event): void {

    if ($event->getConfig()->getName() == 'content_lock.settings' && $event->isChanged('types')) {
      $this->routeBuilder->setRebuildNeeded();

      // Maintain actions for entity types unless the config is being synced.
      if (!$this->configInstaller->isSyncing()) {
        $current_types = array_filter($event->getConfig()->get('types'));
        /** @var \Drupal\Core\Entity\EntityStorageInterface $action_storage */
        $action_storage = $this->entityTypeManager->getStorage('action');
        foreach ($current_types as $type => $value) {
          // Skip if the entity type does not exist.
          if (!$this->entityTypeManager->getDefinition($type, FALSE)) {
            unset($current_types[$type]);
            continue;
          }

          // Create an action config for all activated entity types.
          $action = $action_storage->loadByProperties([
            'plugin' => 'entity:break_lock:' . $type,
          ]);
          if (empty($action)) {
            $action = $action_storage->create([
              'id' => $type . '_break_lock_action',
              'label' => 'Break lock ' . $type,
              'plugin' => 'entity:break_lock:' . $type,
              'type' => $type,
            ]);
            $action->save();
          }
        }

        // Remove old actions for entity types that are no longer activated.
        $old_types = array_diff(array_keys($event->getConfig()
          ->getOriginal('types') ?? []), array_keys($current_types));
        if (!empty($old_types)) {
          $plugin_ids = array_map(fn($type) => 'entity:break_lock:' . $type, $old_types);
          $action_ids = $action_storage->getQuery()
            ->accessCheck(FALSE)
            ->condition('plugin', $plugin_ids, 'IN')
            ->execute();
          if (!empty($action_ids)) {
            $action_storage->delete($action_storage->loadMultiple($action_ids));
          }
        }
      }

      if ($this->moduleHandler->moduleExists('views')) {
        Views::viewsData()->clear();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    return $events;
  }

}
