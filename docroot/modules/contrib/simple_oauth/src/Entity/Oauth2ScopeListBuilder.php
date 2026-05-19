<?php

namespace Drupal\simple_oauth\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Builds a listing of OAuth2 scope entities.
 *
 * @ingroup simple_oauth
 */
class Oauth2ScopeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['description'] = $this->t('Description');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\simple_oauth\Entity\Oauth2ScopeEntityInterface $entity */
    $row['name'] = $entity->getName();
    $row['description'] = $entity->getDescription();
    return $row + parent::buildRow($entity);
  }

}
