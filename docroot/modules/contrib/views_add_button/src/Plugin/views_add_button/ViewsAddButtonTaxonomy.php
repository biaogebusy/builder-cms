<?php

namespace Drupal\views_add_button\Plugin\views_add_button;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Url;
use Drupal\views_add_button\ViewsAddButtonInterface;

/**
 * Taxonomy Term plugin for Views Add Button.
 *
 * @ViewsAddButton(
 *   id = "views_add_button_taxonomy",
 *   label = @Translation("ViewsAddButtonTaxonomy"),
 *   target_entity = "taxonomy_term"
 * )
 */
class ViewsAddButtonTaxonomy extends PluginBase implements ViewsAddButtonInterface {

  /**
   * Plugin description.
   *
   * @return string
   *   A string description.
   */
  public function description() {
    return $this->t('Views Add Button URL Generator for Taxonomy Term entities');
  }

  /**
   * Check for access to the appropriate "add" route.
   *
   *   Entity id as a machine name.
   *
   * @param string $bundle
   *   The bundle string.
   *   Entity context string.
   *
   * @return bool
   *   Whether we have access.
   */
  public static function checkAccess(string $entity_type, string $bundle) {
    if ($bundle) {
      $accessManager = \Drupal::service('access_manager');
      return $accessManager->checkNamedRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $bundle], \Drupal::currentUser());
    }
  }

  /**
   * Generate the add button URL.
   *
   *   Entity type ID.
   *
   * @param string $bundle
   *   Bundle ID.
   * @param array $options
   *   Array of options to be passed to the Url object.
   *   Module-specific context string.
   *
   * @return \Drupal\Core\Url
   *   Url object which is used to construct the add button link
   */
  public static function generateUrl(string $entity_type, $bundle, array $options) {

    // Create URL from the data above.
    return Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $bundle], $options);
  }

}
