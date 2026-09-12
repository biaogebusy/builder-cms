<?php

namespace Drupal\pathauto\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\pathauto\AliasStorageHelperInterface;
use Drupal\pathauto\PathautoFieldItemList;
use Drupal\pathauto\PathautoGeneratorInterface;
use Drupal\pathauto\PathautoItem;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\pathauto\PathautoWidget;

/**
 * Entity hook implementations for pathauto.
 */
class PathautoEntityHooks {

  public function __construct(
    protected PathautoGeneratorInterface $pathautoGenerator,
    protected AliasStorageHelperInterface $aliasStorageHelper,
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    $this->pathautoGenerator->updateEntityAlias($entity, 'insert');
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->pathautoGenerator->updateEntityAlias($entity, 'update');
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if ($entity->hasLinkTemplate('canonical') && $entity instanceof ContentEntityInterface && $entity->hasField('path') && $entity->getFieldDefinition('path')->getType() == 'path') {
      $this->aliasStorageHelper->deleteEntityPathAll($entity);
      $entity->get('path')->first()->get('pathauto')->purge();
    }
  }

  /**
   * Implements hook_field_info_alter().
   */
  #[Hook('field_info_alter')]
  public function fieldInfoAlter(&$info): void {
    $info['path']['class'] = PathautoItem::class;
    $info['path']['list_class'] = PathautoFieldItemList::class;
  }

  /**
   * Implements hook_field_widget_info_alter().
   */
  #[Hook('field_widget_info_alter')]
  public function fieldWidgetInfoAlter(&$widgets): void {
    $widgets['path']['class'] = PathautoWidget::class;
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    $config = $this->configFactory->get('pathauto.settings');
    // Verify that the configuration data isn't null (as is the case before the
    // module's initialization, in tests), so that in_array() won't fail.
    if ($enabled_entity_types = $config->get('enabled_entity_types')) {
      if (in_array($entity_type->id(), $enabled_entity_types)) {
        $fields['path'] = BaseFieldDefinition::create('path')->setCustomStorage(TRUE)->setLabel(t('URL alias'))->setTranslatable(TRUE)->setComputed(TRUE)->setDisplayOptions('form', [
          'type' => 'path',
          'weight' => 30,
        ])->setDisplayConfigurable('form', TRUE);
      }
    }
    return $fields;
  }

}
