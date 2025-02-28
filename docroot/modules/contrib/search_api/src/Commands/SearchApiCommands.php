<?php

namespace Drupal\search_api\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\Contrib\RowsOfMultiValueFields;
use Drupal\search_api\Utility\CommandHelper;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines Drush commands for the Search API.
 */
class SearchApiCommands extends DrushCommands {

  /**
   * The command helper.
   *
   * @var \Drupal\search_api\Utility\CommandHelper
   */
  protected $commandHelper;

  /**
   * Constructs a SearchApiCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the "search_api_index" or "search_api_server" entity types'
   *   storage handlers couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the "search_api_index" or "search_api_server" entity types are
   *   unknown.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler, EventDispatcherInterface $eventDispatcher) {
    parent::__construct();

    $this->commandHelper = new CommandHelper($entityTypeManager, $moduleHandler, $eventDispatcher, 'dt');
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(LoggerInterface $logger): void {
    parent::setLogger($logger);
    $this->commandHelper->setLogger($logger);
  }

  /**
   * Lists all search indexes.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The table rows.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an index has a server which couldn't be loaded.
   *
   * @command search-api:list
   *
   * @usage drush search-api:list
   *   List all search indexes.
   *
   * @field-labels
   *   id: ID
   *   name: Name
   *   server: Server ID
   *   serverName: Server name
   *   types: Type IDs
   *   typeNames: Type names
   *   status: Status
   *   limit: Limit
   *
   * @default-fields id,name,serverName,typeNames,status,limit
   *
   * @aliases sapi-l,search-api-list
   */
  public function listCommand() {
    $rows = $this->commandHelper->indexListCommand();

    return new RowsOfMultiValueFields($rows);
  }

  /**
   * Enables one disabled search index.
   *
   * @param string $indexId
   *   A search index ID.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if no indexes could be loaded.
   *
   * @command search-api:enable
   *
   * @usage drush search-api:enable node_index
   *   Enable the search index with the ID node_index.
   *
   * @aliases sapi-en,search-api-enable
   */
  public function enable($indexId) {
    $this->commandHelper->enableIndexCommand([$indexId]);
  }

  /**
   * Enables all disabled search indexes.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if no indexes could be loaded.
   *
   * @command search-api:enable-all
   *
   * @usage drush search-api:enable-all
   *   Enable all disabled indexes.
   * @usage drush sapi-ena
   *   Alias to enable all disabled indexes.
   *
   * @aliases sapi-ena,search-api-enable-all
   */
  public function enableAll() {
    $this->commandHelper->enableIndexCommand();
  }

  /**
   * Disables one or more enabled search indexes.
   *
   * @param string $indexId
   *   A search index ID.
   *
   * @throws \Exception
   *   If no indexes are defined or no index has been passed.
   *
   * @command search-api:disable
   *
   * @usage drush search-api:disable node_index
   *   Disable the search index with the ID node_index.
   * @usage drush sapi-dis node_index
   *   Alias to disable the search index with the ID node_index.
   *
   * @aliases sapi-dis,search-api-disable
   */
  public function disable($indexId) {
    $this->commandHelper->disableIndexCommand([$indexId]);
  }

  /**
   * Disables all enabled search indexes.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if no indexes could be loaded.
   *
   * @command search-api:disable-all
   *
   * @usage drush search-api:disable-all
   *   Disable all enabled indexes.
   * @usage drush sapi-disa
   *   Alias to disable all enabled indexes.
   *
   * @aliases sapi-disa,search-api-disable-all
   */
  public function disableAll() {
    $this->commandHelper->disableIndexCommand();
  }

  /**
   * Shows the status of one or all search indexes.
   *
   * @param string|null $indexId
   *   (optional) A search index ID, or NULL to show the status of all indexes.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The table rows.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if one of the affected indexes had an invalid tracker set.
   *
   * @command search-api:status
   *
   * @usage drush search-api:status
   *   Show the status of all search indexes.
   * @usage drush sapi-s
   *   Alias to show the status of all search indexes.
   * @usage drush sapi-s node_index
   *   Show the status of the search index with the ID node_index.
   *
   * @field-labels
   *   id: ID
   *   name: Name
   *   complete: % Complete
   *   indexed: Indexed
   *   total: Total
   *
   * @aliases sapi-s,search-api-status
   */
  public function status($indexId = NULL) {
    $rows = $this->commandHelper->indexStatusCommand([$indexId]);
    return new RowsOfFields($rows);
  }

  /**
   * Indexes items for one or all enabled search indexes.
   *
   * @param string $indexId
   *   (optional) A search index ID, or NULL to index items for all enabled
   *   indexes.
   * @param array $options
   *   (optional) An array of options.
   *
   * @throws \Exception
   *   If a batch process could not be created.
   *
   * @command search-api:index
   *
   * @option limit
   *   The maximum number of items to index. Set to 0 to index all items.
   *   Defaults to 0 (index all).
   * @option batch-size
   *   The maximum number of items to index per batch run. Defaults to the "Cron
   *   batch size" setting of the index if omitted or explicitly set to 0. Set
   *   to a negative value to index all items in a single batch (not
   *   recommended).
   *
   * @usage drush search-api:index
   *   Index all items for all enabled indexes.
   * @usage drush sapi-i
   *   Alias to index all items for all enabled indexes.
   * @usage drush sapi-i node_index
   *   Index all items for the index with the ID node_index.
   * @usage drush sapi-i --limit=100 node_index
   *   Index a maximum number of 100 items for the index with the ID node_index.
   * @usage drush sapi-i --limit=100 --batch-size=10 node_index
   *   Index a maximum number of 100 items (10 items per batch run) for the
   *   index with the ID node_index.
   *
   * @aliases sapi-i,search-api-index
   */
  public function index($indexId = NULL, array $options = ['limit' => NULL, 'batch-size' => NULL]) {
    $limit = $options['limit'];
    $batch_size = $options['batch-size'];
    $process_batch = $this->commandHelper->indexItemsToIndexCommand([$indexId], $limit, $batch_size);

    if ($process_batch === TRUE) {
      drush_backend_batch_process();
    }
  }

  /**
   * Marks one or all indexes for reindexing without deleting existing data.
   *
   * @param string $indexId
   *   The machine name of an index. Optional. If missed, will schedule all
   *   search indexes for reindexing.
   * @param array $options
   *   An array of options.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if one of the affected indexes had an invalid tracker set, or some
   *   other internal error occurred.
   *
   * @command search-api:reset-tracker
   *
   * @option entity-types List of entity type ids to reset tracker for.
   *
   * @usage drush search-api:reset-tracker
   *   Schedule all search indexes for reindexing.
   * @usage drush sapi-r
   *   Alias to schedule all search indexes for reindexing .
   * @usage drush sapi-r node_index
   *   Schedule the search index with the ID node_index for reindexing.
   *
   * @aliases search-api-mark-all,search-api-reindex,sapi-r,search-api-reset-tracker
   */
  public function resetTracker($indexId = NULL, array $options = ['entity-types' => []]) {
    $this->commandHelper->resetTrackerCommand([$indexId], $options['entity-types']);
  }

  /**
   * Rebuilds the trackers for one or all indexes.
   *
   * @param string $indexId
   *   The machine name of an index. Optional. If missed, will rebuild the
   *   trackers of all indexes.
   *
   * @command search-api:rebuild-tracker
   *
   * @usage drush search-api:rebuild-tracker
   *   Rebuild the trackers of all search indexes.
   *
   * @usage drush sapi-rt
   *   Alias for rebuilding the trackers of all search indexes.
   * @usage drush sapi-rt node_index
   *   Rebuild the tracker of the search index with the ID node_index.
   *
   * @aliases sapi-rt,search-api-rebuild-tracker
   */
  public function rebuildTracker($indexId = NULL) {
    $this->commandHelper->rebuildTrackerCommand([$indexId]);
  }

  /**
   * Clears one or all search indexes and marks them for reindexing.
   *
   * @param string $indexId
   *   The machine name of an index. Optional. If missed all search indexes will
   *   be cleared.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if one of the affected indexes had an invalid tracker set, or some
   *   other internal error occurred.
   *
   * @command search-api:clear
   *
   * @usage drush search-api:clear
   *   Clear all search indexes.
   * @usage drush sapi-c
   *   Alias to clear all search indexes.
   * @usage drush sapi-c node_index
   *   Clear the search index with the ID node_index.
   *
   * @aliases sapi-c,search-api-clear
   */
  public function clear($indexId = NULL) {
    $this->commandHelper->clearIndexCommand([$indexId]);
  }

  /**
   * Searches for a keyword or phrase in a given index.
   *
   * @param string $indexId
   *   The machine name of an index.
   * @param string $keyword
   *   The keyword to look for.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The table rows.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if no search query could be created for the given index, for
   *   example because it is disabled or its server could not be loaded.
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if searching failed for any reason.
   *
   * @command search-api:search
   *
   * @usage drush search-api:search node_index title
   *   Search for "title" inside the "node_index" index.
   * @usage drush sapi-search node_index title
   *   Alias to search for "title" inside the "node_index" index.
   *
   * @field-labels
   *   id: ID
   *   label: Label
   *
   * @aliases sapi-search,search-api-search
   */
  public function search($indexId, $keyword) {
    $rows = $this->commandHelper->searchIndexCommand($indexId, $keyword);

    return new RowsOfFields($rows);
  }

  /**
   * Lists all search servers.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The table rows.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if no servers could be loaded.
   *
   * @command search-api:server-list
   *
   * @usage drush search-api:server-list
   *   List all search servers.
   * @usage drush sapi-sl
   *   Alias to list all search servers.
   *
   * @field-labels
   *   id: ID
   *   name: Name
   *   status: Status
   *
   * @aliases sapi-sl,search-api-server-list
   */
  public function serverList() {
    $rows = $this->commandHelper->serverListCommand();

    return new RowsOfFields($rows);
  }

  /**
   * Enables a search server.
   *
   * @param string $serverId
   *   The machine name of a server.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if the server couldn't be loaded.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if an internal error occurred when saving the server.
   *
   * @command search-api:server-enable
   *
   * @usage drush search-api:server-enable my_solr_server
   *   Enable the my_solr_server search server.
   * @usage drush sapi-se my_solr_server
   *   Alias to enable the my_solr_server search server.
   *
   * @aliases sapi-se,search-api-server-enable
   */
  public function serverEnable($serverId) {
    $this->commandHelper->enableServerCommand($serverId);
  }

  /**
   * Disables a search server.
   *
   * @param string $serverId
   *   The machine name of a server.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if the server couldn't be loaded.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if an internal error occurred when saving the server.
   *
   * @command search-api:server-disable
   *
   * @usage drush search-api:server-disable
   *   Disable the my_solr_server search server.
   * @usage drush sapi-sd
   *   Alias to disable the my_solr_server search server.
   *
   * @aliases sapi-sd,search-api-server-disable
   */
  public function serverDisable($serverId) {
    $this->commandHelper->disableServerCommand($serverId);
  }

  /**
   * Clears all search indexes on the given search server.
   *
   * @param string $serverId
   *   The machine name of a server.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if the server couldn't be loaded.
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if one of the affected indexes had an invalid tracker set, or some
   *   other internal error occurred.
   *
   * @command search-api:server-clear
   *
   * @usage drush search-api:server-clear my_solr_server
   *   Clear all search indexes on the search server my_solr_server.
   * @usage drush sapi-sc my_solr_server
   *   Alias to clear all search indexes on the search server my_solr_server.
   *
   * @aliases sapi-sc,search-api-server-clear
   */
  public function serverClear($serverId) {
    $this->commandHelper->clearServerCommand($serverId);
  }

  /**
   * Sets the search server used by a given index.
   *
   * @param string $indexId
   *   The machine name of an index.
   * @param string $serverId
   *   The machine name of a server.
   *
   * @throws \Exception
   *   If no index or no server were passed or passed values are invalid.
   *
   * @command search-api:set-index-server
   *
   * @usage drush search-api:set-index-server default_node_index my_solr_server
   *   Set the default_node_index index to used the my_solr_server server.
   * @usage drush sapi-sis default_node_index my_solr_server
   *   Alias to set the default_node_index index to used the my_solr_server
   *   server.
   *
   * @aliases sapi-sis,search-api-set-index-server
   */
  public function setIndexServer($indexId, $serverId) {
    $this->commandHelper->setIndexServerCommand($indexId, $serverId);
  }

}
