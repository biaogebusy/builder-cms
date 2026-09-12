<?php

namespace Drupal\webform_test\Hook;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_test.
 */
class WebformTestHooks {

  /**
   * Implements hook_webform_load().
   */
  #[Hook('webform_load')]
  public function webformLoad(array $entities) {
    // If ?generate is passed to the current pages URL the test webform's elements
    // will get rebuilt.
    // phpcs:ignore
    if (!isset($_GET['generate'])) {
      return;
    }
    foreach ($entities as $id => $entity) {
      $name = _webform_test_load_include($id);
      if ($name && function_exists('webform_test_' . $name)) {
        $function = 'webform_test_' . $name;
        $elements = $function($entity);
        $entity->setElements($elements);
        // Issue: Unable to execute Webform::save().
        // $entity->save();
        // Workaround: Write the elements directory to webform config.
        \Drupal::configFactory()->getEditable('webform.webform.' . $id)->set('elements', Yaml::encode($elements))->save();
        // Display message.
        \Drupal::messenger()->addStatus(t('Generated elements for %title webform', ['%title' => $entity->label()]));
      }
    }
  }

}
