
***
## <a name="optimization"> </a>STRATEGIC OPTIMIZATION CHECKLIST
Proper configuration ensures the module works for you, not against you. Use this
checklist to audit your implementation for maximum performance and technical
integrity.

---

### 1. Essential Environment Hygiene

- **Production Clean-up:**
    Always **uninstall Blazy UI** in production; configuration belongs in code
    or exported features. A clean production environment is a performant one.

- **Global Performance:**
    Ensure Drupal’s core **CSS/JS aggregation** and caching are active at
    `/admin/config/development/performance`. Without this, software-level
    optimizations are moot.

### <a name="media-architecture"> </a>2. Media Architecture & Privacy

- **Lazyload HTML:**

    For third-party embeds (Instagram, Pinterest, etc.), enable
    **Lazyload HTML** in the Blazy UI. Offloading heavy third-party scripts
    prevents main-thread blocking and preserves host page performance.

- **Lazyload IFRAME:**

    Using the **Media Switcher** option with a static image preview is the
    primary defense against heavy third-party scripts. By intercepting iframe
    requests until user interaction, the main thread remains responsive during
    initial page load.

    1. **Image to IFRAME (Two-Click Loader):**

       The gold standard for **GDPR/ePrivacy compliance**. This workflow blocks
       third-party tracking until "Active Opt-in" user engagement.

    2. **Image to Lightbox:**

       Offloads scripts into a **conceptual background thread**—a destroyable,
       isolated execution path—protecting the host page’s performance.

    3. **Image Linked to Content|by Link field:**

       The most aggressive strategy. It **completely removes** external scripts
       from the current page, delegating them to dedicated URLs.

### 3. Precision Engineering for Media

- **Prevent Layout Shift (CLS):**

    The **Aspect Ratio** is our primary defense against
    **Cumulative Layout Shift (CLS)**. By reserving space before media loads, we
    prevent container collapse and page jumps.

    - **Strategy:**

       Use image styles with a **"crop"** effect, whenever possible. Select the
       **Aspect ratio** in the formatter UI and enable
       **Modern CSS aspect-ratio** in Blazy settings.

    - **Fluid Logic:**

      While native lazy loading handles basic shifts, Blazy’s
      **adaptive intelligence** provides robust fallbacks for legacy
      environments and fluid containers.

     - **Issues beyond Blazy:**

        Blazy is just one inhabitant of a complex page ecosystem. Even when
        Blazy has been optimized to eliminate shifts for media, many variables
        affect CLS:

        * Ads
        * BigPipe
        * Third-party widgets or HTML
        * etc.

        The most authoritative way to prevent layout shift is to reserve space
        for a container, either by defining **predictive/fixed dimensions** or,
        at a minimum, a `min-height`.

- **Loading Priority (LCP):**

    Use **Preload** and **Loading Priority** options for "above-the-fold" assets
    to optimize **Largest Contentful Paint (LCP)**. Treat hero media as a
    priority, not an afterthought.

- **Responsive Standards:**

    Prioritize **Core Responsive Image** whenever storage permits. If storage is
    a constraint, utilize modern formats like **WebP** or **AVIF** to maintain
    visual fidelity at a fraction of the weight along with a versatile design.

### 4. Interactive Scalability

- **Scalability for Galleries:**

  For massive datasets, favor **Blazy Grid + Lightbox** (Colorbox, PhotoSwipe,
  etc.). This is objectively more efficient than a slider-only implementation
  for static viewing.

- **The DOM Diet: Eradicating Divitis:**

  To achieve a high-performance render, we must treat the DOM tree with the
  precision of a minimalist. Excessive nesting is technical debt. Follow these
  protocols to ensure lean, semantic markup:

  1. Field & View Configuration

     - **Remove Wrapper Classes:**

       Always enable **"Remove field/view wrapper CSS classes."**. It is only
       useful for custom DOM diet.

     - **Uncheck "Use theme field":**

       If provided, keep this disabled unless a specific architectural
       requirement dictates otherwise. This prevents the system from injecting
       default, heavy-handed wrappers.

  2. Template-Level Purging

     We embrace the **@mortendk** DOM diet. If a `div` doesn't have a semantic
     or structural purpose, it is bloat.

     - **Implementation:**

       Use specialized Twig templates—`block--no-wrapper.html.twig` or
       `views--no-wrapper.html.twig`—to strip the container to its core
       components selectively, whenever possible. And use the
       **field/view wrapper CSS classes** for more contextual styling.

     - **Result:**

       Contextual styling becomes cleaner, inheritance is more predictable,
       and the browser spends less time traversing the tree.

> _Every line of HTML you don't write is a line you don't have to debug.
> Shave the bloat._

### 5. Automated Intelligence & Modern Standards

- **Native Lazyload:**

    Favor native browser lazy-loading to reduce main-thread execution by
    enabling the **No JavaScript + polyfills**, when targeting modern sites.

- **Noscript Compatibility:**

    While `<noscript>` provides a fallback, it adds HTML weight. If your target
    audience is modern sites or performance-critical, disable this fallback to
    shave off every possible byte.

- **Blur Effect:**

    While **Blur** effect provides attractive animations and transitions, it
    adds HTML weight. If your target audience is performance-critical, any will
    do:

    - Disable **Blur**, and use the default **Blue loading indicator**
    - Leverage [Animate CSS](#animate-css)
    - Enable **Use client-side blur** with **Store blur in localStorage**
    - Leverage `hook_blazy_settings_alter` to switch **Blue**, **Blur**, and
      **Animate CSS** animation effects conditionally
    - If using **Blur**, ensure to fill in **Blur min-width** to prevent 
      thumbnail/preview or small images from adding more unnecessary weight.

    They signifantly reduce the page weight by shaving off **Blur** large bytes.

- **Fine-Tuning:**

    Audit your settings at [Blazy UI](/admin/config/media/blazy), submodules'
    administrative UI pages, and Media formatters. The administrative UI is your
    cockpit for precision tuning.

---
> **Blazy does not optimize media performance by magic.
> It prevents media performance failure by design.**
---

## <a name="cls"> </a>The Big Picture: CLS Prevention

While **Blazy** is optimized to eliminate shifts for media, it is only one
inhabitant of a complex page ecosystem. [Total layout stability](#layouts)
requires a holistic approach to address variables that occur before or during
the "DOM surgery" of a page load.

For components outside the Blazy ecosystem, the approach generally involves
assigning a **min-height** or **predictive/fixed dimensions** to containers that
may collapse. This follows the same principle used to prevent layout shifts in
images when an aspect ratio cannot be applied, as outlined in the form items and
[TROUBLESHOOTING](#troubleshooting) section.

### ⚠️ Variables Challenging Visual Stability
Understanding the variables is vital for more targeted solutions.

* **Ads & Dynamic Bidding:**

    These often carry unpredictable dimensions. Intercepting them is complex,
    and by the time they resolve, the layout has already shifted.

* **Core BigPipe:**

    Drupal’s streaming delivery removes HTML from the initial response and
    injects it dynamically. With up to 6 known sequential replacements, the page
    can "stutter" or jump repeatedly if placeholders aren't sized correctly.

* **Third-party HTML & iFrames:**

    Widgets (social feeds, maps) have unpredictable heights. Waiting for
    `iframe.onload` is "too late"—the browser has already recorded the shift.

---

### 🛡️ Strategic Solutions

To maintain a perfect **Cumulative Layout Shift (CLS)** score, we must move from
**reactive** loading to **predictive** spacing. While Blazy has an immediate
solution via [**Aspect ratio**](#aspect-ratio) option and
[**Blazy Layout**](#layouts) with **CSS classes** and **Custom CSS** textarea,
the following demands your own fixes.

#### 1. The "Skeleton" Container (Fixed Dimensions)
The most authoritative way to prevent shift is to reserve space.

* **Known Dimensions:**

    If a block’s size is constant (e.g., a 300x250 ad), use a container with
    these **fixed dimensions**. Ignoring these constraints to “catch” a click
    may result in invalid engagement and directly degrades CLS.

* **Unknown Dimensions:**

    At a minimum, apply a `min-height` to BigPipe placeholders. It is visually
    superior to have a small gap of whitespace than to have the entire page
    content "leap" 500px downward. Adjust 500px to your own layout, or use
    [Blazy layout](#layouts) which is designed to manage layout stability
    problems using UI rather than hacking theme CSS for every minor content
    variances like so:
    ```css
    /* Adjust collapsed container selector. */
    .region-content {
      min-height: 500px;
    }
    ```

#### 2. Aspect Ratio Boxes
For fluid layouts, the CSS `aspect-ratio` property is your strongest ally. It
allows the browser to calculate the container's footprint even when the content
(like a Blazy image or BigPipe block) is still empty.

```css
.container {
  aspect-ratio: 16 / 9;
}
```

#### 3. Slot Capping for Third-Parties
For ads that vary in size, implement **Slot Capping**. Reserve the height of the
largest possible ad. This ensures that regardless of which creative wins the
bid, the content below it remains anchored.

#### <a name="big-bees"> </a>4. The BigPipe + Blazy Bridge

BigPipe delivers significant improvements to TTFB and FCP by streaming page
fragments progressively. However, because it replaces placeholders in the live
DOM, it can introduce CLS unless [layout stability](#layouts) is explicitly
designed into those replacement regions, as [outlined above](#cls).

When evaluating CLS in relation to TTFB, temporarily enabling or disabling
BigPipe can help isolate the rendering strategy. Since BigPipe operates at a
broader rendering scope through fragment streaming and directly influences TTFB,
while Blazy focuses on a narrower media-related scope, CLS should be analyzed in
terms of placeholder replacement and layout stability across the rendering
pipeline.

A clear separation of responsibilities enables more accurate, context-driven
decisions based on the target audience: optimizing BigPipe for authenticated
users with highly dynamic, PHP-driven interactions, while relying on stable,
cached HTML for anonymous traffic. Within this model, Blazy remains a focused,
media-level solution—addressing only media delivery and media-induced layout
shifts, not structural changes introduced by the rendering pipeline itself.

During BigPipe’s progressive rendering phase, Blazy functions as a coordinating
layer within its own ecosystem. By extending `core/once` and responding to the
completion of BigPipe’s placeholder replacements, Blazy ensures that media
assets are initialized only after their structural containers have stabilized
in the DOM.

With this approach, starting from Blazy 2.17, the vast majority of
Blazy–BigPipe interoperability issues have been resolved. The remaining edge
cases are primarily related to CSS reordering behavior (if still present, and
outside Blazy’s scope), as well as any currently unknown scenarios. Reproducible
reports for such cases are welcome to help ensure full compatibility with Core
BigPipe.

> **Documentation note**
>
> This section is out of scope for Blazy itself, as are a few related topics
elsewhere. It is included to provide a complete reference and to reduce
repetitive support requests that originate outside the Blazy ecosystem.
>
> Experienced site builders may skip foundational steps; however, issues should
be validated against this documentation before reporting. A brief review or
skim (≈15 minutes) often prevents misattribution and unnecessary
troubleshooting.
>
> Proper [isolation](#cls) and [configuration](#optimization) enable accurate
> evaluation.
> [Have your cake and eat it too](#cls).
