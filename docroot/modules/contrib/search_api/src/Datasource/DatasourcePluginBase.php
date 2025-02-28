<?php

namespace Drupal\search_api\Datasource;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\search_api\Plugin\IndexPluginBase;

/**
 * Defines a base class from which other datasources may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_datasource_info_alter(). The definition includes the
 * following keys:
 * - id: The unique, system-wide identifier of the datasource.
 * - label: The human-readable name of the datasource, translated.
 * - description: A human-readable description for the datasource, translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SearchApiDatasource(
 *   id = "my_datasource",
 *   label = @Translation("My datasource"),
 *   description = @Translation("Exposes my custom items as a datasource."),
 * )
 * @endcode
 *
 * @see \Drupal\search_api\Annotation\SearchApiDatasource
 * @see \Drupal\search_api\Datasource\DatasourcePluginManager
 * @see \Drupal\search_api\Datasource\DatasourceInterface
 * @see plugin_api
 */
abstract class DatasourcePluginBase extends IndexPluginBase implements DatasourceInterface {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    $items = $this->loadMultiple([$id]);
    return $items ? reset($items) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getItemLabel(ComplexDataInterface $item) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemBundle(ComplexDataInterface $item) {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemLanguage(ComplexDataInterface $item) {
    if ($item instanceof TranslatableInterface) {
      return $item->language()->getId();
    }
    $item = $item->getValue();
    if ($item instanceof TranslatableInterface) {
      return $item->language()->getId();
    }
    return Language::LANGCODE_NOT_SPECIFIED;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemUrl(ComplexDataInterface $item) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function checkItemAccess(ComplexDataInterface $item, AccountInterface $account = NULL) {
    @trigger_error('\Drupal\search_api\Datasource\DatasourceInterface::checkItemAccess() is deprecated in search_api:8.x-1.14 and is removed from search_api:2.0.0. Use getItemAccessResult() instead. See https://www.drupal.org/node/3051902', E_USER_DEPRECATED);
    return $this->getItemAccessResult($item, $account)->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemAccessResult(ComplexDataInterface $item, AccountInterface $account = NULL) {
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getViewModes($bundle = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getBundles() {
    return [
      $this->getPluginId() => $this->label(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewItem(ComplexDataInterface $item, $view_mode, $langcode = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function viewMultipleItems(array $items, $view_mode, $langcode = NULL) {
    $build = [];
    foreach ($items as $key => $item) {
      $build[$key] = $this->viewItem($item, $view_mode, $langcode);
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemIds($page = NULL) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function canContainEntityReferences(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAffectedItemsForEntityChange(EntityInterface $entity, array $foreign_entity_relationship_map, EntityInterface $original_entity = NULL): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDependencies(array $fields) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getListCacheContexts() {
    return [];
  }

}
