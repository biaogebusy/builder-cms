
***

## <a name="architecture"> </a>ARCHITECTURAL PHILOSOPHY

Inspired by Jan van Eyck’s motto **'Als Ik Kan'** (_As best I can_), Blazy is
built on the principle of intentional effort—refining code to the limits of our
ability to ensure the media delivery remains performant and stable.

Blazy operates on a philosophy of **selective enhancement** rather than
broad-stroke automation. By rejecting a "global takeover" approach, Blazy
empowers developers to act with precision—orchestrating exactly which assets
deserve priority. This strategic design ensures that critical elements, such as
brand logos, multi-site logo blocks, or **Largest Contentful Paint (LCP)**
media, are never throttled by the same logic used for non-essential footer
assets. Historically, this signature principle was defined by the mandate:
_"It doesn't take over all images."_ Blazy has consistently led this specialized
approach, proven to withstand the rigors of **Core Web Vitals (CWV)**.

While Blazy is purpose-built to optimize media delivery and prevent
media-induced layout shifts, it operates within a broader, interconnected page
ecosystem. As a result, achieving [true layout stability](#layouts) extends
beyond any single module. **Cumulative Layout Shift (CLS)**
[must be addressed holistically](#cls), with coordinated responsibility across
markup, rendering strategies, and interacting subsystems—not media alone.

---

### <a name="why-cwv"> </a>I. Throughput vs. Perceptual Timing

> _Why would I care about CWV and specifically LCP, when I can have overall
> lighter page weight with native lazy loading alone?_

**_Short answer:_**

We don't view it as a user preference, but we value diverse POVs. Feel free to
choose your own adventure!

**_Long answer:_**

The obsolete obsession with **page weight** is a relic of the dial-up era;
modern architecture prioritizes **Critical Path Optimization** and
**Perceptual Performance**.

| Metric                   | Philosophy                 | Technical Reality                                                                                                                                                                          |
| ------------------------ | -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Native Lazyloading**   | Passive Resource Deferral  | A blunt instrument that manages payload volume but does nothing to orchestrate the **Critical Rendering Path**. It is a bandwidth saver, not a speed generator.                            |
| **LCP (Core Web Vital)** | Dominant Content Heuristic | Measures the precise moment the **Viewport’s primary node** is rasterized. You can have a "light" page that fails LCP due to render-blocking CSS, slow TTFB, or unprioritized hero assets. |

**The Verdict:**

Reducing total byte-count via lazyloading is secondary to **LCP orchestration**.
An architect does not just lighten the ship; they ensure the lighthouse is
visible first. Native lazyloading without [LCP optimization](#heroes) is simply
"fast waiting"—the user still stares at a blank canvas while your unprioritized
"light" assets trickle in.

**Architectural Continuity & Refinement**

These architectural positions are not retrospective interpretations of modern
performance or privacy metrics. They emerged from long-standing design
constraints, practical integration challenges, and real-world usage across
diverse environments.

With the introduction of the Blazy [media player](#media-architecture)—initially
integrated with Slick in 2014—the ecosystem operated without unsolicited
tracking or external third-party dependencies. This approach was motivated by
performance predictability and integration clarity. In later years, these same
principles aligned naturally with the stricter expectations formalized under the
**General Data Protection Regulation (GDPR)**.

Similarly, Blazy’s emphasis on **selective enhancement**, explicit
[layout reservation](#aspect-ratio), and the deliberate avoidance of global
“auto-lazy” strategies addressed concerns around layout stability and perceptual
loading behavior. These concerns were eventually formalized within
**Core Web Vitals**, but the underlying architectural decisions predated those
metrics.

Rather than reacting to new standards, the architecture evolved in continuity
with its original constraints—many of which later proved durable under formal
measurement frameworks.

**Refinement Phase (2026)**

By 2026, the architectural focus shifts from _capability expansion_ to
_constraint enforcement_.

Patterns that were previously configurable—such as preloading strategies,
priority hints, and early media discovery—are being consolidated with stronger
defaults and clearer boundaries. The goal is not to reduce flexibility, but to
reduce accidental misuse and over-optimization in complex environments.

This refinement reflects operational maturity rather than reversal. Insights
gained from large-scale, real-world deployments are translated into guardrails
that preserve intentional, expert-level control while improving predictability
for broader usage.

> _2026 represents a transition from enabling performance patterns to
> formalizing performance discipline._

#### <a name="resource-manager"> </a>II. Resource Consolidation

> Blazy is not just a lazy-loader; it is an **intelligent resource manager**:

- **Precision Orchestration:**

  Prioritizes assets within the critical path to eliminate wasted bandwidth
  and CPU cycles.

- **Data Efficiency:**

  Drastically reduces unnecessary server requests, preserving resources for
  both the client and the server.

- **Performance-First Architecture:**

  Transforms traditional, passive lazy-loading into an active, prioritized
  media delivery system.

#### <a name="orchestration"> </a>III. Modern Consolidation

- **Main-Thread Liberation:**

  Blazy consolidates fragmented logic into a unified **Vanilla JS** framework,
  aligning with modern Core mandates to eliminate **jQuery** dependencies. By
  purging legacy library overhead, we minimize execution latency and ensure
  the render engine remains lean, future-proof and resilient.

- **Hybrid Orchestration:**

  Pioneering the **Native/JS Lazy-loading Hybrid** since its incubation, Blazy
  synchronizes with evolving browser standards. We provide a sophisticated
  bridge—leveraging native efficiencies while maintaining robust
  **Backward Compatibility (BC)** across all browser generations.

- **Surgical CWV Precision:**

  Blazy serves as a high-performance alternative to "one-size-fits-all" native
  lazyloading implementations. We don't just defer assets; we orchestrate the
  viewport to ensure **LCP dominance** and eliminate
  **CLS (Cumulative Layout Shift)** through rigid structural integrity.

#### <a name="interoperability"> </a>IV. Architectural Consolidation (DRY)

To ensure long-term maintainability, redundant logic across the ecosystem is
systematically merged into the Blazy core. This prevents "duplication of effort"
and ensures core performance fixes immediately benefit all submodules.

- **Formatter Unification:**

  Streamlined ecosystem by removing redundant formatters from
  **Intense, Zooming, Slick Lightbox, Splidebox,** and related integrations.

- **Image & Caption Unification:**

  The `theme_blazy()` now serves as the single source of truth, replacing
  various `theme_ITEM()` implementations for streamlined maintenance.

- **Centralized Logic:**

  Primary functions—lightbox embedding, interoperable grids, media player, and
  LCP-optimized sliders—are managed through a single, optimized source.

- **Interoperability:**

  Every integration inherits our **Core Web Vitals** optimizations (LCP, CLS,
  and fetchpriority) by default.

#### <a name="javascript"> </a>V. Asset Efficiency & Granular Architecture

Evaluation of "bloat" must be based on **Granular Delivery**, not repository
size. While the total potential JS library is **~33kB** (excluding polyfills
**~4.8kB** and admin **~1.4kB**), the architecture is strictly fragmented.
By disabling polyfills or opting for native browser capabilities (via
**No JavaScript** option) on modern sites, significant reductions are achieved.
Bearing in mind that Native lazyloading only supports `IMG`
and [defaut](#media-architecture) `IFRAME`.

- **On-Demand Loading:**

  Logic is only requested when a specific feature is invoked. A Media player
  will never load Lightbox code.

- **Intelligent Exclusion:**

  Requesting a Native Grid Masonry will not trigger Flexbox Masonry logic.
  These are distinct, independent fragments.

- **Component-Specific Footprint:**

  A typical formatter setup delivers only **0kB - 22kB** of code. Even
  complex, mixed-media ajaxified pages stay under the 33kB threshold due to
  the **exclusion principle**.

By delivering the library in fragments rather than a single monolithic bundle,
Blazy ensures that the user's browser only processes what is strictly necessary.
This **Just-In-Time execution** eliminates the overhead of traditional
"all-in-one" libraries, providing a feature-rich experience with the footprint
of a micro-library.

#### <a name="audit"> </a>VI. Independent Audit: The Minimalist Truth

We encourage an independent audit of the Blazy footprint to demystify the
architecture. Do not be distracted by the presence of fragmented files; in
production, Core asset aggregation resolves these into a singular, streamlined
delivery.

- **The Zero-JS Challenge:**

  1. Initialize a simple **Blazy Image**, the most basic formatter.
  2. Enable the **No JavaScript** option and remove polyfills in the UI.
  3. Disable all JS-dependent features (Blur, Lightbox, Media players,
     Responsive Image/Picture, Aspect ratio Fluid, etc.).

- **Observe the result:**

  Blazy is engineered to serve **zero JavaScript**. We provide the framework
  for total minimalism; while the final execution weight is an artistic
  choice, reaching the **33kB threshold** is impossible unless you permit
  systemic design inconsistency on a single page. This is impractical given
  Blazy’s feature set—useful only as a PoC.

> **Disclaimer on Technical Terminology:**
>
> My background in **Java/Kotlin multi-threading** informs the terminology used
> here. References to **main thread** and **background thread** (and thus
> execution) are used as a _conceptual mental model_ to describe execution
> priority, lifecycle, and the distinction between critical and offloaded logic.
> While **web application logic is single-threaded by default**, this framing
> offers an intuitive way to reason about performance bottlenecks and
> architectural focus. It is intended as an explanatory abstraction, not a
> literal description of web threading behavior. I hope this minor
> cross-disciplinary perspective improves clarity for the reader.

---

## <a href="#top">Back to Top &uarr;</a>
