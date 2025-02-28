<?php

namespace Drupal\search_api\Utility;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\SearchApiException;

/**
 * Provides a service for indexing items at the end of the page request.
 */
class PostRequestIndexing implements PostRequestIndexingInterface, DestructableInterface {

  use LoggerTrait;

  /**
   * Indexing operations that should be executed at the end of the page request.
   *
   * The array is keyed by index ID and has arrays of item IDs to index for that
   * search index as values.
   *
   * @var string[][]
   */
  protected $operations = [];

  /**
   * Keeps track of how often destruct() was called recursively.
   *
   * This is used to avoid infinite recursions.
   *
   * @var int
   */
  protected $recursion = 0;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function destruct() {
    foreach ($this->operations as $index_id => $item_ids) {
      try {
        $storage = $this->entityTypeManager->getStorage('search_api_index');
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        // It might be possible that the module got uninstalled during the rest
        // of the page request, or something else happened. To be on the safe
        // side, catch the exception in case the entity type isn't found.
        return;
      }

      /** @var \Drupal\search_api\IndexInterface $index */
      $index = $storage->load($index_id);
      // It's possible that the index was deleted in the meantime, so make sure
      // it's actually there.
      if (!$index) {
        continue;
      }

      try {
        // In case there are lots of items to index, take care to not load/index
        // all of them at once, so we don't run out of memory. Using the index's
        // cron batch size should always be safe.
        $batch_size = $index->getOption('cron_limit', 50) ?: 50;
        if ($batch_size > 0) {
          $item_ids_batches = array_chunk($item_ids, $batch_size);
        }
        else {
          $item_ids_batches = [$item_ids];
        }
        foreach ($item_ids_batches as $item_ids_batch) {
          $items = $index->loadItemsMultiple($item_ids_batch);
          if ($items) {
            $index->indexSpecificItems($items);
          }
        }
      }
      catch (SearchApiException $e) {
        $vars['%index'] = $index->label();
        watchdog_exception('search_api', $e, '%type while trying to index items on %index: @message in %function (line %line of %file).', $vars);
      }

      // We usually shouldn't be called twice in a page request, but no harm in
      // being too careful: Remove the operation once it was executed correctly.
      unset($this->operations[$index_id]);
    }

    // Make sure that no new items were added while processing the previous
    // ones. Otherwise, call this method again to index those as well. (But also
    // guard against infinite recursion.)
    if ($this->operations && ++$this->recursion <= 5) {
      $this->destruct();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function registerIndexingOperation($index_id, array $item_ids) {
    foreach ($item_ids as $item_id) {
      $this->operations[$index_id][$item_id] = $item_id;
    }
  }

}
