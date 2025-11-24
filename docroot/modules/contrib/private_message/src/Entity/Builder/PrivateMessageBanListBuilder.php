<?php

declare(strict_types=1);

namespace Drupal\private_message\Entity\Builder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Private Message Ban entities.
 */
class PrivateMessageBanListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    // @todo Make this useful by adding the ban owner and target fields.
    $header['id'] = $this->t('Private Message Ban ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\private_message\Entity\PrivateMessageBanInterface $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.private_message_ban.edit_form',
      ['private_message_ban' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
