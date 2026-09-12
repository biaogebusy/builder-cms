<?php

namespace Drupal\blazy_layout\Plugin\Layout;

/**
 * Provides standardized layout-level descriptions for Blazy layouts.
 *
 * Centralizes help text to keep layout plugins concise while maintaining
 * clear guidance for site builders.
 */
trait TraitLayoutDescriptions {

  /**
   * Returns a list of layout configuration descriptions.
   *
   * @param array $data
   *   The data being passed.
   *
   * @return array
   *   The form item descriptions.
   */
  protected function description(array $data = []): array {
    $bl_help = $data['bl_help'] ?? '';
    $blazy_help = $data['blazy_help'] ?? '';
    $blazy_ui = $data['blazy_ui'] ?? '';
    $scope = $data['css_scope'] ?? '';
    $use_custom_css = $data['use_custom_css'] ?? FALSE;

    $css_scope = '';
    $custom_css_desc = '';

    if ($scope = $data['css_scope'] ?? '') {
      $css_scope = ' ' . $this->t(
        'under @scope',
        [
          '@scope' => $scope,
        ]
      );
    }

    if (!$use_custom_css) {
      $custom_css_desc = $this->t(
        'Requires the "Allow custom inline CSS for Blazy" option to be enabled at @url.',
        [
          '@url' => $blazy_ui,
        ]
      ) . ' ';
    }

    return [
      'settings' => $this->t(
        'Use Blazy Image or Media formatters to enable background media and nested grids within blocks. Reload the page if CSS or previews do not update after saving this modal.'
      ),

      'remove_bg' => $this->t(
        'Remove main region background if unused. Useful to reduce markups, or avoid competing Heroes. Leave it unchecked, if using a solid background color with Hero, or <b>Semantic layout</b> is disabled to have regular background.'
      ),

      'semantic_layout' => $this->t(
        'Enable the <b>Semantic Layout</b> when a Hero or any section below it contains related lists. Use it only if appropriate:
        <ul>
          <li>Hero structural info to navigate features or services</li>
          <li>Article summaries or blog post listings</li>
        </ul>
        This option will override <b>Wrapper</b> tags for convenience and consistency, and keeps original, semantic Blazy Grid markups in layouts. Main background is moved before the list. Please read the provided <a href=":url">documentation</a> to avoid incorrect usages.',
        [
          ':url' => $bl_help . '#semantic-layout',
        ]
      ),

      'hero' => $this->t(
        'Select the region delta to mark it as the Hero—typically the largest background media. Leave empty if the layout already appears below a Hero, or if this region is overlaid by another. Hero media should appear only once per page, similar to a Page Title. See <a href=":url">Building heroes</a>.',
        [
          ':url' => '/admin/help/blazy_ui#heroes',
        ]
      ),

      'custom_css' => $this->t(
        "@custom_css_descThis CSS is injected directly into the page <code>&lt;head&gt;</code> and applied at render time.
<ul>
  <li>Use scoped selectors only@css_scope</li>
  <li>Avoid targeting global elements (<code>html</code>, <code>body</code>)</li>
  <li>External imports and remote URLs are ignored</li>
  <li>Direct descendant (<code>&gt;</code>) is escaped.</li>
  <li>Leave empty to avoid unnecessary layout instability</li>
</ul>
Incorrect CSS may break layout rendering or affect unrelated components. This option is intended primarily to mitigate <a href=':cls'>CLS issues</a> when the provided <code>min-height</code> utility classes (<b>xxs xs sm md lg xl xxl x2l x3l x4l x5l</b>) are insufficient.",
        [
          '@css_scope' => $css_scope,
          '@custom_css_desc' => $custom_css_desc,
          ':cls' => $blazy_help . '#cls',
        ]
      ),

      'label' => $this->t(
        'Human-readable region label, primarily used for theming.'
      ),
    ];
  }

}
