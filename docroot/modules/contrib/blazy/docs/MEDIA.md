
***
## <a name="mixed-media"> </a>LINKABLE AND MIXED-MEDIA

To have a custom hi-res image/poster for (local|remote) video and audio, etc.:

- **Visit bundle pages:**

  * [Remote video](/admin/structure/media/manage/remote_video/fields)
  * [Video](/admin/structure/media/manage/video/fields)
  * [Audio](/admin/structure/media/manage/audio/fields)

- **Re-use** the existing `field_media_image` into each bundle.

- **Avoid creating a new field of Image**, it will fail mixed-media scenarios.
    The same principle is applicable to non-image|background (Document, etc.)
    when being used with/without image|background purposes. Normally you would
    select this field under **Blazy formatter > Main stage** to be sure.

- **Media switcher:**

    Select `Media switcher > Image to iframe` option, or lightboxes, etc.

- **Linkable Media:**

  To have unique linkable media:

  + add a Link or Text field to the Media bundles (not Content type or Node),
  + select it under **Link** option,
  + choose **Media switcher > Image linked by Link field**.

Please refer to [Media Architecture & Privacy](#media-architecture) for
optimization details.

***
## <a name="svg"> </a> SVG

Install **SVG Sanitizer** via Composer (see the [COMPOSER](#composer) section):

```bash
composer require enshrined/svg-sanitize
```
Read more about [SVG Sanitizer](https://github.com/darylldoyle/svg-sanitizer)

Blazy intentionally does not ship this dependency in its own `composer.json` for
security and maintenance reasons. If **SVG Sanitizer** is not installed, the
**Inline SVG** option will be disabled.

Since version 2.17, the formatter **Blazy Image with VEF (deprecated)** has been
repurposed to support SVG files. It is now named **Blazy File**.

Drupal core’s Image widget does not support SVG files. To upload SVGs, use a
**File**  field instead:

1. [/admin/structure/types/manage/page/fields](/admin/structure/types/manage/page/fields)

   * **Add a new field → Reference → File** for simple needs
   * Enable the **Description field** for SVG captions
   * Alternatively, choose **Reference → Other → File** for more complex needs

`/admin/structure/types/manage/page/fields`

2. [/admin/structure/types/manage/page/fields](/admin/structure/types/manage/project/page)

   * Select **Blazy File** and adjust configuration as needed

The **Blazy File** formatter can also be used for standard images when the SVG
extension is available. Otherwise, use **Blazy Image**. The two formatters are
kept separate to expose SVG-specific form options where appropriate.

This represents the most basic SVG support available in Drupal core without
installing additional modules. Blazy can render SVGs either as inline SVG or as
embedded SVG via `<img>`.

For more robust solutions, consider modules such as SVG Image Field, SVG Image,
and related projects.

**FYI**
* SVG Image overrides core formatters and widgets globally, which can make it
difficult to uninstall on sites with existing image fields.
* Blazy works well with SVG Image and similar modules.
* SVG form options are inspired by the SVG Image Field module. To honor this,
**Blazy File** supports its field type, enabling grids and various Blazy
features, including SVG carousels.
* SVG `<title>` element support is inspired by SVG Formatter.
* If an SVG appears smaller than expected, try applying width: 100% via CSS.

---
## <a name="webp"> </a>WEBP
* Drupal 9.2 supports WEBP conversion via **Convert WEBP** on the Image Styles
  administration page.
* Drupal 11 supports **Convert to AVIF**, with WEBP as a fallback.

If support for older browsers is required, Blazy provides a WEBP polyfill in the
Blazy UI under **No JavaScript**. Be sure to leave it **unchecked**.

**Benefits**

* Modern browsers continue using clean `<img>` markup without being forced into
  unnecessary `<picture>` elements for all WEBP images.
* Older browsers receive a `<picture>` fallback only when WEBP is unsupported.

---

### <a name="animate-css"> </a>ANIMATE.CSS INTEGRATION
The `.media` container is the primary target for animations (leveraging
[animate.css](https://github.com/daneden/animate.css)). This ensures a unified
transition regardless of the asset type (Picture, Image, or Rich Media).

The **Blur** effect can be replaced with `animate.css`.

#### Animation Sample:
- [GridStack](https://drupal.org/project/gridstack) at **Layout Builder** pages
- Use `Drupal\blazy\Utility\Animation` for quick population into
  **Image effect** option containing the **Blur** effect.
- See [blazy.api.php](https://git.drupalcode.org/project/blazy/blob/3.0.x/blazy.api.php)

#### Required Implementation Strategy:
1.  **Animation Registration**: Register animations via
    `hook_blazy_image_effects_alter`.
2.  **Attach the Library**: Use your theme, or `hook_blazy_attach_alter` to
    load the library selectively.
3.  **Effect Selection**: Choose the effects at **Blazy UI > Image effect**,
    available after cache clearing.

#### Optional Implementation Strategy:
1.  **Granular Control**: Use `hook_blazy_settings_alter` to programmatically
    inject or even switch the `blazies.fx` setting based on specific context.
2.  **CSS Overrides**: Override CSS using `.media` selector; it is more
    efficient to modify or override duration, delay and iteration in CSS
    than preprocess surgery.
2.  **Preprocess Overrides**: Tough route for non-CSS-savvy users. Put the
    relevant attributes into `.media`, only if fine-graned controls are
    required, otherwise default will do:

```php
/**
 * Implements hook_preprocess_blazy().
 */
function MYTHEME_preprocess_blazy(&$variables) {
  $settings = &$variables['settings'];
  $blazies = $settings['blazies'];

  // Scope the animation to a specific entity or field context.
  if ($blazies->get('entity.id') == 123
    && $blazies->get('field.name') == 'field_media_animated') {
    $prefix = 'data-b-animation';

    // Everything is optional for fine grained control, Blazy will automatically
    // populate animation name based on Image effect option, and that should
    // work immediately when the library is correctly loaded. Two options:
    // Overrides animation attributes here accordingly for more controls.
    $variables['attributes'][$prefix] = $blazies->get('fx') ?: 'wobble';
    $variables['attributes'][$prefix . '-duration'] = '3s';
    $variables['attributes'][$prefix . '-delay'] = '.3s';
    $variables['attributes'][$prefix . '-iteration-count'] = 'infinite';
  }
}
```

---

### <a name="native-lazyload"> </a> NATIVE LAZY-LOADING & CHROME BEHAVIOR
Blazy library last release was v1.8.2 (2016/10/25).

While [Native Lazy-loading](https://web.dev/native-lazy-loading/) is supported
by modern browsers (Chrome 76+, 2019/01), current implementations often use a
massive pre-fetch threshold (e.g., [8000px](https://cs.chromium.org/chromium/src/third_party/blink/renderer/core/frame/settings.json5?l=971-1003&rcl=e8f3cf0bbe085fee0d1b468e84395aad3ebb2cad)). Blazy remains essential as an intelligent fallback and a tool for precision orchestration where native
thresholds are too aggressive or blunt.

**Note:** If lazy-loading appears non-functional in modern browsers, check the
**Network tab**. The browser may have pre-fetched the asset based on its
internal heuristics. Blazy ensures the asset is handled correctly once the
threshold is actually met.

- **Update 2020-04-24:** Added logic to only trigger lazy-loading once the
initial viewport asset is confirmed loaded, preventing bandwidth contention, see
[#3120696](https://drupal.org/node/3120696)

---
<a href="#top">Back to Top &uarr;</a>
---
