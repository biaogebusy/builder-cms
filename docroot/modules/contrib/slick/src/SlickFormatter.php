<?php

namespace Drupal\slick;

use Drupal\blazy\BlazyFormatter;
use Drupal\slick\Entity\Slick;

/**
 * Provides Slick field formatters utilities.
 */
class SlickFormatter extends BlazyFormatter implements SlickFormatterInterface {

  /**
   * {@inheritdoc}
   */
  protected static $namespace = 'slick';

  /**
   * {@inheritdoc}
   */
  protected static $itemId = 'slide';

  /**
   * {@inheritdoc}
   */
  protected static $itemPrefix = 'slide';

  /**
   * {@inheritdoc}
   */
  public function preBuildElements(array &$build, $items, array $entities = []) {
    // @todo remove $this->hashtag($build);.
    $settings = &$build['#settings'];

    $this->verifySafely($settings);
    $blazies = $settings['blazies'];
    $config = $settings['slicks'];

    $optionset = Slick::verifyOptionset($build, $settings['optionset']);

    // Prepare integration with Blazy.
    $blazies->set('initial', $optionset->getSetting('initialSlide') ?: 0);

    // Pass initial to parent required by Preload.
    parent::preBuildElements($build, $items, $entities);

    // Slick specific stuffs.
    $settings['_unload'] = FALSE;

    // Only display thumbnail nav if having at least 2 slides. This might be
    // an issue such as for ElevateZoomPlus module, but it should work it out.
    $nav = $blazies->isset('nav') || isset($settings['nav']);
    if (!$nav) {
      $nav = !empty($settings['optionset_thumbnail']) && isset($items[1]);
    }

    // Nothing to work with Vanilla on, disable the asnavfor, else JS error.
    $nav = $nav && empty($settings['vanilla']);

    // Dups to allow one swap to all sliders as seen at ElevateZoomPlus.
    $settings['nav'] = $nav;
    $blazies->set('is.nav', $nav);
    $config->set('is.nav', $nav);

    // Only trim overridables options if disabled.
    if (empty($settings['override']) && isset($settings['overridables'])) {
      $settings['overridables'] = array_filter($settings['overridables']);
    }

    $this->moduleHandler->alter('slick_settings', $build, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function preElements(array &$build, $items, array $entities = []): void {
    parent::preElements($build, $items, $entities);

    $settings = $build['#settings'];

    $build['#asnavfor'] = $settings['blazies']->is('nav');
    $build['#vanilla'] = !empty($settings['vanilla']);
  }

  /**
   * {@inheritdoc}
   */
  public function verifySafely(array &$settings, $key = 'blazies', array $defaults = []) {
    SlickDefault::verify($settings, $this);

    return parent::verifySafely($settings, $key, $defaults);
  }

}
