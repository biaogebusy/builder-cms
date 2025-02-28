<?php

namespace Drupal\user_default_page;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\Entity\User;

/**
 * Provides a listing of User default page entities.
 */
class UserDefaultPageConfigEntityListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['roles'] = $this->t('Roles');
    $header['weight'] = $this->t('Weight');
    $header['users'] = $this->t('User Id(s)');
    $header['login_path'] = $this->t('Login Path');
    $header['logout_path'] = $this->t('Logout Path');
    $header['login_message'] = $this->t('Login Message');
    $header['logout_message'] = $this->t('Logout Message');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $user_values = $entity->getUsers();
    $uids = explode(',', $user_values);
    $default_users = User::loadMultiple($uids);
    $default_value = EntityAutocomplete::getEntityLabels($default_users);
    $row['label'] = $entity->label();
    $row['roles'] = implode(',', $entity->getUserRoles());
    $row['weight'] = $entity->getWeight();
    $row['users'] = $default_value;
    $row['login_path'] = $entity->getLoginRedirect();
    $row['logout_path'] = $entity->getLogoutRedirect();
    $row['login_message'] = $entity->getLoginRedirectMessage();
    $row['logout_message'] = $entity->getLogoutRedirectMessage();
    return $row + parent::buildRow($entity);
  }

}
