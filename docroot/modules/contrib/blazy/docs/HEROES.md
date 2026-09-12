
***
## <a name="heroes"> </a>HERO MEDIA: Mastering the Critical Path
Building Hero media (Backgrounds, Images, Iframes, or Video) requires strict
adherence to **Core Web Vitals** (CWV), specifically optimizing for
**Largest Contentful Paint** (LCP).

If a field is dedicated to Hero content, ensure the `unlazy` or `slider`
priority options are used only once per page, similar to how you treat a
**Page Title**. Both options extend and optimize the `loading="eager"`
attribute. While `loading="eager"` is a baseline requirement for LCP, it is
insufficient for complex multi-value fields like sliders, which can
inadvertently threaten performance if not handled with the surgical precision
Blazy provides.

**Note:** For simple, single-asset needs like multi-site logo blocks, core
formatters handle `loading="eager"` sufficiently. Blazy is for the complex
demands of the modern Hero.

#### Implementation Guide
1. **Loading Priority**

     Under the **Loading priority** option, select either `unlazy` or `slider`:

     - **unlazy (Static Heroes):**

       Optimized for single media assets. For multi-value fields, pair this with
       **Native Grid**, **Use CSS background**, and **Thumbnail style**. This
       creates a hierarchy where the first media is prominent, while subsequent
       media items follow a layout pattern.

        + **Grid Example (Tagore):**

          `12x6 4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2`

        + **Grid Example (Slider-style):**

          `12x6 3x2 3x2 3x2 3x2`

           (12x6 main image with 4-item thumbnail navigation).

        + *Pro Tip:*

           * Ensure **Thumbnail style** is defined for smaller assets to protect
             LCP.
           * Note that Slick or Splide can also display single Heroes, often
             removing the need for a separate static Hero field. That is why
             static Heroes are never fully elaborated until 3.0.17 as a PoC for
             those who have no interests in sliders.

     - **slider (Dynamic Heroes):**

        Designed for multi-value fields using Slick or Splide. This is intended
        for true sliders (*one visible slide at a time*), not carousels
        (*multiple visible slides*). For any slider positioned below the fold,
        use `lazy` or `defer` instead.

2. **Preloading**

     Vital for Heroes, especially for "late-discovered" assets like CSS
     backgrounds that the browser cannot see until the CSS or JS is parsed.

3. **Media Switcher**

     Enhance the visual impact of your Hero without compromising the underlying
     performance logic.

     - **Optimization Tip:**

        If your Hero uses an `<iframe>` video, displaying it directly can block
        the main thread and tank your LCP. Instead, use
        **Media switcher > Image to Iframe**. This replaces the heavy iframe
        with a static preview image on initial load—optimizing the LCP while
        simultaneously ensuring **GDPR compliance** by withholding third-party
        scripts until user interaction.

4. **Pre-emptive Space Allocation:**

    Blazy handles aspect ratios to prevent layout shifts (CLS), ensuring that
    the Hero container exists in the DOM at the correct proportions before the
    media even begins to download. Be sure to fill in the **Aspect ratio**
    option as required.

5. **Semantic Heroes:**

     [**Blazy Layout**](#layouts) exists to build more complex Hero contents or
     blocks composition with decent LCP (Heroes) and CLS (layout stability)
     management. Enabling **Semantic Layout** option tells browsers and
     assistive technologies (like screen readers) that its contents form a
     list of related feature or service items; it helps improve SEO.

#### The Hero Logic: Performance by Design
The following ensures Heroes meet LCP and CLS requirements without manual
micro-management:

- **Selective Eagerness (Unlazy):**

    Blazy strategically exempts the first visible media from lazyloading (the
    **unlazy** state). In sliders, it can be the thirdth or sixth and so on, not
    always the first media depending on `start` or `initialSlide` options. This
    ensures the asset is immediately discoverable by the browser's
    **Preload Scanner** at the initial HTML parse. Even when using the
    JavaScript-delegated approach—where JS manages the native `loading`
    attribute for broader audience compatibility—this exemption bypasses the
    script execution bottleneck, ensuring the LCP candidate is fetched with zero
    delay.

- **Layout Stability (CLS):**

    Blazy has enforced space reservation for a decade. Transitioning from the
    historical `padding-bottom` hack to modern **CSS aspect-ratio**, Blazy
    ensures the DOM container is accurately sized before the media arrives,
    effectively neutralizing layout shifts.

- **Priority Orchestration:**

    For the primary media, CSS backgrounds receive a `fetchpriority="high"` via
    a `<link rel="preload">` in the document head. Inline assets receive the
    attribute on their respective HTML tags. This ensures that even "hidden" or
    late-discovered Hero assets are prioritized by the browser engine.
    Multi-breakpoint CSS backgrounds are achieved by combining
    **Responsive image** and **Use CSS background** options.

- **Adaptive Decoding:**

    Intelligent `decoding` is applied to hidden or thumbnail Heroes to optimize
    CPU cycles and main-thread availability.

**Reflection on Hero Architecture**

Native lazyloading alone is a [blunt instrument](#why-cwv); it cannot fully
satisfy the nuanced requirements of LCP. While recent releases have tightened
warnings and addressed oversights and edge-case overrides, the foundational
logic for Hero and Slider optimization has been a core pillar of Blazy since
the inception of **Core Web Vitals**.

---
<a href="#top">Back to Top &uarr;</a>
---
