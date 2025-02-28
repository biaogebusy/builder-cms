<?php

namespace Drupal\blazy\Plugin\views\field;

use Drupal\file\Entity\File;
use Drupal\views\ResultRow;

/**
 * Defines a custom field that renders a preview of a file.
 *
 * @ViewsField("blazy_file")
 */
class BlazyViewsFieldFile extends BlazyViewsFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\file\Entity\File $entity */
    // @todo recheck relationship and remove this $entity = $values->_entity;
    $entity = $this->getEntity($values);

    if ($entity instanceof File) {
      $settings = $this->mergedViewsSettings();

      $data['#entity']   = $entity;
      $data['#settings'] = $settings;
      $data['#delta']    = $values->index;
      $data['fallback']  = $entity->getFilename();

      // Pass results to \Drupal\blazy\BlazyEntity.
      // @todo phpstan bug only undestands the doc return types, not dynamic.
      /* @phpstan-ignore-next-line */
      return $this->blazyEntity->build($data);
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'multimedia' => TRUE,
      'view_mode' => 'default',
    ] + parent::getPluginScopes();
  }

}
