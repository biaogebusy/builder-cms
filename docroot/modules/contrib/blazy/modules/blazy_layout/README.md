## <a name="top"> </a>TABLE OF CONTENTS

 * [Introduction](#introduction)
 * [Requirements](#requirements)
 * [Installation](#installation)
 * [Configuration](#configuration)
   * [Mastering the Dynamic Layout](#mastering-layout)
   * [Media Background](#media-background)
   * [Linkable and Mixed Media](#mixed-media)
   * [Semantic Layout](#semantic-layout)
   * [Core Expectations](#core-expectations)
 * [Known Issues/Limitations](#issues)
 * [Maintainers](#maintainers)


***
## <a name="introduction"> </a>BLAZY LAYOUT: THE ARCHITECT’S CHOICE

**Blazy Layout** provides a single layout template with dynamic regions for
**Layout Builder** (LB). This isn't just another layout handler; it is a
sophisticated re-imagining of the established **Blazy Grid** system. By
transforming dynamic grid options into responsive layout variants, it eliminates
the redundant need for dozens of individual templates while providing a
**CLS-zero** strategy utilizing its simple configurations.

We have mastered the art of the "One"—a single, noble template capable of
infinite expression.

> In an era of over-engineering, **Blazy Layout** honors the original intent of
> the web: _clean, fast, and infinitely adaptable_.

***
## <a name="requirements"> </a>REQUIREMENTS
* Core Layout Discovery.
* [Media library form element](https://www.drupal.org/project/media_library_form_element) to have builtin Media library integration
  (Optional).

***
## <a name="installation"> </a>INSTALLATION
* [Installing Drupal Modules](https://drupal.org/node/1897420).

***
## <a name="configuration"> </a>CONFIGURATION

- **Integration:**

    Navigate to your **Layout Builder** default configuration
    (`/admin/structure/types/manage/page/display/default/layout`) or any
    administrative variant (`/node/123/layout`).

- **Selection:**

    When adding a new section, choose **Blazy dynamic layout**.

- **Adjustment:**

    Read the provided descriptions and adjust any relevant options accordingly.

***
### <a name="mastering-layout"> </a>MASTERING THE DYNAMIC LAYOUT
The **dynamic** part refers to **configurable regions**, not the actual
**layout** itself which is basically just a single **static** template under the
hood. Within this framework, they are known as **layout variants** similar to
**separated block layout variants**, and so on; only in the **Blazy Layout**,
they are **unified and modifiable at the same time** without touching it, unlike
regular **multiple layout templates**.

Whether you require Flexbox, CSS3 Columns, or Native Grid—including their
Masonry counterparts—**Blazy Layout** delivers a "one for all" solution. It is hyper-efficient, leveraging modern browser capabilities with a remarkably small
CSS footprint and lean markups to produce limitless, high-performance results.

While Grid impresses a box, and Column a pillar depending on the selected layout
engine, they refer to a region or sub-section in layout terminology.

- **Layout Definition:**

  + Select **Region count** to define the allowed amount of regions.
  + Utilize the three core Grid options to define your region structure.
    Whether you need a simple inline flow (one-dimensional), a complex 2D grid
    (two-dimensional), or a stacked mobile-first design, a single baseline
    handles it all.

- **Semantic Layout:**

    Enable the **Semantic Layout** when a Hero or any section below it contains
    related lists. Use it only if appropriate:

    * Article summaries or blog post listings
    * Hero structural info to navigate features or services
    * [Learn more](#semantic-layout)

- **Hero Friendly:**

  Defining a region as a Hero allows you to make **Layout Builder** as the
  primary layout manager beyond regular hard-coded regions and traditional
  block placement in templates. Two Hero builders:

  1. Place a dynamic slider or static media Views block and treat it as a
     [Hero](/admin/help/blazy_ui#heroes).

     **Benefits:** single and multi-value field are supported.

  2. Assign a Hero delta directly into **Hero region** option, and upload or
     re-use a Hero media ((Responsive) Image, Picture, Media player, Video,
     Audio).

     **Benefits:** a single, prominent and optimized Hero without Views overhead

- **Universal Application:**

  Effortlessly apply CSS backgrounds, solid colors, or transparent washes to the
  entire layout or specific sub-sections to create a striking visual
  foundation.

- **Atmospheric Overlays & Contrast:**

  For truly "eye-catching" depth, utilize the **RGBA Overlay** option. This
  allows you to stack semi-transparent color filters over your images, working
  in tandem with **Headline and Text color** options to ensure perfect contrast
  and brand consistency. A layout is only as good as its legibility. By
  controlling the overlay and the text color within a single interface, you
  aren't just building a page—you are composing a masterpiece of readability.

- **Instant Feedback:**

  Design at the speed of thought. A **Live Preview** is integrated directly into
  the configuration UI, providing immediate visual confirmation as you fine-tune
  your colors, backgrounds, and typography. Be sure the section, blocks and
  probably background images are added first to the page, otherwise nothing to
  see.

- **Perspective:**

  Engage the **Edge-to-Edge** option to allow your layout to span the full
  horizontal width of the viewport.

- **Composition:**

    1. **Overlayed blocks**:

       Place regular content blocks over your defined layout regions.

    2. **CSS backgrounds**:

       To impress depth, use the background colors or images:

       - Select **Styles > Media** and check **Use CSS background** option.
       - Alternatively, add solid **Styles > Colors** without images.
       - Adjust Text, Heading and Link colors to ensure perfect contrast.

    3. **Nested Grids**:

       - Add a Blazy formatter and assign **Grid** options within the formatter.
       - Alternatively, add multiple columns within 100% or 12 column constraint
         to impress nested grids without additional wrappers.

- **Custom CSS (advanced):**

  The CSS is injected directly into the page `<head>` and applied at
  render time.

  + Provide a scoped selector at [Blazy UI](/admin/config/media/blazy)
    after enabling **Allow custom inline CSS for Blazy layout**
  + Avoid targeting global elements (`html`, `body`)
  + External imports and remote URLs are ignored
  + Direct descendant (`>`) is escaped
  + Leave empty to avoid unnecessary layout instability

   Incorrect CSS may break layout rendering or affect unrelated components. This
   option is intended primarily for CSS-savvy site builders to mitigate
   [**CLS issues**](/admin/help/blazy_ui#cls) when the provided `min-height`
   utility classes (**xxs xs sm md lg xl xxl x2l x3l x4l x5l**) are
   insufficient.

- **The Result:**

  Experience the power of a single, refined layout engine that offers
  unlimited possibilities with unparalleled efficiency.

***
### <a name="media-background"> </a>MEDIA BACKGROUND
Three ways to add Media (image, local|remote video, audio) as CSS backgrounds:

1. **With builtin Media library (Recommended):**

   * Install [Media library form element](https://www.drupal.org/project/media_library_form_element).

   * This is alternative to core **Layout Builder Expose All Field Blocks**
     which was deprecated, also a more efficient solution than the last two
     options below to avoid creating useless/ unused Media fields. This
     background is available for all regions, including the main layout. If
     provided, be sure to **NOT** enable **Use CSS background** option for
     other Blazy formatters when overlayed above the same region to avoid
     multiple and conflicting backgrounds.

   * Select image/media at **Layout Builder** page under:

     **Blazy layout > [Global|Region] > Settings > Styles > Media**

   * Repeat for any other regions as needed.

   * **Benefits**: No fields or blocks are created, just re-use, or create,
     media. This is the most efficient solution for simple backgrounds.

2. **With active entity/Content type:**

   * Add a _multi-value_ Media/ Image field in the active entity/Content type.
   * Upload some images/media (matching the amount of regions which should
     have backgrounds) into the field. If the region total is 10, and you need
     3 backgrounds, just upload 3 items, not 10.
   * At LB: **Add block > Choose a block > Content fields**.
   * Choose **Blazy formatter**, and enable **Use CSS background** option.
   * Use **By delta** option starting from 0 to map field items to any regions
     rather than creating multiple fields for multiple regions.
   * Repeat for any region which may require backgrounds. Adjust **By delta**,
     no need to match one to one delta from field items to regions.
   * FYI, this offers more options, but might be overwhelmed for background
     purposes.

3. **With Block content type**:

   * [/admin/structure/block-content](/admin/structure/block-content), add
      a dedicated background type, says **Background**.

   * [/admin/structure/block-content/manage/background/fields](/admin/structure/block-content/manage/background/fields),
     add a Media field says **Media**, choose Image, Video and Remote video.
     Multi-value is better for carousel re-use.

   * [/admin/structure/block-content/manage/background/display](/admin/structure/block-content/manage/background/display), choose Blazy formatter, and enable
      **Use CSS background**.

   * Create as many as blocks for background: [/block/add/background](/block/add/background), or on the fly using LB **Create content block**.

   * At LB, either way:

     * **Add block > Create content block**.
     * **Add block > Choose a block > Content block**


### <a name="mixed-media"> </a>Linkable or Mixed Media

Please refer to [Mixed Media](/admin/help/blazy_ui#mixed-media) section.

***
## <a name="semantic-layout"> </a>SEMANTIC LAYOUT

Enabling the **Semantic Layout** option replaces generic `<div>` wrappers with a semantic, structural list (`<ul>`)
**only where the content is inherently list-like**. This is especially relevant
for Heroes that include not only a prominent visual, but also structured
supporting information such as features, highlights, or services. Because the
layout remains scoped to that section, it does not apply list semantics to the
entire page. In this context, a Hero is not purely presentational; it can also
convey structure and meaning.

Using a `<ul>` is generally appropriate when content represents a collection of
related items (for example, features, services, or summaries). Compared to a
generic `<div>`, a list element communicates intent and relationships more
explicitly, benefiting both users and user agents.

### Hero Structural Information
Supporting attributes or secondary signals that accompany a primary Hero
message.

A hero may participate in a semantic list **only when it is the lead item of
that list**—that is, when it represents the first entry in a sequence of
related information. When the hero serves a distinct narrative, branding, or
promotional role, it must remain structurally separate.

**Rules of thumb:**

- **Common workflow:**

   * When **Semantic layout** is enabled, the main background region will be
     placed before the list to maintain the semantic order.
   * Fill in **0** in **Hero** textfield for builtin Hero Media.
   * Empty **Hero** textfield if using Blazy or slider Media formatters.
   * When **Semantic layout** is disabled, **Hero** textfield can be any number
     when using Native Grid with complex composition. The largest region is the
     Hero.
   * When placing a Hero, disable image-based main layout backgrounds to avoid
     competing large media and to ensure the hero remains the first meaningful
     list item, rather than being preceded by background media.
   * A solid-color background may still be applied via **Styles > Colors**
     option, as it does not participate in media loading or affect semantic
     order.
   * Turn on **Remove main Background region** option if not using background
     color, or background is not utilized, to shave off empty markups.
   * Refer to [Building Heroes](/admin/help/blazy_ui#heroes).

- **Default:**

    It requires two sections to function.

    * Turn off **Semantic layout**; place the hero in its own section.
    * Turn on **Semantic layout**; place features listed containing `<ul>` in a
      separate section below the hero.

- **Allowed:**

    It requires one section to function.

    * Turn on **Semantic layout**.
    * Include the hero as the first `<li>` *only* when it follows the
      same informational flow as the list items.
    * Follow **Common workflow** above.

- **Avoid:**

    Placing the hero inside a list purely for layout or grid convenience.

### Why `<ul>` Can Be Appropriate

- **Semantic Structure**

  A `<ul>` indicates that its children form a group of related items. This
  structural signal is not conveyed by `<div>`, which is intentionally generic.
  Applying semantic structure can improve document clarity, particularly in
  layouts where visual grouping alone may not be sufficient.

- **Accessibility & Navigability**

  Assistive technologies can announce lists, their length, and item positions
  (for example, “list of 4 items”). This enables more predictable navigation
  for keyboard and screen-reader users, especially in Hero sections that present
  multiple actions or highlights.

- **Content Interpretation & SEO**

  Search engines use semantic HTML to better interpret content roles and
  relationships. While semantic markup alone does not guarantee ranking
  improvements, it can support clearer content indexing when combined with
  headings, landmarks, and meaningful text.

- **Resilience Across Render Paths**

  Semantic markup remains meaningful even when styling or scripting is delayed
  or unavailable, such as during streaming, partial hydration, or progressive
  rendering. A list retains its structure independent of presentation.

### Relevance to Core Web Vitals (CWV)

Semantic Layout does not directly optimize Core Web Vitals, but it supports
structural patterns that can make CWV-related work more predictable:

- **CLS (Cumulative Layout Shift)**

  List-based structures encourage consistent item flow and sizing. When list
  items use stable dimensions or placeholders, layout shifts during hydration
  or media loading are easier to anticipate and manage.

- **LCP (Largest Contentful Paint)**

  Separating primary Hero content (such as headings or media) from supporting
  lists can help reduce unnecessary reflows that may delay LCP stabilization.

- **INP (Interaction to Next Paint)**

  Clear, shallow DOM structures can make interaction-related layout behavior
  easier to reason about and reduce reflow or repaint costs during interactions,
  particularly when Heroes include interactive elements.

In short, semantic layout does not replace performance optimization, but it can
reduce the likelihood of structural choices that complicate it.

### When to Use Semantic Layout

Use it when the content represents a **collection of related items**, rather
than a purely decorative grouping.

**Common examples include:**

- **Navigation Menus**

  Each link functions as a list item (`<li>`), making `<ul>` a natural
  structural choice.

- **Feature or Service Lists**

  Product highlights, service offerings, or capability summaries.

- **Article or Content Summaries**

  Blog listings, cards, or teaser collections.

- **Hero Structural Information**

  A hero may participate in a semantic list **only when it is the lead item of
  that list**—that is, when it represents the first entry in a sequence of
  related information. When the hero serves a distinct narrative, branding, or
  promotional role, it must remain structurally separate.

### Considerations When Using `<div>` for Structure

- **Generic Semantics**

  `<div>` does not convey meaning beyond grouping. When used exclusively for
  structural layout, relationships between elements may need to be inferred
  visually or through additional attributes.

- **Accessibility Considerations**

  A sequence of `<div>` elements does not expose list structure, item count,
  or grouping semantics to assistive technologies by default, which may affect
  navigability for some users.

- **Maintenance Over Time**

  As layouts evolve, `<div>`-only structures may require additional ARIA roles
  or refactoring to clarify intent that semantic elements would otherwise
  provide.

### Practical Guideline

Use the **most appropriate semantic element for the content being presented**:

- Use `<ul>` when items are meaningfully related.
- Use `<div>` for styling or layout when no more specific semantic element
  applies.

Semantic Layout focuses on clarity rather than enforcement: it helps make
structure explicit, accessibility more natural, and performance considerations
easier to reason about in progressively rendered pages.

***

##  <a name="core-expectations"> </a>Is a Single Layout Template with Dynamic Regions Breaking Core Expectations?

**Short answer: No.**

Blazy Layout uses a single Twig template and dynamically adjusts region
definitions at runtime. This approach does not alter Drupal core behavior,
violate layout plugin contracts or move around regions on the fly.

### What This Approach Does

- Clones the discovered `LayoutDefinition` at runtime.
- Modifies region definitions on the cloned instance only.
- Modifies attributes of the rigid layout structure impressing different region
  structure or composition while under the hood it is intact once defined.
- Leaves the original plugin discovery definition untouched.
- Uses standard render arrays and Layout Builder pipelines.
- Preserves compatibility with Drupal’s caching and rendering systems.

### What This Approach Does *Not* Do

- It does **not** alter plugin discovery.
- It does **not** mutate cached core definitions.
- It does **not** override or bypass Layout Builder APIs.
- It does **not** change expected render array structures.
- It does **not** introduce global state into Drupal core.
- It does **not** move around regions on the fly. The only time it "moves" a
  region is when **Semantic Layout** is enabled for **Hero**. It conditionally
  moves **Background** server-side out of the list to comply with
  **Core Web Vitals** and semantic markup expectations, similar to how a
  checkbox is placed either before or after the label. No DOM mutation is
  invited.

### What This Layout Differs from Regular Layout Templates

- Regular template files hard-code classes and attributes for each template
  file with limited set of regions, Blazy Layout makes them configurable
  server-side and feed them into a single template file containing dynamic
  amount of regions conditionally removing the need for hard-coded attributes
  while maintaining Twig expectations.

- The dynamic amount of regions is similar to how the amount of blocks may
  occupy a region as defined at `/admin/structure/block`, or a Media field may
  have different amount images, or multiple sections of layouts are added and
  removed at Layout Builder pages.

- Since the very beginning, Core has done what Blazy Layout does, except for
  a few hard-coded template files such as layout templates.

### Why This Pattern Is Used

Instead of maintaining multiple nearly identical layout definitions and template
files, dynamic regions allow:

- Reduced duplication
- Easier long-term maintenance
- A single source of truth for markup structure
- Predictable runtime behavior
- Making the best out of what Core already supports from the start

This follows a common prototype-style pattern:

`clone → adjust → render`

### Compatibility Notes

The layout:

- Returns standard render arrays.
- Maintains expected `#layout`, `#attributes`, and region structures.
- Operates within Drupal’s plugin and caching systems.

As long as layout instances remain isolated and do not share mutable state, this approach behaves consistently with core expectations.

***

## <a name="issues"> </a>KNOWN ISSUES/ LIMITATIONS
* This module does not provide a CSS framework integration aka framework
  agnostic. Instead using the existing grid solutions with few tweaks to support
  regular floating elements commonly seen at one-dimensional layouts. However,
  any CSS framework cosmetic rules can be utilized via the provided **Classes**
  options.
* Background images are not draggable, simply replace and reuse them.

***
## <a name="maintainers"> </a>AUTHOR/MAINTAINER/CREDITS
* [Gaus Surahman](https://www.drupal.org/user/159062)
* CHANGELOG.txt for helpful souls with their patches, suggestions and reports.

***
## READ MORE
See the project page on drupal.org for more updated info:

[Blazy module](https://drupal.org/project/blazy)
