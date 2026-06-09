<?php

namespace Drupal\ai_logging\ViewBuilder;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Prepare json fields for rendering.
 */
class LogViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(array $build) {
    // Call the parent's build method.
    $build = parent::build($build);

    // Get the entity from the build array.
    if (isset($build['#ai_log']) && $build['#ai_log'] instanceof EntityInterface) {

      $entity = $build['#ai_log'];

      foreach (['extra_data', 'configuration'] as $view_key) {
        if ($entity->hasField($view_key)) {
          $json_field = $entity->get($view_key)->value;

          if (!empty($json_field)) {
            // Decode the JSON string.
            $decoded_value = json_decode($json_field, TRUE);

            // Convert it into a formatted key-value string.
            $formatted_string = '';
            if (is_array($decoded_value)) {
              foreach ($decoded_value as $key => $value) {
                $formatted_string .= $key . ":\n" . Json::encode($value) . "\n\n";
              }
            }
            else {
              $formatted_string = 'Invalid JSON';
            }

            // Add the formatted string to the render array.
            if ($formatted_string) {
              $build[$view_key][0]['#context']['value'] = $formatted_string;
            }
          }
        }
      }
    }

    return $build;
  }

}
