<?php

namespace Drupal\blazy_layout\Plugin\Layout;

/**
 * Provides a BlazyLayout class for Layout plugins.
 */
class BlazyLayout extends BlazyLayoutForm {

  /**
   * {@inheritdoc}
   */
  public function build(array $regions): array {
    // Clone the base LayoutDefinition to create a runtime variant.
    // This prevents mutation of the discovered plugin definition.
    $this->init();

    $build = parent::build($regions);

    /** @var array $settings */
    $settings = $this->settings();

    $build['#settings'] = $settings;
    $build['#count']    = static::$count;

    // Modifies output.
    /** @var array $output */
    $output = $this->interpolate($settings, $build);

    // Modifies attributes.
    $this->attributes($output, $settings);

    // Modifies regions.
    $this->regions($output, $settings);

    // Provides inline style.
    $this->styles($output, $settings);

    // Modifies attachments.
    $this->attachments($output, $settings);

    // Updates settings and layout.
    $output['#settings'] = $settings;
    $this->setConfiguration($settings);
    $output['#layout'] = $this->pluginDefinition;

    ksort($output);
    return $output;
  }

  /**
   * Interpolate data from Layout Builder to extract grid attributes.
   *
   * This doesn't move around regions. It collects relevant attributes based on
   * user settings and passes them back into the $build['#attributes'].
   *
   * @param array $settings
   *   The settings being modified.
   * @param array $build
   *   The build being passed.
   *
   * @return array
   *   The $build element with modified attributes.
   */
  private function interpolate(array &$settings, array $build): array {
    $sets = $settings;
    $sets['is_form'] = FALSE;
    $data = $this->manager->initGrid($sets);

    $settings = $this->manager->merge($data['settings'], $settings);
    $build['#attributes'] = $this->manager->merge(
      $data['attributes'],
      $build,
      '#attributes'
    );

    return $build;
  }

}
