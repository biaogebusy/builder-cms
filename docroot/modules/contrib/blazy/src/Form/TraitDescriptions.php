<?php

namespace Drupal\blazy\Form;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * A description Trait to declutter, and focus more on form elements.
 */
trait TraitDescriptions {

  use StringTranslationTrait;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * Returns base descriptions.
   */
  protected function baseDescriptions($scopes): array {
    $namespace = $scopes->get('namespace', 'blazy');
    $ui_url = '/admin/config/media/blazy';
    if ($this->blazyManager->moduleExists('blazy_ui')) {
      $ui_url = Url::fromRoute('blazy.settings')->toString();
    }

    $view_mode = $this->t('Required to grab the fields, or to have custom entity display as fallback display. If it has fields, be sure the selected "View mode" is enabled, and the enabled fields here are not hidden there.');
    if ($this->blazyManager->moduleExists('field_ui')) {
      $view_mode .= ' ' . $this->t('Manage view modes on the <a href=":view_modes">View modes page</a>.', [':view_modes' => Url::fromRoute('entity.entity_view_mode.collection')->toString()]);
    }

    return [
      'preload' => $this->t("Preload to optimize the loading of late-discovered resources. Normally large or hero images below the fold. By preloading a resource, you tell the browser to fetch it sooner than the browser would otherwise discover it before Native lazy or lazyloader JavaScript kicks in, or starts its own preload or decoding. The browser caches preloaded resources so they are available immediately when needed. Nothing is loaded or executed at preloading stage. <br>Just a friendly heads up: do not overuse this option, because not everything are critical, <a href=':url'>read more</a>.", [
        ':url' => 'https://www.drupal.org/node/3262804',
      ]),
      'link' => $this->t('<strong>Supported types</strong>: Link or plain Text containing URL. Link to content: Read more, View Case Study, etc. If an entity, be sure its formatter is linkable strings like ID or Label. <strong>Two behaviors</strong>: <ol><li>If <strong>Media switcher &gt; Image linked by Link field</strong> is available as an option and selected, it will be gone to serve as a wrapping link of the image, only if its formatter/ output is plain text URL.</li><li>As opposed to <strong>Caption fields</strong> if available, it will be positioned and wrapped with a dedicated class: <strong>@class</strong>.</li></ol>', [
        '@class' => $namespace == 'blazy' ? 'blazy__caption--link' : $namespace . '__link',
      ]),
      'loading' => $this->t("Decide the `loading` attribute affected by the above fold aka onscreen critical contents aka <a href=':lcp'>LCP</a>. <ul><li>`lazy`, the default: defers loading below fold or offscreen images and iframes until users scroll near them.</li><li>`auto`: browser determines whether or not to lazily load. Only if uncertain about the above fold boundaries given different devices. </li><li>`eager`: loads right away. Similar effect like without `loading`, included for completeness. Good for above fold.</li><li>`defer`: trigger native lazy after the first row is loaded. Will disable global `No JavaScript: lazy` option on this particular field, <a href=':defer'>read more</a>.</li><li>`unlazy`: explicitly removes loading attribute enforced by core. Also removes old `data-[SRC|SRCSET|LAZY]` if `No JavaScript` is disabled. Best for the above fold.</li><li>`slider`, if applicable: will `unlazy` the first visible, and leave the rest lazyloaded. Best for sliders (one visible at a time), not carousels (multiple visible slides at once).</li></ul><b>Note</b>: lazy loading images/ iframes for the above fold is anti-pattern, avoid, <a href=':more'>read more</a>, even <a href=':webdev'>more</a>.", [
        ':lcp' => 'https://web.dev/lcp/',
        ':more' => 'https://www.drupal.org/node/3262724',
        ':defer' => 'https://drupal.org/node/3120696',
        ':webdev' => 'https://web.dev/browser-level-image-lazy-loading/#avoid-lazy-loading-images-that-are-in-the-first-visible-viewport',
      ]),
      'image_style' => $this->t('The content image style. This will be treated as the fallback image to override the global option <a href=":url">Responsive image 1px placeholder</a>, which is normally smaller, if Responsive image are provided. Shortly, leave it empty to make Responsive image fallback respected. Otherwise this is the only image displayed. This image style is also used to provide dimensions not only for image/iframe but also any media entity like local video, where no images are even associated with, to have the designated dimensions in tandem with aspect ratio as otherwise no UI to customize for.', [':url' => $ui_url]),
      'media_switch' => $this->t('Clear cache if lightboxes do not appear here due to being permanently cached. <ol><li>Link to content/ by Link field: for aggregated small media contents -- slicks, splides, grids, etc.</li><li>Image to iframe: video is hidden below image until toggled, otherwise iframe is always displayed, and draggable fails. Aspect ratio applies.</li><li>(Quasi-)lightboxes: Colorbox, ElevateZoomPlus, Intense, Photobox, PhotoSwipe, Magnific Popup, Slick Lightbox, Splidebox, Zooming, etc. Depends on the enabled supported modules, or has known integration with Blazy. See docs or <em>/admin/help/blazy_ui</em> for details.</li></ol> Add <em>Thumbnail style</em> if using Photobox, Slick, or others which may need it. Try selecting "<strong>- None -</strong>" first before changing if trouble with this complex form states.'),
      'box_style' => $this->t('Supports both Responsive and regular images.'),
      'box_media_style' => $this->t('Allows different lightbox video dimensions. Or can be used to have a swipable video if <a href=":photoswipe">Blazy PhotoSwipe</a>, or <a href=":slick">Slick Lightbox</a>, or <a href=":splidebox">Splidebox</a> installed.', [
        ':photoswipe' => 'https:drupal.org/project/blazy_photoswipe',
        ':slick' => 'https:drupal.org/project/slick_lightbox',
        ':splidebox' => 'https:drupal.org/project/splidebox',
      ]),
      'box_caption' => $this->t('Automatic will search for Alt text first, then Title text. Try selecting <strong>- None -</strong> first when changing if trouble with form states.'),
      'box_caption_custom' => $this->t('Multi-value rich text field will be mapped to each image by its delta.'),
      'ratio' => $this->t('Aspect ratio to get consistently responsive images and iframes. Coupled with Image style. And to fix layout reflow, excessive height issues, whitespace below images, collapsed container, no-js users, etc. <a href=":dimensions" target="_blank">Image styles and video dimensions</a> must <a href=":follow" target="_blank">follow the aspect ratio</a>. If not, images will be distorted. <a href=":link" target="_blank">Learn more</a>. <ul><li><b>Fixed ratio:</b> all images use the same aspect ratio mobile up. Use it to avoid JS works, or if it fails Responsive image. </li><li><b>Fluid:</b> aka dynamic, dimensions are calculated. First specific for non-responsive images, using PHP for pure CSS if any matching the fixed ones (1:1, 2:3, etc.), <a href=":ratio">read more</a>. If none found, JS works are attempted to fix it.</li><li><b>Leave empty:</b> to DIY (such as using CSS mediaquery), or when working with gapless grids like GridStack, or Blazy Native Grid.</li></ul>', [
        ':dimensions'  => '//size43.com/jqueryVideoTool.html',
        ':follow'      => '//en.wikipedia.org/wiki/Aspect_ratio_%28image%29',
        ':link'        => '//www.smashingmagazine.com/2014/02/27/making-embedded-content-work-in-responsive-design/',
        ':ratio'       => '/admin/help/blazy_ui#aspect-ratio',
      ]),
      'view_mode' => $view_mode,
      'thumbnail_style' => $this->t('Usages: <ol><li>Placeholder replacement for image effects (blur, etc.)</li><li>Photobox/PhotoSwipe thumbnail</li><li>Custom works with thumbnails.</li></ol> Be sure to have similar aspect ratio for the best blur effect. Leave empty to not use thumbnails.'),
      'image' => $this->t('<strong>Required for</strong>: <ul><li>image attribute translation,</li><li>lightboxes as image triggers,</li><li>(remote|local) video high-res or poster image.</li><li>thumbnail/ slider navigation association, etc.</li></ul>Main background/stage/poster image field with the only supported field types: <b>Image</b> or <b>Media</b> containing an Image field. Add a new Image field to this entity, if not the Image bundle. Reuse the exact same image field (normally <strong>field_media_image</strong>) across various entitiy types (Image, Remote video, Local audio/video, etc.) within this particular entity (says, Media). This exact same field is also used for bundle <b>Image</b> to have a mix of videos and images if this entity is Media. Leaving it empty will fallback to the video provider thumbnails, and may cause issues due to failing requirements above.'),
    ];
  }

  /**
   * Returns grid descriptions.
   */
  protected function gridDescriptions($scopes): array {
    $description = $this->t('Empty the value first if trouble with changing form states. The amount of block grid columns (1 - 12, or empty) for large monitors 64.063em  (1025px) up.');
    if ($scopes->is('slider')) {
      $description .= $this->t('<br /><strong>Requires</strong>:<ol><li>Any grid-related Display style,</li><li>Visible items,</li><li>Skin Grid for starter,</li><li>A reasonable amount of contents.</li></ol>');
    }
    return [
      'grid' => $description,
      'grid_medium' => $this->t('Only accepts uniform columns (1 - 12, or empty) for medium devices 40.063em - 64em (641px - 1024px) up, even for Native Grid due to being pure CSS without JS.'),
      'grid_small' => $this->t('Only accepts uniform columns (1 - 2, or empty) for small devices 0 - 40em (640px) up due to small real estate, even for Native Grid due to being pure CSS without JS. Below this value, always one column.'),
      'visible_items' => $this->t('How many items per display at a time.'),
      'preserve_keys' => $this->t('If checked, keys will be preserved. Default is FALSE which will reindex the grid chunk numerically.'),
    ];
  }

  /**
   * Returns grid header description.
   */
  protected function gridHeaderDescription() {
    return $this->t('Depends on the <strong>Display style</strong>.');
  }

  /**
   * Returns native grid description.
   */
  protected function nativeGridDescription() {
    return $this->t('<br>Specific for <b>Native Grid</b>, two recipes: <ol><li><b>One-dimensional</b>: Input a single numeric column grid, acting as Masonry. <em>Best with</em>: scaled images.</li><li><b>Two-dimensional</b>: Input a space separated value with <code>WIDTHxHEIGHT</code> pair based on the amount of columns/ rows, at max 12, e.g.: <br><code>4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2</code> <br>This will resemble GridStack optionset <b>Tagore</b>. Any single value e.g.: <code>4x4</code> will repeat uniformly like one-dimensional. <br><em>Best with</em>: <ul><li><b>Use CSS background</b> ON.</li><li>Exact item amount or better more designated grids than lacking. Use a little math with the exact item amount to have gapless grids.</li><li>Disabled image aspect ratio to use grid ratio instead.</li></ul></li></ol>This requires any grid-related <b>Display style</b>. Unless required, leave empty to DIY, or to not build grids.');
  }

  /**
   * Returns opening descriptions.
   */
  protected function openingDescriptions($scopes): array {
    return [
      'background' => $this->t('Check this to turn the image into CSS background. This opens up the goodness of CSS, such as background cover, fixed attachment, etc. <br /><strong>Important!</strong> Requires an Aspect ratio, otherwise collapsed containers. Unless explicitly removed such as for GridStack which manages its own problem, or a min-height is added manually to <strong>.b-bg</strong> selector.'),
      'caption' => $this->t('Enable any of the following fields as captions. These fields are treated and wrapped as captions.'),
      'layout' => $this->t('Requires a skin. The builtin layouts affects the entire items uniformly. Leave empty to DIY.'),
      'skin' => $this->t('Skins allow various layouts with just CSS. Some options below depend on a skin. Leave empty to DIY. Or use the provided hook_info() and implement the skin interface to register ones.'),
      'style' => $this->t('Unless otherwise specified, the styles require <strong>Grid</strong>. Difference: <ul><li><strong>Columns</strong> is best with irregular image sizes (scale width, empty height), affects the natural order of grid items, top-bottom, not left-right.</li><li><strong>Foundation</strong> with regular cropped ones, left-right.</li><li><strong>Flex Masonry</strong> (@deprecated due to an epic failure) uses Flexbox, supports (ir)-regular, left-right flow, requires aspect ratio fluid to layout correctly.</li><li><strong>Native Grid</strong> supports both one and two dimensional grid.</li></ul> Unless required, leave empty to use default formatter, or style. Save for <b>Grid Foundation</b>, the rest are experimental!'),
    ];
  }

  /**
   * Returns SVG description, from SVG image field to support it in Blazy.
   */
  protected function svgDescriptions(): array {
    $sanitizer = 'https://github.com/darylldoyle/svg-sanitizer';
    return [
      'inline' => $this->t('If checked, SVG is not embedded in the IMG tag. Be sure to disable CSS background option. Only enable for CSS and JavaScript manipulations, and trusted users, due to <a href=":url1">inline SVG security</a>. Required <a href=":url2">SVG Sanitizer</a>.', [
        ':url1' => 'https://www.w3.org/wiki/SVG_Security',
        ':url2' => $sanitizer,
      ]),
      'sanitize' => $this->t('Sanitize the SVG XML code to prevent XSS attacks. Required <a href=":url">SVG Sanitizer</a>.', [
        ':url' => $sanitizer,
      ]),
      'sanitize_remote' => $this->t('Remove attributes that reference remote files, this will stop HTTP leaks but will add an overhead to the sanitizer.'),
      'fill' => $this->t('Force the fill to currentColor to allow the SVG inherit coloring from the enclosing tag, such as a link tag.'),
      'hide_caption' => $this->t('Unlike images, SVG has no ALT and TITLE attributes, except for SVG Image Field, or core file Description field. This option will hide captions, and put them into image attributes instead. Relevant if Inline option is disabled aka using IMG tag. Be sure to enable them under the Caption fields.'),
      'attributes' => $this->t('Input one of SVG dimension sources: <code>none, image_style, or WIDTHxHEIGHT</code>. To disable, input: <strong>none</strong>, and will also disable Aspect ratio option. The <strong>image_style</strong> ansich will use the provided Image style, useful to get consistent heights within carousels, or rigid grids. The <strong>WIDTHxHEIGHT</strong>, e.g.: 800x400, for custom defined dimensions. Default or fallback to extract from SVG attributes, unless <strong>none</strong> is set. Only width and height are supported. Affected by Aspect ratio option.'),
    ];
  }

}
