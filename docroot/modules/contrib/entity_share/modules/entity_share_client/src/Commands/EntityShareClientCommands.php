<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_share_client\Service\EntityShareClientCliService;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Class EntityShareClientCommands.
 *
 * These are the Drush >= 9 commands.
 *
 * @package Drupal\entity_share_client\Commands
 */
class EntityShareClientCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * The interoperability CLI service.
   *
   * @var \Drupal\entity_share_client\Service\EntityShareClientCliService
   */
  protected $cliService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * EntityShareClientCommands constructor.
   *
   * @param \Drupal\entity_share_client\Service\EntityShareClientCliService $cliService
   *   The CLI service which allows interoperability.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\entity_share_client\Service\RemoteManagerInterface $remoteManager
   *   The remote manager.
   */
  public function __construct(
    EntityShareClientCliService $cliService,
    EntityTypeManagerInterface $entityTypeManager,
    RemoteManagerInterface $remoteManager
  ) {
    parent::__construct();
    $this->cliService = $cliService;
    $this->entityTypeManager = $entityTypeManager;
    $this->remoteManager = $remoteManager;
  }

  /**
   * Pull a channel from a remote website.
   *
   * @param string $remote_id
   *   The remote website ID to import from.
   * @param string $channel_id
   *   The remote channel ID to import.
   * @param string $import_config_id
   *   The import config entity ID.
   *
   * @command entity-share-client:pull
   * @validate-remote-id
   * @validate-import-config-id
   * @usage drush entity-share-client:pull site_1 articles_en default
   *   Pull a channel from a remote website. The "Include count in collection
   *   queries" option should be enabled on the server website. This option is
   *   provided by the JSON:API Extras module.
   */
  public function pullChannel(string $remote_id, string $channel_id, string $import_config_id): void {
    $this->cliService->ioPull($remote_id, $channel_id, $import_config_id, $this->io(), 'dt');
  }

  /**
   * Pull all channels from a remote website.
   *
   * @param string $remote_id
   *   The remote entity ID.
   * @param string $import_config_id
   *   The import config entity ID.
   *
   * @option ignore-channel-ids
   *   Comma separated list of channel ids to be ignored.
   *
   * @command entity-share-client:pull-all
   * @validate-remote-id
   * @validate-import-config-id
   * @usage drush entity-share-client:pull-all site_1 default
   *   Pull all channels from a remote website. The "Include count in collection
   *   queries" option should be enabled on the server website. This option is
   *   provided by the JSON:API Extras module.
   * @usage drush entity-share-client:pull-all site_1 default --ignore-channel-ids=nodes
   *   Same as above, except the channel "nodes".
   * @usage drush entity-share-client:pull-all site_1 default --ignore-channel-ids=nodes,terms
   *   Same as above, except the channels "nodes" and "terms".
   */
  public function pullAllChannels(string $remote_id, string $import_config_id, $options = ['ignore-channel-ids' => '']): void {
    $ignoredChannelIds = explode(',', $options['ignore-channel-ids']);

    /** @var \Drupal\entity_share_client\Entity\RemoteInterface $remote */
    $remote = $this->entityTypeManager->getStorage('remote')->load($remote_id);
    $channels = $this->remoteManager->getChannelsInfos($remote);

    // Check channels.
    if (empty($channels)) {
      $this->io()->warning(dt('Channel list is empty.'));
      return;
    }

    $selfRecord = $this->siteAliasManager()->getSelf();
    foreach (array_keys($channels) as $channel_id) {
      if (in_array($channel_id, $ignoredChannelIds, TRUE)) {
        $this->io()->write(dt('Ignoring @channel_id', [
          '@channel_id' => $channel_id,
        ]), TRUE);
        continue;
      }
      $this->io()->write(dt('Synchronizing @channel_id', [
        '@channel_id' => $channel_id,
      ]), TRUE);
      $args = [
        $remote_id,
        $channel_id,
        $import_config_id,
      ];
      $sub_process = $this->processManager()->drush($selfRecord, 'entity-share-client:pull', $args);
      $sub_process->mustRun();
    }
  }

  /**
   * Validate that a remote ID is valid.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @return \Consolidation\AnnotatedCommand\CommandError|null
   *   NULL if no validation error is found. An error otherwise.
   *
   * @hook validate @validate-remote-id
   */
  public function validateRemoteId(CommandData $commandData) {
    $arg_name = $commandData->annotationData()->get('validate-remote-id', NULL) ?: 'remote_id';
    $remote_id = $commandData->input()->getArgument($arg_name);
    $remote = $this->entityTypeManager->getStorage('remote')->load($remote_id);
    if ($remote === NULL) {
      $message = dt('Impossible to load the remote website with the ID: @remote_id', [
        '@remote_id' => $remote_id,
      ]);
      return new CommandError($message);
    }
    return NULL;
  }

  /**
   * Validate that an import config ID is valid.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @return \Consolidation\AnnotatedCommand\CommandError|null
   *   NULL if no validation error is found. An error otherwise.
   *
   * @hook validate @validate-import-config-id
   */
  public function validateImportConfigId(CommandData $commandData) {
    $arg_name = $commandData->annotationData()->get('validate-import-config-id', NULL) ?: 'import_config_id';
    $import_config_id = $commandData->input()->getArgument($arg_name);
    $import_config = $this->entityTypeManager->getStorage('import_config')->load($import_config_id);
    if ($import_config == NULL) {
      $message = dt('Impossible to load the import config with the ID: @import_config_id', [
        '@import_config_id' => $import_config_id,
      ]);
      return new CommandError($message);
    }
    return NULL;
  }

}
