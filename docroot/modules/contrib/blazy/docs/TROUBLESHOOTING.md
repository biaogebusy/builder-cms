
***
## <a name="troubleshooting"> </a>TROUBLESHOOTING
- **Masonry (Flexbox or Native Grid) issues**: If your layouts are broken, try
uninstalling BigPipe. Before version 2.17, we attempted to maintain BigPipe
compatibility, but it frequently broke Masonry on infinite pagers (VIS/IO).
Since version 3.x, BigPipe should work for 99% use cases, leaving 1% for:

  + CSS reordering issues which can not be fixed by Blazy alone, see
  BigPipe-related JS/CSS issues in Slick or Splide, especially if you enable the
  optional sitewide custom works aka slider-driven sites
  +  a rare edge case with complex AJAX setups
- **JavaScript issues**: These may no longer be relevant when
  `No JavaScript lazy` is enabled—unless you use features that Native Grid
  doesn't support (Blur, BG, Video, etc.) or require support for legacy browsers
  that cannot ditch the lazyloader script.
- **Theme conflicts**: Switch to a core theme temporarily to determine if your
  custom theme is the culprit.
- **Version Mismatch**: See [VERSION COMPATIBILITY](#first).
- **Resizing**: Browser resizing is not supported for lazyloading transitions;
  simply reload the page, or use mobile preview by pressing
  `CTRL/CMD + Shift + M`. **The reason**: During a resize, browsers often fail
  to provide accurate pixel ratio data between desktop and mobile without an
  unacceptable delay (4s+).
- **Console Errors**: Press F12 to check the browser console. Any JS error will
  prevent Blazy from initializing, often identified by "eternal blue loaders."
  Include the screenshot in your report if related to Blazy, otherwise fix them
  first.
- **Anonymous Testing**: Always view the browser console as an anonymous user,
  not just as an admin.
- **Collapsed/Distorted Images**: If images or Iframes are collapsed or
  non-responsive, select an **Aspect ratio**. If unsure, choose **Fluid** to
  allow the module to calculate the ratio automatically.
  [Aspect ratio guidelines](#aspect-ratio)

---

### 1. JavaScript Errors
References to the `bLazy` library are no longer required for versions forked at
2.6.

**Symptoms**: "Blazy is not defined." Images are missing, and the blue loader
spins indefinitely.

**Solution**:
Ensure no external JS errors exist. Steps:

* Switch to a core theme to rule out theme-specific JS breakage.
* Try disabling the **Disconnect** option under IO settings.

### 3. MIN-WIDTH
If images appear to shrink within a **floating** container, add the expected
`width` or `min-width` to the parent container via CSS. Non-floating containers
are unaffected.

### 4. MIN-HEIGHT
Add a `min-height` CSS rule to individual elements to avoid layout reflow when
not using **Aspect ratio** or when it is unsupported (e.g., Native Grid).
Without this, collapsed containers defeat the purpose of lazyloading.

**SOLUTIONS**:
Both layout reflow and lazyloading delays are resolved if the **Aspect ratio**
option is enabled. Adjust and override Blazy CSS/JS files as needed.

### 5. BLAZY FILTER
The Blazy Filter **must** run after **Align/Caption filters**. Otherwise, the
required `b-lazy` class is moved into `<figure>` elements, causing Blazy to fail
because it cannot find the `src` and `[data-src]` attributes.

Blazy Filter is incompatible with **Media embed** or
**Display embedded entities**. In those cases, disable Blazy Filter and use the
Blazy formatter inside those entities instead. It remains useful for User
Generated Content (UGC) where Media Embed is restricted.

### 6. INTERSECTION OBSERVER API
This API is bypassed if `No JavaScript lazy` is enabled, unless unsupported
features (Blur, BG, Video) are present.

* If **IntersectionObserver (IO)** is not loading all images, disable the
**Disconnect** option in the Blazy UI.
* If IO fails with Slick `slidesToShow > 1`, disable Slick `centerMode`. If
issues persist, choose `slider` or `unlazy` under the **Loading priority**
formatter option.

**FYI**: IO is also utilized for infinite pagers and lazyloaded blocks via the
IO module.

### 7. BLUR IMAGE EFFECT
Path: `/admin/config/media/blazy`

The **Blur** image effect overrides the **Placeholder** option. It uses the
**Thumbnail style** selected in Blazy formatters (falling back to core
"Thumbnail").

**For best results**:
* Enable **Aspect ratio** (non-fluid is preferred).
* Use matching aspect ratios for both Thumbnail and Image styles.
* Adjust **Offset** or **Threshold** (smaller values generally perform better).
* Use `hook_blazy_image_effects_alter()` to add custom effects like curtains or
slices.

**Limitations**: Currently requires a proper **Aspect ratio** to prevent
collapsed images. If one isn't set, manually add `width: 100%` and a
`min-height` via CSS.

**Blur overrides**: See [ANIMATE.CSS INTEGRATION](#animate-css)

### 9. BLAZY WITHIN SCROLLING CONTAINERS
Path: `/admin/config/media/blazy`

**Note**: IO does not require this configuration, but the old `bLazy` library
does. If Blazy is inside a scrolling container, provide comma-separated CSS
selectors (e.g., `#my-scrolling-container, .another-container`).

Common scrolling containers include `#drupal-modal` (Media Library). If a
container has CSS `overflow` set to `auto` or `scroll`, it must be defined here.
The selector `.is-b-scroll` can be used if the Blazy UI is inaccessible.

### 10. LINKED FIELD INTEGRATION
Under **Media switcher**, only **Image to iFrame** is recommended when using
Linked Fields. Other options (like Lightboxes or Image linked to content) will
be ignored because they conflict with the `<a>` tag output of the Linked Field
module. **Image to iFrame** allows the video to remain playable while the image
remains linked.

Alternatively leave `Media switcher` empty, if no videos are mixed with images.
With `Image to iFrame`, the good thing is video will be still playable, and the
image be linked as required. Best of Both Worlds for real.

### 11. VIEWS GOTCHAS
* If using Blazy formatter as a standalone Views output and encountering issues,
  check **Use field template** under **Style settings**.
* Conversely, uncheck **Use field template** when Blazy is embedded inside
  another module (like Slick) to ensure the renderable array is passed correctly.
* When in doubt, toggle this setting and check the output.

### 12. NATIVE GRID MASONRY
#### One-dimensional vs. two-dimensional native grids?
Under **Display style**, choose **Native Grid**.

- **One-dimensional (Masonry)**: Input a single number (e.g., 2, 3, 4) in the
**Grid large** option.
- **Two-dimensional**: Input space-delimited `WIDTHxHEIGHT` pairs (e.g.,
`4x4 4x3 2x2`).

#### The native grid masonry has incorrect bottom gaps?
This is often an optical illusion caused by inner divs not filling 100%
height.

- **Solutions**: Add a background color to `.grid__content` to see the actual
  even gaps.
* Enable **CSS background** in the Blazy formatter. In Views, set field wrappers
  to "None" so the background fills the grid cell.
* Manually set the height of `.grid__content` inner DIVs to 100% via CSS.
* For complex layouts, consider **GridStack**, which handles these calculations
  automatically.

### 13. IMAGES DO NOT LOAD
If images fail to load inside hidden tabs or containers:

* Enable **Load invisible** at `/admin/config/media/blazy`. Only valid for old
  bLazy library. IO and Native lazy loading don't have this issue.
* For Responsive Images in lightboxes, do not use the `-empty image-` fallback.
  Edit styles at `/admin/config/media/responsive-image-style`. Note that
  lightbox full-size images are handled by the lightbox library, not Blazy's
  lazyloading.

### 14. OLIVERO SUB-THEMES
Carousels (Splide/Slick) in Views may conflict with Olivero's grid rules,
causing "gargantuan" dimensions. This issue has been taken care of automatically
since 2.17, however it is still mentioned to avoid similar issues with other
themes as well. Choose one of these:

* Disable `grid-template-rows: max-content;` on ancestor selectors.
This rule often forces slides to span their full width/height regardless of the
viewport.
* Adding the CSS class `view--blazy` under **Views > Advanced > CSS Class** may
also resolve container boundary issues.

### 15. BROKEN MODULES
Refer to the [Update SOP](#updating) for detailed procedures.

---
<a href="#top">Back to Top &uarr;</a>
---
