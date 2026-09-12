
***
## <a name="aspect-ratio"> </a>ASPECT RATIO & LAYOUT STABILITY
The **Aspect Ratio** is the primary defense against
**Cumulative Layout Shift (CLS)**, particularly when using JavaScript-based lazy
loading or responsive iframes. By reserving the correct space before media, we
prevent container collapse, white space below media, distorted elements, and
unexpected page jumps:
  * —essential for both user experience and SEO rankings.
  * —a critical requirement for cross-browser compatibility and legacy support.

While modern browsers can often derive this from inline `width` and `height`
attributes (Native Lazy Loading), **Fluid Logic** and JavaScript-based solutions
offer superior **adaptive intelligence**: they can delegate the task to the
browser's native engine when available, while providing a robust fallback for
older environments.

### Core Implementation Logic
The system now utilizes a **Modern-First** strategy (v3.0.17+) with intelligent
fallbacks to ensure cross-browser stability:

- **Modern CSS `aspect-ratio`**:

    If **Use modern CSS aspect-ratio** is enabled in Blazy UI, the system
    utilizes native CSS properties, significantly cleaner for themers and less
    hacky.

- **Adaptive Intelligence**:

    JavaScript-based lazy loading detects browser support; it delegates tasks to
    the native engine when available but maintains the **padding-bottom hack**
    for legacy environments.

- **Fluid Logic**:

    The system prioritizes the calculated ratio. If a matching **fixed** ratio
    (e.g., 16:9) is found, it is applied via CSS. If no match is found, it
    defaults to the padding hack.

- **Responsive Images**:

    Currently, responsive images (including Picture element) continue to utilize
    padding hacks due to the complexity of varied source sizes.

---

### Configuration & Extension

#### 1. Enabling Modern Support
* Navigate to **Blazy UI > Use modern CSS aspect-ratio**.
* This replaces legacy padding hacks with native CSS.
* **Customization**:

   Override `css/components/blazy.ratio.css` and
   `css/components/blazy.ratio-modern.css` for custom styling.

#### 2. Defining Custom Ratios
To extend the default ratios (1:1, 4:3, 16:9, etc.), use
`hook_blazy_settings_alter`:

```php
// Appending custom ratios (7:8, 6:5) to the existing set.
$blazies->set('css.ratio', ['7:8', '6:5'], TRUE);
```
The `TRUE` flag ensures to append, not nullify, the existing ones:

  ``['1:1', '3:2', '4:3', '8:5', '9:16', '16:9', '16:10', '21:9']``

See [**blazy.api.php**](https://git.drupalcode.org/project/blazy/blob/3.0.x/blazy.api.php)
for the available `hook_alter`.

#### 3. Image Styles
For the best results, create Image Styles that match your defined aspect ratios
and select the **Fluid** option in the formatter. The system will automatically
pick the matching ratio for pure CSS execution.

Create image styles that stick to the default or custom aspect ratios:
  * [/admin/config/media/image-styles](/admin/config/media/image-styles)
  * [Aspect ratio template](#aspect-ratio-template)

**Strategy: When to Use vs. Disable**

While Aspect Ratio is your "first best bet" for fixing display issues, it must
be applied strategically based on your layout architecture.

| Scenario | Recommendation | Logic
| --- | --- | --- |
| **General Lazy-Loading or Mixed-media** | **Enable** | Prevents CLS; makes iframes responsive without jQuery dependencies. |
| **Responsive iframes** | **Enable** | Achieves responsiveness without extra JS. |
| **Gapless Grids (Native Grid or GridStack)** | **Disable** | Grid geometry must dictate the size, not individual image ratios.|
| **Art Direction (Picture)** | **Fixed / Legacy** | Use fixed ratios (4:3, etc.) for consistency;  or **Fluid** for varied shapes. |
| **Cropping is Acceptable** | **CSS Background** | Provides a seamless , "filled" container look, though edges may be cropped. |

####  Technical Troubleshooting
- **Collapsed Containers or Empty White Space:**

    Ensure an Aspect Ratio is defined.

- **Grid Distortions:**

    If your grid appears broken, the image aspect ratio is likely conflicting
    with the grid's own constraints. Disable the ratio for these specific
    elements.

- **Missing Custom Ratios:**

    If a custom ratio isn't appearing, ensure you have cleared the cache after
  your procedural function change and that your theme contains the matching CSS
  rule following the convention in `blazy.ratio.css`.

**References:**

* [Browser Support (CanIUse)](https://caniuse.com/?search=aspect-ratio)
* [MDN Web Docs: aspect-ratio](https://developer.mozilla.org/en-US/docs/Web/CSS/aspect-ratio)

---
## <a name="aspect-ratio-template"> </a>ASPECT RATIO TEMPLATE
### Tools to check aspect ratio:
https://size43.com/jqueryVideoTool.html

### Common resolutions:
https://en.wikipedia.org/wiki/List_of_common_resolutions

### Aspect ratio 4:3
* 420x236
* 640x480
* 800x600
* 1024x768
* 1152x864
* 1280x960
* 1400x1050
* 1600x1200
* 2048x1536
* 3200x2400
* 4000x3000
* 6400x4800

### Aspect ratio 16:9
* 640x360
* 853x480
* 960x540
* 1024x576
* 1280x720
* 1366x768
* 1600x900
* 1920x1080
* 2048x1152
* 2560x1440
* 2880x1620
* 3840x2160
* 4096x2304
* 7680x4320

### Aspect ratio 16:10
* 1440x900
* 1680x1050
* 1920x1200
* 2560x1600
* 3840x2400
* 7680x4800

---
<a href="#top">Back to Top &uarr;</a>
---
