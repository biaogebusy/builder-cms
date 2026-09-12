<?php

namespace Drupal\views_add_button\Plugin\views_add_button;

use Drupal\Core\Url;

/**
 * Plugin for Views Add Button for entity types created by ECK module.
 *
 * @ViewsAddButton(
 *   id = "views_add_button_eck_entity",
 *   label = @Translation("ViewsAddButtonECKEntity"),
 *   target_entity = ""
 * )
 */
class ViewsAddButtonECKEntity extends ViewsAddButtonDefault {

  /**
   * Plugin description.
   *
   * @return string
   *   A string description.
   */
  public function description() {
    return $this->t('Views Add Button URL Generator for entities created by Entity Construction Kit (ECK) module');
  }

  /**
   * Generate the add button URL.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle ID.
   * @param array $options
   *   Array of options to be passed to the Url object.
   * @param string $context
   *   Module-specific context string.
   *
   * @return \Drupal\Core\Url
   *   Url object which is used to construct the add button link.
   */
  public static function generateUrl($entity_type, $bundle, array $options, $context = '') {
    $path = '/admin/content/' . $entity_type . '/add/' . $bundle;

    // Create the URL from the data above.
    return Url::fromUserInput($path, $options);
  }

}
