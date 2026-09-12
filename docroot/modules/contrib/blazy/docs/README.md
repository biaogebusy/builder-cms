## <a name="top"> </a>TABLE OF CONTENTS

- [Introduction](#introduction)
- [Requirements](#requirements)
- [Configuration](#configuration)
- [Features](#features)
- [Recommended modules](#recommended-modules)
- [Architectural philosophy](#architecture)
- <a name="installation-upgrade"> </a>**Installation & Upgrade:**
  - [Installation, Upgrade & Update SOP](#installation)
  * [Version Compatibility](#first)
  - [Drupal 11 Compatibility](#d11-compat)
  - [Upgrade Path: 3.x → 4.x](#4x-upgrade)
- <a name="content-architecture"> </a>**Content displays:**
  - [Blazy Layout](#layouts)
  - [Building Heroes](#heroes)
  - [Multimedia galleries](#galleries)
  - [Lightboxes](#lightboxes)
  - [Aspect ratio](#aspect-ratio)
  - [Linkable and Mixed-media](#mixed-media)
  - [SVG & WEBP](#svg)
  - [Animate.css integration](#animate-css)
  - [theme_blazy()](#theme-blazy)
- [FAQ](#faq)
- [Troubleshooting](#troubleshooting)
- [Optimization](#optimization)
- [Project Manifesto](#manifesto)
- [Roadmap](#roadmap)
- [Contribution](#contribution)
- [Maintainers](#maintainers)
- [Notable changes](#changes)
- [Scope & Responsibilities](#scope-and-responsibilities)

---

## <a name="introduction"> </a>OPTIMIZED MEDIA DELIVERY

Blazy is a high-performance **media delivery** engine engineered to meet the
rigorous demands of modern **Core Web Vitals**. By intelligently leveraging the
**Intersection Observer API**, browser-native lazy loading, or the bLazy
library, it ensures assets are served only when necessary and in the optimal
format for the user's device.

Check out [Blazy project home](https://www.drupal.org/project/blazy) for most
updated info.

---

## <a name="first"> </a>VERSION COMPATIBILITY

Blazy and its sub-modules use a **tightly coupled architecture** to reduce code
duplication (DRY principle). To ensure system stability, you must maintain
version parity across all installed sub-modules:

- **Match Release Tiers:** Always pair `DEV` with `DEV`, or `Beta` with
  `Beta/RC`. Mixing stable releases with development branches will likely cause
  errors.
- **Branch Integrity:** Mismatched branches (e.g., `1.x` with `2.x`) are
  fundamentally incompatible unless explicitly stated.

**Note:** If you encounter unexpected errors, your first step should be ensuring
all Blazy-related modules match the latest release date or version number.
**Uninstallation is not needed**. While it might be true for manual FTP or GIT,
Composer with proper constraints will install dependency tree correctly
eliminating this issue in the first place.

### <a name="version-roadmap"> </a>VERSION ROADMAP

1. **Blazy 3.x (Drupal ≥ 9.4)**

   - "Just works" for convenience due to time and resource constraints.
   - Ecosystem stability with lingering baggages.
   - Recommended for old websites with heavy Blazy ecosystem customization.

2. **Blazy 4.x (Drupal ≥ 11.0)**

   - Optimized for migration.
   - Ecosystem consolidation with minimum BC and maximum FC as a bridge for D12.
   - This version intentionally exposes architectural seams to ease the
     transition to Blazy 5.x.
   - Recommended for new/old end-user websites with minimum Blazy ecosystem
     PHP customization.
   - Requires PHP ≥ 8.2.
   - Internals are strictly typed and converted into instance classes as needed.
   - Public APIs:

     - `blazy.api.php` remains BC-stable within the major, except for new
       integration methods beyond hook_alter itself.
     - Public classes may be tightened.

3. **Blazy 5.x (Drupal ≥ 12.0)**

   - Optimized for correctness.
   - A breaking change phase for D11 below without BC.
   - Recommended for new websites with zero Blazy ecosystem PHP customization or
     have passed Blazy 4.x.
   - Public APIs are tightened.
   - Full strict typing enforced to a great extent.

---

## <a name="requirements"> </a>REQUIREMENTS

1. Media
2. Filter
3. Layout Discovery, optional for Blazy Layout

---

## <a name="installation"> </a>INSTALLATION, UPGRADE & UPDATE SOP

1. [**Drupal Module Installation Manual**](https://drupal.org/node/1897420)

2. [**Composer**](#composer)

3. [**Upgrade path**](https://www.drupal.org/project/blazy#blazy-upgrade)

   For upgrading Blazy and its sub-modules from 1.x to 2.x or 3+.

4. [**Update SOP**](#updating)

   A definitive guide for update and upgrade troubleshootings, including WSOD.

---

## <a name="configuration"> </a>CONFIGURATION

Visit the following to configure and make use of Blazy:

1. [/admin/config/media/blazy](/admin/config/media/blazy)

   Enable Blazy UI sub-module first, otherwise regular **404|403**.
   Contains few global options. Blazy UI can be uninstalled at production later
   without problems.

2. Visit any entity types:

   - [Content types](/admin/structure/types)
   - [Block types](/admin/structure/block/block-content/types)
   - _/admin/structure/paragraphs_type_
   - etc.

   Use Blazy as a formatter under **Manage display** for the supported fields:
   Image, Media, Entity reference, Paragraphs, or even Text.

3. `/admin/structure/views`

   Use `Blazy Grid` as standalone blocks, or pages.

---

## <a name="features"> </a>FEATURES

Blazy provides the architectural scaffolding that native lazy-loading lacks. It
is the engine that transforms basic browser specs into a polished,
high-performance experience compliant with **Core Web Vitals**.

- **Deep Integration**:

  Seamless orchestration for Core Media, Views,
  Paragraphs, Media contrib, and [**Layout Builder**](#layouts). Supports
  Image, Responsive image, Picture, (local|remote|iframe) video, audio,
  [SVG](#svg), multi-breakpoint CSS backgrounds, and HTML media type.

- **Main-Thread Protection**:

  [Offloads heavy third-party embeds](#media-architecture) (Instagram,
  Pinterest, Twitter, YouTube, Vimeo, SoundCloud, etc) via **Lazyload HTML**
  and **Media Switcher** options to prevent UI "jank" and prioritize
  interaction.

- **LCP & CLS Management**:

  Engineered for a **"CLS-zero" strategy** using modern CSS `aspect-ratio`
  with legacy `padding-bottom` fallbacks to ensure layout stability across all
  browser generations. See [Aspect ratio](#aspect-ratio),
  [CLS Prevention](#cls) and [**Blazy Layout**](#layouts).

- **Critical Path Optimization**:

  Advanced preloading (image, responsive image/picture, iframe preview, CSS
  background and video poster) and `fetchpriority="high"` logic to
  systematically eliminate LCP discovery delays for the most important assets,
  and adaptive `decoding` for hidden or thumbnail Heroes.

- **Intelligent Loading Priority**:

  - **unlazy|slider**:

    Server-side exemption for Hero assets to trigger the browser's
    **Preload Scanner** immediately while keeping hidden and below-the-fold
    elements lazyloaded. See [Building heroes](#heroes).

  - **defer**:

    Postpones below-fold assets until the initial viewport is stable and the
    first row is about to enter the viewport.

  - **Native/JS Hybrid**:

    Supports Native lazyloading since incubation with an optional JavaScript
    delegation for granular threshold control and legacy browser support.
    JavaScript-based solutions offer superior **adaptive intelligence**: they
    can delegate the task to the browser's native engine when available, while
    providing a robust fallback for older environments.

- **Universal Compatibility**:

  - Supports [WEBP without forced Picture tags](#webp).
  - Works with IE9+ (v2.6), AMP, and static site generators (Tome, HTTrack).
  - Functional with or without JavaScript enabled in the browser.

- **Privacy by Design**:

  Integrated **Two-Click Media Loader** (**Media switcher > Image to Iframe**)
  ensures **GDPR/ePrivacy** compliance by blocking third-party tracking
  scripts until active user engagement. See [Optimization](#optimization).

- **Advanced Gallery Grids**:

  Built-in support for CSS3 Columns, Flexbox, and Native Grid layouts for
  multi-value Media and Text fields. See [Building galleries](#galleries).

- **Extensible Lightbox Ecosystem**:

  Unified **Media switcher** for Slick Lightbox, Colorbox, PhotoSwipe, Flybox,
  Magnific Popup, ElevateZoom Plus, and more. See
  [Lightbox integration](#lightboxes)

- **Developer & Editor API**:

  - **Vanilla Mode**:

    A minimalist footprint for advanced front-end architectures.

  - **Blazy Filter**:

    Streamlined shortcode support for embedding rich multimedia or grid with
    lightboxes and media players directly within text editors. See
    [Text formats and editors](/admin/config/content/formats) and
    [Filter tips](/filter/tips).

  - **Robust Hook API**:

    Comprehensive integration points for custom theme and module development.
    See [blazy.api.php](https://git.drupalcode.org/project/blazy/blob/3.0.x/blazy.api.php)

### OPTIONAL FEATURES

- Views fields for File Entity and Media integration, see:
  - [IO Browser](https://www.drupal.org/project/io)
  - [Slick Browser](https://www.drupal.org/project/slick_browser).
- Views style plugin `Blazy Grid` for CSS3 Columns, Grid Foundation, Flexbox,
  and Native Grid.

---

## <a name="recommended-modules"> </a>RECOMMENDED LIBRARIES/ MODULES

For better admin help page, either way will do, ordered by recommendation:

- `composer require league/commonmark`
- `composer require michelf/php-markdown`
- [Markdown](https://www.drupal.org/project/markdown)

To make reading this README a breeze at [Blazy help](/admin/help/blazy_ui)

### MODULES THAT INTEGRATE WITH OR REQUIRE BLAZY

- [Blazy layout](#layouts), included.
- [Ajaxin](https://www.drupal.org/project/ajaxin)
- [Intersection Observer](https://www.drupal.org/project/io)
- [Blazy PhotoSwipe](https://www.drupal.org/project/blazy_photoswipe)
- [GridStack](https://www.drupal.org/project/gridstack)
- [Outlayer](https://www.drupal.org/project/outlayer)
- [Intense](https://www.drupal.org/project/intense)
- [Mason](https://www.drupal.org/project/mason)
- [Slick](https://www.drupal.org/project/slick)
- [Slick Lightbox](https://www.drupal.org/project/slick_lightbox)
- [Slick Views](https://www.drupal.org/project/slick_views)
- [Slick Paragraphs](https://www.drupal.org/project/slick_paragraphs)
- [Slick Browser](https://www.drupal.org/project/slick_browser)
- [Splide](https://www.drupal.org/project/splide)
- [Splidebox](https://www.drupal.org/project/splidebox)
- [Jumper](https://www.drupal.org/project/jumper)
- [Zooming](https://www.drupal.org/project/zooming)
- [ElevateZoom Plus](https://www.drupal.org/project/elevatezoomplus)
- [Ultimenu](https://www.drupal.org/project/ultimenu)

---

## <a name="layouts"> </a>BLAZY LAYOUT

To fully leverage the Core **Layout Builder** (LB) and re-use the established
Grid system, Blazy provides the **Blazy Layout** submodule.

- Enable the [**Blazy Layout**](/admin/modules) submodule, if not already.
- Visit [**Blazy Layout Help**](/admin/help/blazy_layout) for more detailed
  applications.

## READ MORE

See the project page on drupal.org for more updated info:

- [Blazy module](https://www.drupal.org/project/blazy)

See the bLazy docs at:

- [Blazy library](https://github.com/dinbror/blazy)
- [Blazy website](https://dinbror.dk/blazy/)

---

## <a name="maintainers"> </a>MAINTAINERS/CREDITS

- [Gaus Surahman](https://www.drupal.org/user/159062)
- [geek-merlin](https://www.drupal.org/u/geek-merlin)
- [sun](https://www.drupal.org/u/sun)
- [gambry](https://www.drupal.org/u/gambry)
- [Contributors](https://www.drupal.org/node/2663268/committers)
- CHANGELOG.txt for helpful souls with their patches, suggestions and reports.

---

## <a href="#top">Back to Top &uarr;</a>
