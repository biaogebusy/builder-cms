<?php

namespace Drupal\views_add_button\Plugin\views_add_button;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\views_add_button\ViewsAddButtonInterface;

/**
 * Default plugin for Views Add Button.
 *
 * @ViewsAddButton(
 *   id = "views_add_button_default",
 *   label = @Translation("ViewsAddButtonDefault"),
 *   target_entity = ""
 * )
 */
class ViewsAddButtonDefault extends PluginBase implements ViewsAddButtonInterface {

  /**
   * Plugin description.
   *
   * @return string
   *   A string description.
   */
  public function description() {
    return $this->t('Default Views Add Button URL Generator for entitites which do not have a dedicated ViewsAddButton plugin');
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
    /*
     * Since the create route is difficult to determine
     * from entity annotations (there is not a standard
     * name for a create/register form), We will make
     * an assumption that a no-bundle entity has the format
     * {entity_type}/add , and bundled entities are of the
     * type {entity_type}/add/{bundle} . Differences are
     * handled in other ViewsAddButton plugins.
     */
    $u = $entity_type === $bundle ? '/' . $entity_type . '/add' : '/' . $entity_type . '/add/' . $bundle;

    // Create URL from the data above.
    $url = Url::fromUserInput($u, $options);

    return $url;
  }

  /**
   * Generate the add button link.
   *
   * @param $text
   *   The link text.
   * @param Url $url
   *   The Url for constructing the link.
   * @param array $options
   *   Array of options from the VAB settings.
   *
   * @return \Drupal\Core\Link
   *   Link object which is used to construct the add button.
   */
  public static function generateLink($text, Url $url, array $options = []) {
    return Link::fromTextAndUrl($text, $url);
  }

}
