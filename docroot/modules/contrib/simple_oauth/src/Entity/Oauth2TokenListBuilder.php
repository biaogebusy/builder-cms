<?php

namespace Drupal\simple_oauth\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Defines a class to build a listing of Access Token entities.
 *
 * @ingroup simple_oauth
 */
class Oauth2TokenListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['type'] = $this->t('Type');
    $header['user'] = $this->t('User');
    $header['name'] = $this->t('Token');
    $header['client'] = $this->t('Client');
    $header['scopes'] = $this->t('Scopes');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\simple_oauth\Entity\Oauth2Token $entity **/
    $row['id'] = $entity->id();
    $row['type'] = $entity->bundle();
    $row['user'] = NULL;
    $row['name'] = $entity->toLink(sprintf('%s…', substr($entity->label(), 0, 10)));
    $row['client'] = NULL;
    $row['scopes'] = NULL;
    if (($user = $entity->get('auth_user_id')) && $user->entity) {
      $row['user'] = $user->entity->toLink($user->entity->label());
    }
    if (($client = $entity->get('client')) && $client->entity) {
      $row['client'] = $client->entity->toLink($client->entity->label(), 'edit-form');
    }
    /** @var \Drupal\simple_oauth\Plugin\Field\FieldType\Oauth2ScopeReferenceItemListInterface $scopes */
    if (!$entity->get('scopes')->isEmpty()) {
      $scope_names = array_map(function (Oauth2ScopeInterface $scope) {
        return $scope->getName();
      }, $entity->get('scopes')->getScopes());
      $row['scopes'] = implode(', ', $scope_names);
    }

    return $row + parent::buildRow($entity);
  }

}
