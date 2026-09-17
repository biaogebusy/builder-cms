<?php

namespace Drupal\xinshi_legacy\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Keeps existing user binding records without enabling the old Wechat service.
 *
 * @ContentEntityType(
 *   id = "wechat_user",
 *   label = @Translation("Legacy Wechat user binding"),
 *   handlers = {"storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage"},
 *   admin_permission = "administer users",
 *   base_table = "wechat_user",
 *   entity_keys = {"id" = "id", "label" = "openid"}
 * )
 */
final class WechatUser extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Wechat user ID'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);
    foreach (['openid', 'nickname', 'province', 'city', 'country', 'headimgurl', 'remark', 'groupid', 'language'] as $name) {
      $fields[$name] = BaseFieldDefinition::create('string')
        ->setLabel($name)
        ->setSetting('max_length', 255);
    }
    $fields['subscribe_time'] = BaseFieldDefinition::create('created')->setLabel(t('Subscribe time'));
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Drupal user ID'))
      ->setSetting('target_type', 'user');
    $fields['sex'] = BaseFieldDefinition::create('integer')->setLabel(t('Sex'));
    $fields['subscribe'] = BaseFieldDefinition::create('integer')->setLabel(t('Subscribe status'));
    return $fields;
  }

}
