<?php

namespace Drupal\blazy_layout\Form;

/**
 * Provides standardized administrative descriptions for Blazy Layout forms.
 *
 * Centralizes long-form help text to keep form definitions readable while
 * retaining detailed usage guidance.
 */
trait TraitAdminDescriptions {

  /**
   * Returns a list of administrative configuration descriptions.
   *
   * @param array $data
   *   The data being passed.
   *
   * @return array
   *   The form item descriptions.
   */
  protected function description(array $data = []): array {
    $max = $data['max'] ?? 0;
    $url = $data['url'] ?? '';
    $help = $data['help'] ?? '';

    return [
      'count' => $this->t(
        'Number of regions (maximum @max, excluding Background). Typically matches the number of grids used by Native Grid or Flexbox. Adjust this limit at <a href=":url">Blazy UI → Max region count</a>. Regions exceeding the limit are hidden.',
        [
          ':url' => $url,
          '@max' => $max,
        ]
      ),

      'colors' => $this->t(
        'Custom text and overlay colors. If CSS framework color utility classes (e.g. Bootstrap) are applied via <b>Classes</b>, they may override these values—use one approach or the other. Leave defaults (<b>color black/#000000, opacity 1</b>) to defer styling to the framework. Overlay options require <b>Use CSS background</b> or <b>Styles → Media</b> to be enabled. Applies to text elements such as <code>p</code>.'
      ),

      'ete' => $this->t(
        'Expands the background edge-to-edge. Works best with a defined <b>Max width</b> and wide themes without sidebars. Parent containers must not use <code>overflow: hidden</code>, otherwise content may be cropped. Test with Bartik to help isolate theme-related issues.'
      ),

      'padding' => $this->t(
        'CSS padding value, e.g. <code>3rem</code> or <code>15px 30px</code>. Leave empty when using a CSS framework and apply spacing via <b>Classes</b> instead.'
      ),

      'max_width' => $this->t(
        'Maximum width of the <b>b-layout</b> container. Useful for exposing background media. Accepts standard CSS values such as <code>82%</code> or <code>1270px</code>. For responsive values, use space-separated breakpoint pairs:<br><code>0px:98% 768px:90% 1270px:82%</code><br>Final width is constrained by parent containers. Test with Bartik to validate theme interactions.'
      ),

      'gapless' => $this->t(
        'Applies to Flexbox and Native Grid only. Removes gaps or margins between items.'
      ),

      'media' => $this->t(
        'Learn how to use media as a background <a href=":help">here</a>.',
        [
          ':help' => $help,
        ]
      ),

      'mlfe' => $this->t(
        'Requires the <a href=":url2">Media Library Form Element</a> module.',
        [
          ':url2' => 'https://www.drupal.org/project/media_library_form_element',
        ]
      ),

      'link' => $this->t(
        '<b>Supported types</b>: Link field or plain-text URL. Used by <b>Media switcher → Image linked by Link field</b> to wrap rendered images. Formatter output must resolve to a plain URL. For per-region linking, the field must exist on the relevant media bundles (image, video, remote video).'
      ),

      'classes' => $this->t(
        'Space-separated CSS classes, e.g. <code>bg-dark text-white</code>. CSS framework utilities are supported (e.g. <code>p-sm-2 p-md-5</code>).'
      ),

      'align_items' => $this->t(
        'Flexbox and Native Grid only. Controls item alignment along the cross (block) axis. Using <code>start</code> may affect CSS background rendering. <a href=":url">Learn more</a>.',
        [
          ':url' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/align-items',
        ]
      ),

      'grid_auto_rows' => $this->t(
        'Native Grid only. Accepted values include <code>auto</code>, <code>min-content</code>, <code>max-content</code>, and <code>minmax()</code>. Example: <code>minmax(80px, auto)</code>. Defaults to <code>var(--bn-row-height-native)</code> or <code>80px</code>. <a href=":url">Learn more</a>.',
        [
          ':url' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/grid-auto-rows',
        ]
      ),

      'attributes' => $this->t(
        'Comma-separated HTML attributes using the format <code>key|value</code>, e.g. <code>role|main,data-key|value</code>.'
      ),

      'subclasses' => $this->t(
        'Space-separated CSS classes applied to child elements, e.g. <code>bg-dark text-white</code>.'
      ),

      'row_classes' => $this->t(
        'Space-separated row-level classes, e.g. <code>align-items-stretch no-gutters</code>.'
      ),
    ];
  }

}
