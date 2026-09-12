<?php

namespace Drupal\blazy\Internals;

/**
 * Provides methods related to field.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please use the public method instead.
 */
final class Field {

  /**
   * Checks for field formatter settings.
   *
   * @param array $settings
   *   The array containing: field-related settings.
   * @param \Drupal\file\Plugin\Field\FieldType\FileFieldItemList|\Drupal\Core\Field\EntityReferenceFieldItemListInterface|\Drupal\Core\Field\FieldItemListInterface $items
   *   The field item list.
   *
   * @todo deprecate and remove fallback settings after migration and sub-modules.
   */
  public static function check(array &$settings, $items): void {
    $entity = $items->getEntity();

    Entity::settings($settings, $entity);

    $blazies = Internals::getBlazies($settings);
    if ($blazies->was('field')) {
      return;
    }

    // @todo deprecate and remove after sub-modules.
    $field = $items->getFieldDefinition();
    if (!$blazies->get('field')) {
      self::settings($settings, $field);
    }

    // @fixme might be 0 even has one if embedded inside LB blocks.
    $total       = $items->count();
    $count       = $blazies->get('count', $total);
    $field_name  = $blazies->get('field.name');
    $field_clean = str_replace('field_', '', $field_name);
    $entity_type = $blazies->get('entity.type_id');
    $entity_id   = $blazies->get('entity.id');
    $bundle      = $blazies->get('entity.bundle');
    $view_mode   = $blazies->get('field.view_mode', 'default');
    $namespace   = $blazies->get('namespace', 'blazy');
    $id          = $blazies->get('css.id', '');
    $gallery_id  = "{$namespace}-{$entity_type}-{$bundle}-{$field_clean}-{$view_mode}";
    $id          = Internals::getHtmlId("{$gallery_id}-{$entity_id}", $id);
    $switch      = $settings['media_switch'] ?? NULL;
    $switch      = $switch ?: $blazies->get('switch');

    // When alignment is mismatched, split them to satisfy linter.
    // Respects linked_field.module expectation.
    $linked    = $blazies->get('field.third_party.linked_field.linked');
    $use_field = !$blazies->is('lightbox') && $linked;
    $use_field = $use_field || !empty($settings['use_theme_field']);

    if (is_string($settings['by_delta'])) {
      $settings['by_delta'] = (int) $settings['by_delta'];
    }

    // @todo deprecate and remove, used by sliders at twigs.
    $settings['count'] = $count;
    $settings['id'] = $id;
    $settings['use_theme_field'] = $use_field;

    if ($switch && $blazies->is('lightbox')) {
      $gallery_id = str_replace('_', '-', $gallery_id . '-' . $switch);
      $blazies->set('lightbox.gallery_id', $gallery_id);
    }

    // The total is the original unmodified count, tricked at slider grids.
    $blazies->set('cache.metadata.keys', [$id, $count], TRUE)
      ->set('cache.metadata.tags', [$entity_type . ':' . $entity_id], TRUE)
      ->set('count', $count)
      ->set('total', $count)
      ->set('css.id', $id)
      ->set('use.theme_field', $use_field)
      ->set('was.field', TRUE);
  }

  /**
   * Returns available bundles.
   */
  public static function getAvailableBundles($field): array {
    $type     = $field->getSetting('target_type');
    $views_ui = $field->getSetting('handler') == 'default';
    $handlers = $field->getSetting('handler_settings');
    $targets  = $handlers ? ($handlers['target_bundles'] ?? []) : [];
    $bundles  = $views_ui ? [] : $targets;

    // Fix for Views UI not recognizing Media bundles, unlike Formatters.
    // @todo convert it to DI $this->manager->service() at D11.
    if (empty($bundles)
      && $type
      && $service = Internals::service('entity_type.bundle.info')) {
      $bundles = $service->getBundleInfo($type);
    }

    return $bundles;
  }

  /**
   * Provides field-related settings, called by back-end and front-end.
   */
  public static function settings(array &$settings, $field, array $data = []): array {
    $settings['blazies'] = Internals::getBlazies($settings);
    $blazies = $settings['blazies'];
    $bundles = self::getAvailableBundles($field);

    $submodules = [
      'cardinality'    => $field->getFieldStorageDefinition()->getCardinality(),
      'field_type'     => $field->getType(),
      'target_bundles' => $bundles,
      'target_type'    => $field->getSetting('target_type'),
    ];

    $info = [
      'field_label'   => $field->getLabel(),
      'field_name'    => $field->getName(),
      'entity_type'   => $field->getTargetEntityTypeId(),
      'target_bundle' => $field->getTargetBundle(),
    ] + $submodules;

    if ($data) {
      $blazies->set('field', $data, TRUE);
    }

    $blazies->set('field.settings', $field->getSettings());
    if (!$blazies->get('namespace')
      && $namespace = $settings['namespace'] ?? NULL) {
      $blazies->set('namespace', $namespace);
    }

    foreach ($info as $key => $value) {
      $k = str_replace('field_', '', $key);
      $blazies->set('field.' . $k, $value);
    }

    // Cannot use blazies.field.settings.handler_settings.target_bundles, since
    // they are always empty at View UI.
    if ($bundles) {
      $blazies->set('field.target_bundles', $bundles);
    }

    // @todo deprecate and remove at/ by 3.x after migration and sub-modules: EZ, Splidebox.
    foreach ($submodules as $key => $value) {
      $settings[$key] = $value;
    }

    return $settings;
  }

}
