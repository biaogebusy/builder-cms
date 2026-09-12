<?php

namespace Drupal\ai\Entity\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * List builder for AI File entities.
 */
class AiFileListBuilder extends EntityListBuilder {

  /**
   * The currently selected purpose filter.
   *
   * @var string|null
   */
  protected $selectedPurpose;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id())
    );
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   Header columns.
   */
  public function buildHeader(): array {
    $header['filename'] = $this->t('Filename');
    $header['remote_id'] = $this->t('Remote ID');
    $header['purpose'] = $this->t('Purpose');
    $header['provider'] = $this->t('Provider');
    $header['size'] = $this->t('Size');
    $header['owner'] = $this->t('Owner');
    $header['changed'] = $this->t('Updated');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   Row columns.
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ai\Entity\AiFileInterface $entity */
    $row['filename'] = $entity->label();
    $row['remote_id'] = $entity->getRemoteId() ?: '-';
    $row['purpose'] = $entity->getPurpose();
    $row['provider'] = $entity->getProvider();
    $size = $entity->getFileSize();
    $row['size'] = $size ? ByteSizeMarkup::create($size) : '-';
    $row['owner']['data'] = [
      '#theme' => 'username',
      '#account' => $entity->getOwner(),
    ];
    $row['changed'] = $this->dateFormatter->format($entity->getChangedTime(), 'short');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int|string>
   *   Entity IDs.
   */
  protected function getEntityIds() {
    $storage = $this->getStorage();
    $query = $storage->getQuery()->accessCheck(TRUE);
    $account = $this->currentUser;
    if (!$account->hasPermission('view any ai file') && !$account->hasPermission('administer ai')) {
      $query->condition('uid', $account->id());
    }
    $query->sort('changed', 'DESC');
    return $query->execute();
  }

}
