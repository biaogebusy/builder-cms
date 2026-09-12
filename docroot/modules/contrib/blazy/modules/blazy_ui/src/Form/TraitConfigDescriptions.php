<?php

namespace Drupal\blazy_ui\Form;

/**
 * Provides standardized configuration descriptions for Blazy UI forms.
 *
 * This trait centralizes long-form help text to keep form definitions readable
 * while maintaining detailed, accurate documentation.
 */
trait TraitConfigDescriptions {

  /**
   * Returns a list of configuration descriptions.
   *
   * @param array $data
   *   The data being passed.
   *
   * @return array
   *   The form item descriptions.
   */
  protected function description(array $data = []): array {
    $help = $data['help'] ?? '';
    $bl_exists = $data['bl_exists'] ?? FALSE;
    $bl_help = $data['bl_help'] ?? FALSE;

    return [
      'admin_css' => $this->t(
        'Uncheck to disable Blazy-specific compact admin form styling. Only disable this if it conflicts with your admin theme.'
      ),

      'use_oembed' => $this->t(
        'Enable oEmbed when available. This is primarily for VEF compatibility, which already manages its own embed codes. It is irrelevant for Drupal core, which already uses oEmbed. When enabled, VEF embeds will be converted to oEmbed if the provider is available; otherwise they remain unchanged. Note: some providers (e.g. Instagram, Facebook) may require App ID and secret credentials even for simple oEmbed reads. YouTube does not.'
      ),

      'privacy_consent' => $this->t(
        'Keep disabled until further fixes or patches are applied. When enabled, YouTube videos are loaded via <code>www.youtube-nocookie.com</code>, preventing tracking cookies until playback begins. This improves GDPR compliance. The current media player already fairly complies with GDPR; this option further enhances compliance.'
      ),

      'lazy_html' => $this->t(
        'When <code>theme_blazy()</code> cannot interpret a media output, it prints the markup as-is. This content is typically paragraph-sized HTML. Enable this option to lazy-load such HTML (commonly heavy third-party embeds like Instagram or Pinterest oEmbed). No AJAX is used. Otherwise, the HTML is printed immediately. Introduced in version <b>2.17</b> and not yet battle-tested. Potential issues include attached libraries and interactions with other Blazy features. Disable and report issues if encountered.'
      ),

      'use_encodedbox' => $this->t(
        'When enabled and supported by the selected lightbox, the lightbox HTML (local audio/video, Picture/Responsive images, Instagram oEmbed, etc.) is encoded. This also applies to CSS backgrounds. Provides minor byte savings. Disable if any issues occur.'
      ),

      'ratio_modern' => $this->t(
        'Uses the modern CSS <code>aspect-ratio</code> property when supported. Disable if issues arise. See <a href=":ui">Blazy help</a>, <a href=":url">caniuse.com</a>, or <a href=":msdn">MDN documentation</a>.',
        [
          ':ui' => $help . '#aspect-ratio',
          ':url' => 'https://caniuse.com/?search=aspect-ratio',
          ':msdn' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/aspect-ratio',
        ]
      ),

      'nojs' => $this->t(
        "Enable this option if you rely on native browser support and do not support Internet Explorer or other legacy browsers, or if polyfills are already provided globally by your theme. File sizes below are minified and gzipped. A plus (+) indicates dependencies such as <code>dblazy.js (~4KB)</code>.\n\nBlazy never loads all JavaScript at once. Scripts are loaded conditionally based on enabled features and options.\n\n<ul>
          <li><b>Lazyload</b>: Removes lazyload libraries and initializers (<code>blazy.js (original: 2.2KB, fork: 1.6KB+), blazy.load.js (1KB+), bio.js (1.7KB+)</code>) for native non-JS lazy loading. <br><b>Note:</b> A minimal <code>blazy/compat (&lt;1KB + bio.js)</code> and/or <code>blazy/dblazy</code> may still load when JS-dependent features are enabled:
            <ul>
              <li>Image effect animation or Blur</li>
              <li>Dynamic multi-breakpoint (fluid aspect ratio)</li>
              <li>Responsive/Picture-based breakpoints or static CSS backgrounds</li>
              <li>Local video</li>
              <li>Loading priority: defer</li>
              <li>Sub-modules (Slick, Splide, Ultimenu, Jumper, etc.)</li>
            </ul>
          </li>
          <li><b>Polyfills</b>: Loaded only when required (total ~3.6KB) if this option is unchecked. Includes <code>Object.assign, closest, forEach, matches, startsWith, CustomEvent</code>. WebP fallback mainly targets IE9. Other polyfills with questionable licenses should be added at the theme level, such as <a href=':io'>IntersectionObserver</a>. <br><b>Warning:</b> These polyfills may be deprecated when the <a href=':cash'>Cash DOM</a> module becomes available.</li>
        </ul>
        As of 2022/1, native lazy loading supports only <code>IMG</code> and <code>IFRAME</code>. Exceptions above cover Blur, DIV, VIDEO, fluid ratios, and deferred loading. Other JS features (media player, lightbox, masonry) can be disabled via formatters. jQuery (~31KB) is loaded only for Colorbox (4.8KB) and admin UI usage. See the <code>blazy/js</code> directory for details. <a href=':url'>Read more</a>.",
        [
          ':cash' => 'https://drupal.org/project/cash',
          ':url' => 'https://drupal.org/node/3257512',
          ':io' => 'https://github.com/w3c/IntersectionObserver',
        ]
      ),

      'noscript' => $this->t(
        'Enable to support <a href=":url">users without JavaScript</a>. While `&lt;noscript&gt;` provides a fallback, it adds HTML weight. If your target
    audience is modern sites or performance-critical, disable this fallback to
    shave off every possible byte.',
        [':url' => 'https://stackoverflow.com/questions/9478737']
      ),

      'one_pixel' => $this->t(
        'By default, a 1px Data URI image is used as a placeholder for lazy-loaded (Responsive) images, which performs significantly better. Disable this to use a Drupal-managed smallest or fallback image style instead. Ensure proper dimensions or minimum height/width via CSS to avoid layout reflow, or select an Aspect ratio in Blazy formatters. Since <b>2.10</b>, disabling this no longer causes double downloads, allowing a non-empty fallback image without additional HTTP requests.'
      ),

      'visible_class' => $this->t(
        'Adds the <code>is-b-visible</code> CSS class when an element enters the viewport. Enable only if needed (e.g. animations). If enabled, the observer is not destroyed and continues watching visibility changes.'
      ),

      'wrapper_class' => $this->t(
        'Removes non-essential wrapper classes (Field, Block, Views, etc.) to reduce DOM size when context is unnecessary for styling. Required classes such as lightbox or grid remain intact when configured.'
      ),

      'placeholder' => $this->t(
        "Useful primarily when using Views rewrite results. See <a href=':url2'>#2908861</a>. This overrides the global 1px Data URI placeholder when Views sanitization strips it, causing 404 errors. Must be a URL (e.g. <code>/blank.gif</code> or <code>/blank.svg</code>). Unlike SVG, 1px GIFs may have rendering issues (<a href=':url1'>#2795415</a>). Leave empty to use the default inline SVG/Data URI and avoid extra HTTP requests. For 100 images, leaving this empty avoids 100 additional requests. Sample SVG content:
        <br><code>&lt;svg xmlns='https://www.w3.org/2000/svg' viewBox='0 0 100 100'/&gt;</code>
        <br>Save as <code>blank.svg</code> at the web root and reference it as <code>/blank.svg</code>.",
        [
          ':url1' => 'https://drupal.org/node/2795415',
          ':url2' => 'https://drupal.org/node/2908861',
        ]
      ),

      'unstyled_extensions' => $this->t(
        'File extensions that should bypass (Responsive) image styles. Space-delimited without dots (e.g. <code>gif apng</code>). Typically used for animated images. Animation cannot be reliably detected, so this applies to all matching files. No thumbnails, blur, or image-style-dependent features are applied. Defaults to SVG.'
      ),

      'fx' => $this->t(
        'Select the image effect. Uses the Thumbnail style for placeholders, falling back to core Thumbnail. For best results, match aspect ratios between Thumbnail and Image styles, adjust offsets and thresholds, and keep images small. Extend effects via <code>hook_blazy_image_effects_alter()</code>. <b>Limitations:</b> Requires an Aspect ratio or explicit CSS <code>min-height</code> to avoid collapsed images. Cached permanently; clear caches if altered data does not appear. Disabled when un-lazied (no JS, iframe-only, sandboxed).'
      ),

      'blur_client' => $this->t(
        'Enable client-side Blur rendering. Server-side behavior embeds Data URI directly in markup. <b>Pros:</b> Reduced initial and final page weight, data cleared automatically, cacheable across pages. <b>Cons:</b> Requires an initial HTTP request. Can be combined with localStorage caching.'
      ),

      'blur_storage' => $this->t(
        'Caches Blur Data URI in localStorage to reduce repeat HTTP requests. Disable if localStorage is heavily used elsewhere. Storage limits vary (0.05KB–150KB per provider; typical quota 2-10MB). Large or unoptimized Blur styles may exceed limits. Automatically clears and recycles when the quota is reached.'
      ),

      'blur_minwidth' => $this->t(
        'Enable Blur only when the image style width exceeds this value (e.g. 767). Useful to disable Blur on mobile devices to avoid potential OOM (Out of Memory) issues or unnecessary effects on small thumbnails.'
      ),

      'blazy_layout' => $bl_exists
        ? $this->t('The following relates to <a href=":bl"><b>Blazy Layout</b></a>.', [':bl' => $bl_help])
        : $this->t('Requires <a href=":bl"><b>Blazy Layout</b></a> to be installed.', [':bl' => '/admin/modules#edit-modules-blazy']),

      'use_custom_css' => $this->t(
        'Enables a raw CSS textarea for fine-grained visual adjustments. <b>Warning!</b> Inline CSS may affect page rendering and stability beyond Blazy layouts if used incorrectly. Only enable this for trusted site builders who understand CSS scope and impact. When enabled, a <b>Custom CSS</b> textarea becomes available on <b>Layout Builder</b> administrative pages where <b>Blazy dynamic layout</b> is added. Disabling this option will automatically remove all injected custom CSS from <b>Blazy Layout</b> variant pages, and effectively disable the <b>Custom CSS</b> form item.'
      ),

      'css_scope' => $this->t(
        'Provide a single CSS selector scope (e.g. <code>.region-content</code>) to prevent targeting global elements such as <code>html</code> or <code>body</code>. Strongly recommended to reduce misuse. If provided, rules like <code>body { display: none }</code> are rewritten as <code>.region-content body { display: none }</code>. Leave empty only for solo development environments.'
      ),

      'max_region_count' => $this->t(
        'Defines the maximum number of regions. Defaults to 20 when set to 0 or below 9. Regions beyond this limit are hidden. Clear caches after changing to re-register regions.'
      ),

      'blazy' => $this->t(
        'The following settings relate to the legacy bLazy library.'
      ),

      'loadInvisible' => $this->t(
        'Load hidden or invisible elements. Enable if content within tabs, accordions, or hidden containers fails to load.'
      ),

      'offset' => $this->t(
        'Controls how early elements load before entering the viewport. Default is <strong>100</strong> pixels.'
      ),

      'saveViewportOffsetDelay' => $this->t(
        'Throttle delay for calling <code>saveViewportOffset</code> on resize. Default: <strong>50</strong>ms.'
      ),

      'validateDelay' => $this->t(
        'Throttle delay for calling the validation function on scroll or resize. Default: <strong>25</strong>ms.'
      ),

      'container' => $this->t(
        'If Blazy is inside a scrolling container, specify valid comma-separated CSS selectors (excluding <code>#drupal-modal, .is-b-scroll</code>). Examples: <code>#my-container, .another-container</code>. The container must have <code>overflow</code> set to <code>auto</code> or <code>scroll</code>. Inspect via browser dev tools (<code>F12</code>). IntersectionObserver does not require this; legacy bLazy does. Symptoms of misconfiguration include an eternal loading indicator.'
      ),

      'io' => $this->t(
        'Falls back gracefully to the legacy bLazy fork for older browsers unless explicitly disabled. Works reliably down to IE9, though advanced features (Blur, etc.) requiring additional polyfills may silently fail. The following settings relate to the <a href=":url">IntersectionObserver API</a>.',
        [
          ':url' => 'https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API',
        ],
      ),

      'unblazy' => $this->t(
        'Disable the legacy bLazy fork when fully relying on IntersectionObserver and not supporting Internet Explorer. Fork size is ~1KB gzip. Clear caches after changing.'
      ),

      'rootMargin' => $this->t(
        'Defines margins around the root element, similar to CSS margin syntax (e.g. <code>10px 20px 30px 40px</code> or top right bottom left). Values may be percentages. Expands or shrinks the root bounding box before intersection calculations. The default is conservative based on the system. To improve perceived speed with the preloading and decoding, try: 200px 0px, or just 200px, if having horizontal or vertical sliders. Meaning: trigger loading 200px earlier before element enters viewport, either vertically or horizontally. Do not set it too high, otherwise blur, animation, etc, it may be executed too early making such features useless. Defaults to all zeros.'
      ),

      'threshold' => $this->t(
        'A single number or list of numbers indicating the percentage of target visibility required to trigger the callback. Examples: <code>0.5</code> triggers at 50% visibility; <code>0, 0.25, 0.5, 0.75, 1</code> triggers at each interval. Default is <code>0</code>. A value of <code>1</code> requires full visibility.'
      ),

      'disconnect' => $this->t(
        'Disconnects the IntersectionObserver once all images are loaded. Disable if images appear loaded but remain stuck in a loading state.'
      ),
    ];
  }

}
