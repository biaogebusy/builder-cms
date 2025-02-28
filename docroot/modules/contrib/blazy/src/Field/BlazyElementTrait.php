<?php

namespace Drupal\blazy\Field;

use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Theme\Attributes;
use Drupal\Core\Render\Markup;

/**
 * A Trait for blazy element and its captions.
 *
 * This is a preliminary exercise for 3.x mergers, called by formatters and
 * filters, and likely Views if any similarity found, not yet as per 2.17.
 * We all have similar IMAGE + CAPTION constructs. The only difference is
 * sub-modules separate blazy image from captions while Blazy merges them.
 * Plus thumbnails, already managed by themselves, not blazy's business. Err,
 * it is, since they also have the same IMAGE + CAPTION constructs.
 *
 * Normally required as separate element.caption by sub-modules. This allows
 * improvements at one go, seen like below issues with poorly informed
 * thumbnails, or the new addition of SVG File description. With the integrated
 * captions inside Blazy, this opens up some fun or cool kids like hoverable
 * effects between image and captions, etc. in one place for the entire
 * ecosystem rather than working with each sub-modules.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 */
trait BlazyElementTrait {

  /**
   * The svg manager service.
   *
   * @var \Drupal\blazy\Media\Svg\SvgInterface
   */
  protected $svgManager;

  /**
   * Returns the relevant elements based on the configuration.
   *
   * @todo call self::themeBlazy() directly at 3.x after sub-modules.
   * @todo remove caption for captions at 3.x.
   */
  protected function toElement($blazies, array &$data, array $captions = []): array {
    $delta    = $data['#delta'] ?? 0;
    $captions = $captions ?: ($data['captions'] ?? $data['caption'] ?? []);
    $captions = array_filter($captions);

    // @todo remove caption for captions at 3.x.
    unset($data['captions'], $data['caption']);

    // Call manager not formatter due to sub-module deviations.
    $this->manager->verifyItem($data, $delta);

    // Provides inline SVG if applicable.
    $this->viewSvg($data);

    if ($blazies->use('theme_blazy')) {
      return $this->themeBlazy($data, $captions, $delta);
    }

    // @todo remove at 3.x.
    return $this->themeItem($data, $captions, $delta);
  }

  /**
   * Provides inline SVG if so-configured.
   *
   * @todo move it into ::getBlazy() for more available data, like title, etc.
   */
  protected function viewSvg(array &$element): void {
    $settings = $this->formatter->toHashtag($element);
    $item     = $this->formatter->toHashtag($element, 'item', NULL);
    $blazies  = $settings['blazies'];
    $inline   = $settings['svg_inline'] ?? FALSE;
    $bg       = $settings['background'] ?? FALSE;
    $exist    = Blazy::svgSanitizerExists();
    $valid    = $inline && $exist && !$bg;

    if ($valid && $uri = $blazies->get('image.uri')) {
      $options = BlazyDefault::toSvgOptions($settings);

      // @todo remove fallback after entities updated, except file which has it.
      $title = $blazies->get('image.title')
        ?: Attributes::altTitle($blazies, $item)['title'];

      if ($title) {
        $options['title'] = Attributes::escape($title, TRUE);
      }

      if ($output = $this->svgManager->view($uri, $options)) {
        $blazies->set('is.unlazy', TRUE)
          ->set('lazy.html', FALSE)
          ->set('use.image', FALSE)
          ->set('use.loader', FALSE);
        $element['content'][] = ['#markup' => Markup::create($output)];
      }
    }
  }

  /**
   * Merges source with element array, excluding renderable array.
   *
   * Since 2.17, $source is no longer accessible downtream for just $element.
   */
  protected function withHashtag(array $source, array $element): array {
    $data = $this->formatter->withHashtag($source);
    return array_merge($data, $element);
  }

  /**
   * Builds the item using theme_blazy(), if so-configured.
   *
   * This is the future implementation after mergers at/by 3.x.
   */
  private function themeBlazy(array $data, array $captions, $delta): array {
    $internal = $data;

    // Allows sub-modules to use theme_blazy() as their theme_ITEM() contents.
    if ($texts = $this->toBlazy($internal, $captions, $delta)) {
      $internal['captions'] = $texts;
    }

    $render = $this->formatter->getBlazy($internal);
    $output = $this->withHashtag($data, $render);

    // @todo compare with split below if mergeable even more.
    // Only blazy has content, unset here.
    // unset($data['content']);
    // $element = $data;
    // $element[static::$itemId] = $blazy;
    // Inform thumbnails with the blazy processed settings.
    // $this->formatter->postBlazy($element, $blazy);
    if (static::$namespace == 'blazy') {
      $element = $output;
    }
    else {
      // Only blazy has content, unset here.
      unset($data['content']);

      $element = $data;
      $element[static::$itemId] = $output;

      // Inform thumbnails with the blazy processed settings.
      $this->formatter->postBlazy($element, $output);
    }
    return $element;
  }

  /**
   * This is the current implementation before mergers at 3.x.
   *
   * Looks simpler, yet it has lots of dup efforts downstream.
   *
   * @todo remove this at 3.x.
   */
  private function themeItem(array $data, array $captions, $delta): array {
    $internal = $data;

    // Split for different formatters with very minimal difference.
    if (static::$namespace == 'blazy') {
      $internal[static::$captionId] = $captions;

      $render  = $this->formatter->getBlazy($internal);
      $element = $this->withHashtag($data, $render);
    }
    else {
      $render = $this->formatter->getBlazy($internal);
      $output = $this->withHashtag($data, $render);

      // Only blazy has content, unset here.
      unset($data['content']);

      $element = $data;

      $element[static::$itemId] = $output;
      $element[static::$captionId] = $captions;

      $this->formatter->postBlazy($element, $output);
    }
    return $element;
  }

  /**
   * Provides relevant attributes to feed into theme_blazy().
   */
  private function toBlazy(array &$data, array &$captions, $delta): array {
    // Call manager not formatter due to sub-module deviations.
    $this->manager->toBlazy($data, $captions, $delta);
    return $captions;
  }

}
