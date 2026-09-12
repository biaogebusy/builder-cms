<?php

namespace Drupal\blazy\Form;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Provides shared, long-form descriptions for Blazy formatter forms.
 *
 * Centralizes complex help text to keep form definitions readable while
 * preserving detailed guidance for advanced use cases.
 */
trait TraitDescriptions {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function nativeGridDescription() {
    $lb = $this->isAdminLb();

    return $this->t(
      '<br><br><strong>Accepted formats</strong> (space-separated):
<ul>
  <li><code>WIDTHxHEIGHT</code> or <code>WIDTH-HEIGHT</code></li>
  <li>Single numbers (columns)</li>
</ul>
Use one line per row (100% total or 12 columns) for readability.

<br><br>
<strong>Flexbox</strong> (not Flexbox Masonry):
<ol>
  <li>
    <strong>Uniform width</strong>: columns <code>1-12</code>.
  </li>
  <li>
    <strong>Variable width (percent)</strong>: each row must total <code>100%</code>.
    <br><code>25 50 25</code><br><code>33 34 33</code>
  </li>
  <li>
    <strong>Variable width + fixed height</strong>:
    <br><code>WIDTH-HEIGHT</code>, where HEIGHT is one of:
    <br><code>xxs xs sm md lg xl xxl x2l x3l x4l x5l</code>
    <br>Examples:
    <br><code>100-xxl</code>
    <br><code>50-md 50-md</code>
  </li>
</ol>

<strong>Native Grid</strong>:
<ol>
  <li>
    <strong>One-dimensional</strong> (masonry-like):
    <br><code>4</code> (auto height) or <code>4x4</code> (fixed height).
    <br><em>Best for scaled images.</em>
  </li>
  <li>
    <strong>Two-dimensional</strong>:
    <br>Column x row pairs (maximum 12):
    <br><code>4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2</code>
    <br>Single values repeat uniformly.
    <br><em>Best when:</em>
    <ul>
      <li>CSS background is enabled</li>
      <li>Item count matches the grid</li>
      <li>Image aspect ratio is disabled</li>
    </ul>
  </li>
</ol>
@lb',
      [
        '@lb' => $lb ? '' : $this->t('Requires a grid-based Display style. Leave empty to build manually.'),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function baseDescriptions(): array {
    $scopes = $this->scopes;
    $namespace = static::$namespace;
    $help = '/admin/help/blazy_ui';
    $ui_url = '/admin/config/media/blazy';
    $lb = $this->isAdminLb();

    if ($this->blazyManager->moduleExists('help')) {
      $help = Url::fromUri('internal:/admin/help/blazy_ui')->toString();
    }

    if ($this->blazyManager->moduleExists('blazy_ui')) {
      $ui_url = Url::fromRoute('blazy.settings')->toString();
    }

    $view_mode = $this->t(
      'Required to access fields or use a custom entity display as a fallback. If the selected View mode contains fields, ensure it is enabled and that the fields used here are not hidden.'
    );

    if ($this->blazyManager->moduleExists('field_ui')) {
      $view_mode .= ' ' . $this->t(
        'Manage view modes on the <a href=":url">View modes page</a>.',
        [
          ':url' => Url::fromRoute('entity.entity_view_mode.collection')->toString(),
        ]
      );
    }

    return [
      'background' => $this->background(),

      'preload' => $this->t(
        'Preload optimizes the loading of late-discovered resources (such as CSS backgrounds) or critical media (hero images). Preloading instructs the browser to fetch a resource earlier than it would normally discover—before Native lazy loading or JavaScript-based loaders begin. Preloaded resources are cached and reused when needed; nothing is executed at preload time.
<br><br>
To avoid overuse, this option is limited to hero media defined by <b>Loading priority: unlazy</b> or <b>slider</b>. <a href=":url">Read more</a>.',
        [
          ':url' => 'https://web.dev/preload-critical-assets/',
        ]
      ),

      'link' => $this->t(
        '<strong>Supported types</strong>: Link field or plain text URL. Common uses include “Read more” or “View case study”. If linking to an entity, ensure its formatter outputs linkable text (e.g. ID or Label).
<strong>Two behaviors</strong>:
<ol>
  <li>If <strong>Media switcher → Image linked by Link field</strong> is available and selected, it will be used to wrap the image—only when the formatter output is a plain URL.</li>
  <li>Otherwise, when used as a <strong>Caption field</strong>, the link is positioned and wrapped with a dedicated class: <strong>@class</strong>.</li>
</ol>',
        [
          '@class' => $namespace === 'blazy' ? 'blazy__caption--link' : $namespace . '__link',
        ]
      ),

      'loading' => $this->t(
        'Controls the HTML <b>loading</b> attribute, which directly affects <a href=":lcp">LCP</a>.
Use <b>unlazy</b> or <b>slider</b> only once per page for hero media.

<ul>
  <li><b>lazy</b> (default): best for off-screen media.</li>
  <li><b>auto</b>: browser decides.</li>
  <li><b>eager</b>: load immediately (minimum for LCP).</li>
  <li><b>defer</b>: lazy-load after the first row; disables global no-JS lazy loading for this field.</li>
  <li><b>unlazy</b>: recommended for static hero media.</li>
  <li><b>slider</b>: hero sliders with a single visible slide. For non-hero sliders, use <b>lazy</b> or <b>defer</b>. Available only on slider formatters.</li>
</ul>
<b>Note:</b> Avoid lazy-loading LCP media. <a href=":webdev">Read more</a> or see <a href=":heroes">Building heroes</a>.',
        [
          ':lcp' => 'https://web.dev/lcp/',
          ':webdev' => 'https://web.dev/browser-level-image-lazy-loading/#avoid-lazy-loading-images-that-are-in-the-first-visible-viewport',
          ':heroes' => $help . '#heroes',
        ]
      ),

      'image_style' => $this->t(
        'Content image style used as a fallback when overriding the global <a href=":url">Responsive image 1px placeholder</a>. Leave empty to respect Responsive image fallbacks. If set, this style becomes the sole rendered image. It is also used to provide dimensions for non-image media (e.g. local video) to establish sizing alongside aspect ratio.',
        [
          ':url' => $ui_url,
        ]
      ),

      'responsive_image_style' => $this->resimageDescriptions(),

      'media_switch' => $this->t(
        '<ul>
          <li><b>Link to content / by Link field</b>: wrap images with a link.</li>
          <li><b>Image to iframe</b>: video loads after interaction; requires Aspect ratio.</li>
          <li><b>Lightboxes</b>: depends on installed integrations (Colorbox, PhotoSwipe, Splidebox, Slick, etc.).</li>
          @rendered
        </ul>
    @lb',
        [
          '@rendered' => $scopes->form('fieldable')
            ? $this->t('<li><b>Image rendered by its formatter</b>: image-related options here are ignored (breakpoints, styles, background, ratio, lazy loading, etc.). Use only when a specialized image formatter is required.</li>')
            : '',
          '@lb' => $lb
            ? ''
            : $this->t('Add a <em>Thumbnail style</em> when using Splidebox, Slick, or similar. If the form state becomes unstable, try selecting “<strong>- None -</strong>” first.'),
        ]
      ),

      'box_style' => $this->t(
        'Only relevant for lightboxes under Media switcher. Supports both Responsive and regular images.'
      ),

      'box_media_style' => $this->t(
        'Allows different lightbox video dimensions. Or can be used to have a swipable video if <a href=":photoswipe">Blazy PhotoSwipe</a>, or <a href=":slick">Slick Lightbox</a>, or <a href=":splidebox">Splidebox</a> installed.',
        [
          ':photoswipe' => 'https://drupal.org/project/blazy_photoswipe',
          ':slick' => 'https://drupal.org/project/slick_lightbox',
          ':splidebox' => 'https://drupal.org/project/splidebox',
        ]
      ),

      'box_caption' => $this->t(
        'Automatic will search for Alt text first, then Title text.'
      ),

      'box_caption_custom' => $this->t(
        'Multi-value rich text field will be mapped to each image by its delta.'
      ),

      'ratio' => $this->t(
        'Aspect ratio for responsive images and iframes.
Helps prevent layout shifts (CLS), excess whitespace, and collapsed containers.

<ul>
<li><b>Fixed</b>: same ratio for all items. Pure CSS.</li>
<li><b>Fluid</b>: dimensions calculated dynamically (CSS first, JS fallback).</li>
<li><b>Empty</b>: manage ratios manually (e.g. GridStack, Native Grid, custom works).</li>
</ul>

Image styles, including thumbnail for Blur, and video dimensions must match the ratio, or distortion will occur, <a href=":ratio">learn more</a>.',
        [
          ':ratio' => $help . '#aspect-ratio',
        ]
      ),

      'view_mode' => $view_mode,

      'thumbnail_style' => $this->t(
        'Usages: <ol><li>Placeholder replacement for image effects (blur, etc.)</li><li>Splidebox/PhotoSwipe thumbnail</li><li>Custom works with thumbnails.</li></ol> Be sure to have similar aspect ratio for the best blur effect. Leave empty to not use thumbnails.'
      ),

      'image' => $this->t(
        '<strong>Required for</strong>: <ul><li>image attribute translation,</li><li>lightboxes as image triggers,</li><li>(remote|local) video high-res or poster image.</li><li>thumbnail/ slider navigation association, etc.</li></ul>Main background/stage/poster image field with the only supported field types: <b>Image</b> or <b>Media</b> containing an Image field. Add a new Image field to this entity, if not the Image bundle. Reuse the exact same image field (normally <strong>field_media_image</strong>) across various entitiy types (Image, Remote video, Local audio/video, etc.) within this particular entity (says, Media). This exact same field is also used for bundle <b>Image</b> to have a mix of videos and images if this entity is Media. Leaving it empty will fallback to the video provider thumbnails, and may cause issues due to failing requirements above.'
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function gridDescriptions(): array {
    $scopes = $this->scopes;
    $lb = $this->isAdminLb();

    $description = $this->t(
      '@lbGrid controls column count. Unless noted below, enter a number <code>1-12</code> or leave empty.',
      [
        '@lb' => $lb ? '' : $this->t('Clear the value first if form states behave unexpectedly.'),
      ]
    );
    if ($scopes->is('slider')) {
      $description .= $this->t(
        '<br /><strong>Requires</strong>:<ol><li>Any grid-related Display style,</li><li>Visible items,</li><li>Skin Grid for starter,</li><li>A reasonable amount of contents.</li></ol>'
      );
    }
    return [
      'grid' => $description,

      'grid_medium' => $this->t(
        'Uniform columns only (<code>1-12</code>) for medium screens (641-1024px). For Native Grid (2D) and Flexbox (non-masonry), WIDTH-HEIGHT pairs must match Grid (large).'
      ),

      'grid_small' => $this->t(
        'Uniform columns only (<code>1-2</code>) for small screens (≤640px). Below this, layout falls back to one column.'
      ),

      'visible_items' => $this->t(
        'How many items per display at a time.'
      ),

      'preserve_keys' => $this->t(
        'If checked, keys will be preserved. Default is FALSE which will reindex the grid chunk numerically.'
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function gridHeaderDescription() {
    return $this->t('Depends on the <strong>Display style</strong>.');
  }

  /**
   * {@inheritdoc}
   */
  public function openingDescriptions(): array {
    $lb = $this->isAdminLb();
    return [
      // @todo deprecate and remove after sub-modules.
      'background' => $this->background(),

      'by_delta' => $this->t(
        'Display a single item by delta, starting from 0. Leave it -1 to display all. Useful to display a multi-value field when broken down into a single display like Layout Builder blocks so that one field can occupy multiple regions simply by using its delta. More efficient than creating different single fields for the same image or media. Almost similar to Views <strong>Display all values in the same row (DAVISR)</strong>, except only designated to display a single value beyond Views UI. If embedded inside Views, this option is not available for more robust Views DAVISR. Be sure to disable Display style and grid options since it will show one item only.'
      ),

      'caption' => $this->t(
        'Enable any of the following fields as captions. These fields are treated and wrapped as captions.'
      ),

      'layout' => $this->t(
        'Requires a skin. The builtin layouts affects the entire items uniformly. Leave empty to DIY.'
      ),

      'skin' => $this->t(
        'Skins allow various layouts with just CSS. Some options below depend on a skin. Leave empty to DIY. Or use the provided hook_info() and implement the skin interface to register ones.'
      ),

      'style' => $this->t(
        'Unless otherwise specified, it requires <strong>Grid</strong>:<ul><li><strong>Columns</strong> is best with irregular image sizes (scale width, empty height), affects the natural order of grid items, top-bottom, not left-right, free height.</li><li><strong>Foundation</strong> with regular cropped ones, left-right, fixed height.</li><li><strong>Flexbox</strong> with limited non-repeatable non-gapless 3-4 columns, left-right flow, free or configurable [min-]height@lb.</li> <li><strong>Flex Masonry</strong> (@deprecated due to an epic failure) uses Flexbox, supports (ir)-regular, left-right flow, requires aspect ratio fluid to layout correctly, free height.</li><li><strong>Native Grid</strong> supports both one and two dimensional grids, left-right, free height for masonry, or fixed height for boxy grid.</li></ul> Unless required, leave empty to use default formatter, or style. Save for <b>Grid Foundation</b>, the rest are experimental!',
        [
          '@lb' => $lb ? '' : $this->t(', see Blazy Layout sub-module for Layout Builder'),
        ]
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function svgDescriptions(): array {
    $sanitizer = 'https://github.com/darylldoyle/svg-sanitizer';
    return [
      'inline' => $this->t(
        'Render SVG inline (not via IMG).
Disable CSS background.
Trusted users only due to <a href=":url1">SVG security risks</a>.
Requires <a href=":url2">SVG Sanitizer</a>.',
        [
          ':url1' => 'https://www.w3.org/wiki/SVG_Security',
          ':url2' => $sanitizer,
        ]
      ),

      'sanitize' => $this->t(
        'Sanitize the SVG XML code to prevent XSS attacks. Required <a href=":url">SVG Sanitizer</a>.',
        [
          ':url' => $sanitizer,
        ]
      ),

      'sanitize_remote' => $this->t(
        'Remove attributes that reference remote files, this will stop HTTP leaks but will add an overhead to the sanitizer.'
      ),

      'fill' => $this->t(
        'Force the fill to currentColor to allow the SVG inherit coloring from the enclosing tag, such as a link tag.'
      ),

      'hide_caption' => $this->t(
        'Unlike images, SVG has no ALT and TITLE attributes, except for SVG Image Field, or core file Description field. This option will hide captions, and put them into image attributes instead. Relevant if Inline option is disabled aka using IMG tag. Be sure to enable them under the Caption fields.'
      ),
      'attributes' => $this->t(
        'SVG dimensions source:
<ul>
<li><code>none</code>: disables Aspect ratio</li>
<li><code>image_style</code>: ansich, will use the provided Image style, to get consistent heights within carousels, or rigid grids</li>
<li><code>WIDTHxHEIGHT</code>: e.g.: 800x400, for custom defined dimensions. Default or fallback to extract from SVG attributes, unless <strong>none</strong> is set</li>
</ul>Only width and height are supported. Affected by Aspect ratio option.'
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function closingDescriptions(): array {
    $lb = $this->isAdminLb();

    return [
      'use_theme_field' => $this->t(
        'Wrap Blazy field output into regular field markup (field.html.twig). Vanilla output otherwise. @lb',
        [
          '@lb' => $lb ? $this->t('If enabled, it may break CSS background due to extra divities. Backgrounds require very minimal divities.') : '',
        ]
      ),
    ];
  }

  /**
   * Returns formatter base descriptions.
   *
   * @return string
   *   The form item description.
   */
  protected function resimageDescriptions(): string {
    $scopes = $this->scopes;
    if (!$scopes->is('responsive_image')) {
      return '';
    }
    $url = Url::fromRoute('entity.responsive_image_style.collection')->toString();
    $description = $this->t(
      'Responsive image style for the main stage image is more reasonable for large images. Works with multi-serving IMG, PICTURE element, or CSS background to have multi-breakpoint backgrounds. Leave empty to disable. <a href=":url" target="_blank">Manage responsive image styles</a>.',
      [
        ':url' => $url,
      ]
    );
    if ($this->blazyManager->moduleExists('blazy_ui')) {
      $description .= ' ' . $this->t('<a href=":url2">Enable lazyloading Responsive image</a>.', [
        ':url2' => Url::fromRoute('blazy.settings')->toString(),
      ]);
    }
    return $description;
  }

  /**
   * Returns background description, due to dups till sub-module updates.
   *
   * @return string
   *   The form item description.
   */
  private function background(): string {
    $lb = $this->isAdminLb();

    return $this->t(
      'Enable to render the image as a CSS background.
Use a Responsive image for multi-breakpoint backgrounds.
This allows CSS features such as <code>background-size: cover</code> and fixed attachment.

<br><strong>Important:</strong> Requires an Aspect ratio to prevent collapsed containers.
Exceptions apply when the layout manages height (e.g. GridStack), a grid min-height is set, or a manual min-height is applied to <strong>.b-bg</strong>.
@lb',
      [
        '@lb' => $lb
          ? $this->t('<br><strong>Note:</strong> <strong>Use field template</strong> must be disabled for backgrounds to work. Backgrounds are not draggable, only replaceable.')
          : '',
      ]
    );
  }

}
