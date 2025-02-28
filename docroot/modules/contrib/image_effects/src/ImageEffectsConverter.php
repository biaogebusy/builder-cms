<?php

namespace Drupal\image_effects;

use Drupal\image\Entity\ImageStyle;

/**
 * Converts image effects within image styles.
 */
class ImageEffectsConverter {

  /**
   * Converts a Drupal core's Rotate effect(s) to Image Effects.
   *
   * @param \Drupal\image\Entity\ImageStyle $style
   *   The ImageStyle containing the effect(s) to convert.
   *
   * @return bool
   *   TRUE if the conversion occurred, FALSE if no effect to be converted was
   *   present or the changes could not be saved.
   */
  public function coreRotate2ie(ImageStyle $style): bool {
    $needs_saving = FALSE;
    $effects = $style->getEffects();
    foreach ($effects as $effect) {
      $configuration = $effect->getConfiguration();
      if ($configuration['id'] === 'image_rotate') {
        $data = $configuration['data'];

        // Convert background color.
        if ($data['bgcolor'] !== NULL && $data['bgcolor'] !== '') {
          $data['background_color'] = $data['bgcolor'] . 'FF';
        }
        unset($data['bgcolor']);

        // Convert random flag.
        switch ($data['random']) {
          case TRUE:
            $data['method'] = 'random';
            break;

          case FALSE:
          default:
            $data['method'] = 'exact';
            break;

        }
        unset($data['random']);

        $style->addImageEffect([
          'id' => 'image_effects_rotate',
          'weight' => $configuration['weight'],
          'data' => $data,
        ]);
        $style->deleteImageEffect($effect);
        $needs_saving = TRUE;
      }
    }
    return $needs_saving ? $style->save() : FALSE;
  }

  /**
   * Converts an Image Effects' Rotate effect(s) to Drupal core.
   *
   * @param \Drupal\image\Entity\ImageStyle $style
   *   The ImageStyle containing the effect(s) to convert.
   *
   * @return bool
   *   TRUE if the conversion occurred, FALSE if no effect to be converted was
   *   present or the changes could not be saved.
   */
  public function ieRotate2core(ImageStyle $style): bool {
    $needs_saving = FALSE;
    $effects = $style->getEffects();
    foreach ($effects as $effect) {
      $configuration = $effect->getConfiguration();
      if ($configuration['id'] === 'image_effects_rotate') {
        $data = $configuration['data'];

        // Convert background color.
        if ($data['background_color'] !== NULL && $data['background_color'] !== '') {
          $data['bgcolor'] = substr($data['background_color'], 0, 7);
        }
        unset($data['background_color']);
        unset($data['fallback_transparency_color']);

        // Convert random flag.
        switch ($data['method']) {
          case 'random':
          case 'pseudorandom':
            $data['random'] = TRUE;
            break;

          case 'exact':
          default:
            $data['random'] = FALSE;
            break;

        }
        unset($data['method']);

        $style->addImageEffect([
          'id' => 'image_rotate',
          'weight' => $configuration['weight'],
          'data' => $data,
        ]);
        $style->deleteImageEffect($effect);
        $needs_saving = TRUE;
      }
    }
    return $needs_saving ? $style->save() : FALSE;
  }

}
