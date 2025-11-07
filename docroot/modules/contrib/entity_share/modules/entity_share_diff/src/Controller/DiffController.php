<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Controller;

use Drupal\Component\Diff\Diff;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\Entity\RemoteInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Diff support routes.
 */
class DiffController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The remote manager service.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  private $remoteManager;

  /**
   * The diff field builder plugin manager.
   *
   * @var \Drupal\Core\Diff\DiffFormatter
   */
  public $diffFormatter;

  /**
   * The entity parser service.
   *
   * @var \Drupal\entity_share_diff\Service\entityParser
   */
  public $entityParser;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  private $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->remoteManager = $container->get('entity_share_client.remote_manager');
    $instance->resourceTypeRepository = $container->get('jsonapi.resource_type.repository');
    $instance->diffFormatter = $container->get('diff.formatter');
    $instance->entityParser = $container->get('entity_share_diff.entity_parser');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * Returns a table showing the differences between local and remote entities.
   *
   * @param int $left_revision_id
   *   The revision id of the local entity.
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote from which the entity is from.
   * @param string $channel_id
   *   The channel ID from which the entity is from. Used to handle language.
   * @param string $uuid
   *   The UUID of the entity.
   *
   * @return array
   *   Table showing the diff between the local and remote entities.
   */
  public function compareEntities(int $left_revision_id, RemoteInterface $remote, string $channel_id, string $uuid) {
    // Reload the remote to have config overrides applied.
    $remote = $this->entityTypeManager()
      ->getStorage('remote')
      ->load($remote->id());
    $channels_infos = $this->remoteManager->getChannelsInfos($remote);

    // Get the left/local revision.
    $entity_type_id = $channels_infos[$channel_id]['channel_entity_type'];
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    $left_revision = $storage->loadRevision($left_revision_id);

    $this->entityParser->validateNeedToProcess($left_revision->uuid(), FALSE);
    $local_values = $this->entityParser->prepareLocalEntity($left_revision);

    $left_yaml = explode("\n", Yaml::encode($local_values));

    // Get the right/remote revision.
    $url = $channels_infos[$channel_id]['url'];
    $prepared_url = EntityShareUtility::prepareUuidsFilteredUrl($url, [$uuid]);

    $response = $this->remoteManager->jsonApiRequest($remote, 'GET', $prepared_url);
    $json = Json::decode((string) $response->getBody());

    // There will be only one result.
    $entity_data = current(EntityShareUtility::prepareData($json['data']));
    $this->entityParser->validateNeedToProcess($entity_data['id'], TRUE);
    $remote_values = $this->entityParser->prepareRemoteEntity($entity_data, $remote);

    $right_yaml = explode("\n", Yaml::encode($remote_values));

    $header = $this->prepareHeaderlabels($left_revision, $entity_data);

    return $this->diffGenerator($left_yaml, $right_yaml, $header);
  }

  /**
   * Helper: prepare left and right header labels.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $left_entity
   *   The Drupal entity (local).
   * @param array $remote_entity_data
   *   Used for remote entity: entity data coming from JSON:API.
   *
   * @return array
   *   Array with left and right header labels.
   */
  protected function prepareHeaderlabels(ContentEntityInterface $left_entity, array $remote_entity_data) {
    $header = [];
    // Changes diff table header.
    if (method_exists($left_entity, 'getChangedTime')) {
      $left_changed = $this->dateFormatter->format($left_entity->getChangedTime(), 'short');
      $header['left_label'] = $this->t('Local entity: @changed', [
        '@changed' => $left_changed,
      ]);
    }
    else {
      $header['left_label'] = $this->t('Local entity');
    }

    // Changes diff table header.
    $right_changed = $this->entityParser->getRemoteChangedTime($remote_entity_data);
    if ($right_changed) {
      $right_changed = $this->dateFormatter->format($right_changed, 'short');
      $header['right_label'] = $this->t('Remote entity: @changed', [
        '@changed' => $right_changed,
      ]);
    }
    else {
      $header['right_label'] = $this->t('Remote entity');
    }

    return $header;
  }

  /**
   * Helper.
   *
   * @param string[] $left_entity
   *   Array of lines of YAML file representing the local entity.
   * @param string[] $right_entity
   *   Array of lines of YAML file representing the remote entity.
   * @param array $header
   *   Header labels.
   *
   * @return array
   *   A table render array.
   */
  protected function diffGenerator(array $left_entity, array $right_entity, array $header = []) {
    $element = [];
    $diff = new Diff($left_entity, $right_entity);
    $this->diffFormatter->show_header = FALSE;
    $this->diffFormatter->htmlOutput = TRUE;
    $output = $this->diffFormatter->format($diff);
    // Add the CSS for the inline diff.
    $element['#attached']['library'][] = 'system/diff';
    $element['diff'] = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['diff'],
      ],
      '#header' => [
        ['data' => $header['left_label'], 'colspan' => '2'],
        ['data' => $header['right_label'], 'colspan' => '2'],
      ],
      '#rows' => $output,
    ];
    return $element;
  }

}
