
***
## <a name="manifesto"> </a>THE BLAZY MANIFESTO
*A performance system, not a shortcut.*

Blazy is intentionally opinionated by design.

This is not accidental, nor is it a limitation — it is a deliberate requirement
for building performant **media delivery** at scale. However, as just one
participant within a page, Blazy is not aware of the **entire page ecosystem’s
performance characteristics**. Understanding this bounded scope helps set clear
expectations for what Blazy can and cannot address.

---

### 1. Blazy & Native Lazy-Loading

Blazy is not “just lazy-loading”.

JavaScript-based lazy-loading was originally introduced as a
[**primitive**](#why-cwv) to support backward and forward compatibility, and was
later complemented in part by native lazy-loading.

Native lazy-loading is **necessary** — but it is also **scoped by design**.

Blazy operates as a [**coordination layer**](#resource-manager).

It adopted native lazy-loading early, integrates it deliberately, and constrains
it where unconstrained usage is known to negatively affect **Core Web Vitals**
(such as LCP or CLS), using [**selective enhancement**](#architecture).

---

#### 1.a. Blazy Is Not a Feature Module

Blazy exists to address the **coordination problem** between
[layout](#layouts),
[media](#media-architecture),
[JavaScript](#javascript),
and [rendering order](#cls) as they interact under
[real content](#content-architecture),
[real editors](/filter/tips),
and [real production constraints](#optimization)
— the conditions under which CWV regressions are most commonly observed.

If a site renders only static images in isolation, Blazy may reasonably appear
unnecessary.
That scenario is not its primary target environment.

---

#### 1.b. Native Features Are Embraced, Not Replaced

Native features solve *specific, well-defined problems*.

Blazy exists because **frontend systems rarely fail in isolation**.

Blazy does not compete with native browser behavior.
It **embraces**, **orchestrates**, and builds upon it — including native
lazy-loading — as part of a broader media delivery strategy.

---

#### 1.c. Blazy Existence & Scope

Blazy exists to align with how **Core Web Vitals** are evaluated and how
browsers behave under real-world conditions — not only for images, but across
media types and rendering strategies.

It coordinates adaptive priority, decoding and loading strategies, selective
preloading, AMP and sandboxed modes, backward compatibility,
[LCP element coordination](#heroes), and layout stability strategies via
[Blazy Layout](#layouts).

Native lazy-loading in core intentionally covers a
**narrow, declarative subset** of use cases, primarily `IMG` elements and
[default or non-optimized `IFRAME`](#media-architecture) usage. As of this
writing, it does not extend to `VIDEO`, `AUDIO`, or third-party `HTML` embeds,
nor does it address script weight, media player initialization, or layout
reservation.

**Blazy addresses these adjacent concerns by:**

- unifying lazy-loading behavior across media types,
- deferring or substituting heavyweight iframes with lighter media switchers
  (such as optimized `IFRAME` players, audio/video posters, lightbox
  integrations, or links to external content),
- blocking third-party scripts until user intent is explicit.

These tradeoffs — media substitution, script deferral, layout reservation,
and LCP protection — are intentionally outside the scope of native lazy-loading,
which is **non-opinionated by design**.

For clarity: native lazy-loading is not insufficient; it is
**intentionally scoped**.
Blazy exists to address the concerns it does not attempt to solve.

---

#### 1.d. On Blazy Removal

Blazy is not mandatory.

If a completed and maintained alternative reaches functional parity within this
scope — whether in Slick or elsewhere — Blazy can be removed.

Until such an implementation exists, Blazy remains the maintained solution for
these concerns.

---
### 2. Performance Is a Structural Problem

Most performance regressions are not bugs.
They are emergent behavior from **unconstrained configuration** and composition.

**Common contributing factors include:**

- Missing dimensions
- Unstable layout containers
- Late discovery of critical media assets
- Over-eager JavaScript execution
- Conflicting [rendering strategies](#big-bees)
- Non-optimized images
- Failure to defer `IFRAME` and third-party HTML content
- Assumptions based on limited local testing (“it worked on my machine”)

Performance issues are often structural rather than cosmetic.

Blazy approaches performance as a **structural concern**:

- Layout influences performance characteristics
- Dimensions influence layout stability
- Priority influences loading order
- Constraints influence predictability

Optimizing after the fact is often fragile.
Blazy instead aims to **reduce entire classes of common performance regressions
through design choices**.

Blazy encodes [**performance-related constraints**](#optimization) into its
architecture to make problematic configurations harder to reach.

These guardrails exist because documentation alone does not reliably prevent
regressions in complex systems.

---

### 3. Layout Is Performance Policy

Blazy treats layout as a first-class performance consideration.

**Layout decisions can influence:**

- CLS
- LCP
- Media discovery
- Rendering order
- JavaScript execution timing

[**Blazy Layout**](#layouts) exists to make these tradeoffs explicit and
controllable:

- Reduce layout instability
- Encourage predictable rendering behavior
- Enable grid-aware media handling
- Integrate with CWV-conscious constraints

**Blazy Layout** is not decorative.
It is intentionally opinionated.

If layout appears “philosophically unrelated” to lazy-loading,
that often indicates the associated performance cost has not yet been measured
or observed.

---

### 4. Configuration Is Not Neutral

Every option has consequences.

**Blazy makes those consequences more visible through:**

- Scoped configuration
- Contextual warnings
- Limited high-impact features
- Documentation of unsafe configurations
- Scoped and restricted inline CSS
- Discouragement of global overrides
- Intentionally unsupported “vanilla” modes
- Documentation that explains *why*, not only *how*

Configuration is power — and responsibility.
Blazy exposes configuration because real sites are complex.

Blazy does not silently trade performance for convenience.
When performance characteristics change, the system aims to make the cause
understandable.

This may feel restrictive to some users.
It is protective for production systems.

---

### 5. Defaults Favor Stability Over Surprise

**Blazy’s defaults are conservative by design:**

- Layout stability
- Predictable rendering behavior
- Safer editor-facing behavior
- Performance regressions are costly to diagnose
- CWV issues are often invisible until late
- Reduced accidental misuse

Advanced features remain available — but require intent.
They are opt-in.
They are accompanied by warnings.
This is deliberate.

---

### 6. Opinionated Does Not Mean Inflexible

**Blazy allows you to:**

- Bypass processing
- Render vanilla markup
- Disable features selectively
- Integrate with other systems

Each option comes with documented tradeoffs.

Blazy avoids silently sacrificing performance characteristics for convenience.

---

### 7. Complexity Is Not an Accident

Blazy is designed to work reliably with common frontend patterns and
components, including:

- Sliders
- Grids (including native grid)
- Lightboxes
- Media players
- CSS backgrounds
- Inline SVG
- Vanilla output
- Custom CSS via [**Blazy Layout**](#layouts)

These are not exotic features.
They are **common frontend requirements**.

Coordinating media delivery across these patterns—without degrading
**Core Web Vitals**—is inherently complex. Blazy exposes and documents that
complexity rather than hiding it behind fragile abstractions or one-click
toggles.

---

### 8. JavaScript Is a Cost, Not a Feature

Sliders and lightboxes often carry significant cost — large images, third-party
media, player logic, and library weight.

Blazy does not attempt to abstract this away.

**Instead, it provides mechanisms such as:**

- Deferred loading
- Visibility-based constraints
- Native Grid alternatives
- On-demand initialization
- [Optimized media](#media-architecture) handling
- Explicit warnings as complexity increases

When JavaScript is required, it is introduced **intentionally**, with granular
delivery and selective exclusion.

---

### 9. LCP Is Treated as a Singular Event

**Largest Contentful Paint** is not a toggle.
It is a **decision point**.

**Blazy:**

- Treats [hero media](#heroes) as a first-class concept
- Limits `slider` and `unlazy` behavior to reduce misuse
- Associates preload behavior with late-discovered critical assets
- Discourages multiple competing “hero” candidates

This aligns with observed browser heuristics rather than design trends.

---

### 10. This System Was Built Under Constraints

**Blazy evolved under real-world constraints:**

- Short timelines
- Mixed responsibilities (modules, themes, implementation)
- Performance targets under production load

Early versions reflect pragmatism.
Later versions reflect refinement.

The current ecosystem is the result of years of iteration,
measurement, and correction — often informed by patches,
reports, and data from others.

---

### 11. Benchmarks Matter More Than Opinions

Blazy’s architecture is shaped by measurement rather than preference.

As of this writing, under [LCP requirements](#heroes) involving
**multi-value fields**, measured results have shown:

- Reduced page weight
- Improved JavaScript execution characteristics
- Lower memory footprint
- More stable CWV outcomes in slider-heavy and media-dense layouts

These observations include comparisons against alternative implementations,
including Splide- and Slick-based solutions, evaluated under comparable
conditions.

Productive performance discussions benefit from shared technical context,
reproducible methodology, and clearly defined constraints.

**Minimum expectations include:**

- **Objective Benchmarking**

  Technical findings derived from “apples-to-apples” comparisons conducted under
  comparable CWV methodologies, configurations, and environmental conditions.

- **Evidence over Anecdote**

  Subjective impressions or misconfiguration should not substitute for measured
  results. Performance characteristics observed without caching, asset
  aggregation, or baseline optimization are considered anecdotal rather than
  representative.

  Anecdotal regressions can occur and are useful as signals, but they do not
  constitute conclusive evidence without supporting metrics.

- **Issue Isolation**

  The documented configuration guidance and
  [Strategic Optimization Checklist](#optimization) exist to help ensure that
  resolved issues remain isolated and do not regress under unrelated changes.

Claims without [reproduction steps](#contribution) or supporting metrics are not
actionable.
Evaluation without appropriate [isolation](#cls) and
[configuration context](#optimization) cannot be reliably assessed and is
handled accordingly.

---

### 12. On Criticism

Constructive feedback is welcome.
Evidence-backed reports are appreciated.
Patches are celebrated.
Successful contributions and data-driven disagreements are given
[due credit](https://www.drupal.org/node/2663268/committers) with sincere
gratitude.

**Unsubstantiated claims**, **subjective critiques**, and **legacy anecdotes**
without configuration, output, metrics, or reproduction steps do not materially
improve the project. They introduce signal that is difficult to act upon. When
repetition reaches an unproductive threshold
(*analogous to sustained overload*),
it ceases to be collaborative feedback and becomes noise that must be filtered.

Silence in such repeated cases should be understood as a boundary on engagement,
not a dismissal of contributors or their experiences.

---

#### 12.a. On Perceived Scope and Complexity

> *“A big mess, bloated, and extremely complex.”*

This perception is understandable when Blazy is evaluated outside its intended
scope. We also recognize that engaging with this level of complexity is shaped
by time constraints, available resources, and differing project priorities.

Blazy is not designed as a single-purpose feature or a minimal helper. It exists
to address **coordination problems** across layout, media, JavaScript execution,
and rendering order under real-world content and production constraints. That
coordination introduces structure and configuration that may appear complex when
viewed in isolation.

---

> *“A big mess”*

Blazy addresses [layout stability](#layouts),
[media discovery](#optimization),
[JavaScript cost](#javascript),
and [Core Web Vitals considerations](#why-cwv) **in combination**, where these
concerns intersect in the media delivery path.

Performance at scale is inherently complex.
Blazy aims to make that complexity explicit rather than implicit.

Where a simpler configuration is sufficient for a given use case, Blazy supports
that as well.

---

> *“Bloated”*

**What may be perceived as “bloat” often results from:**

- supporting multiple media types (images, video, audio, third-party embeds),
- maintaining backward and forward compatibility,
- accounting for **Core Web Vitals** such as CLS and LCP across diverse
  environments,
- making tradeoffs explicit rather than implicit.

Blazy does not aim to be minimal for all use cases.
If a site requires only basic image lazy-loading, simpler solutions may be more
appropriate.

---

> *“Extremely complex”*

The complexity in Blazy is intentional and reflects the complexity of the
problems it addresses.

Different tools serve different scopes. Blazy operates where performance issues
emerge from interactions between systems rather than isolated features.

Diverse perspectives are valid, and choosing not to use Blazy is a reasonable
decision when its scope does not match a project’s needs.

---

> *“Over-engineered.”*

The guardrails present in Blazy exist because similar systems have failed
without them under real-world conditions.

Performance issues often do not surface in demos.
They tend to appear in production, under load, with real content and editors.

---

> *“Other modules don’t require this.”*

Some modules optimize primarily for simplicity or minimal configuration, while
others prioritize stability and predictability.

**Blazy optimizes for:**

- Predictable rendering behavior
- CWV-aware media handling
- Production resilience

Different tools serve different priorities, and that diversity is healthy.

---

#### 12.b. On Perceived Failures and Expectation Mismatch

> *“This should work automatically.”*

Fully automatic behavior is a common source of CLS and LCP regressions.

Blazy [intentionally avoids global automation](#architecture) where it would
introduce unpredictable rendering or layout instability.

[Explicit configuration](#optimization) is a deliberate design choice intended
to protect production sites.

---

> *“This breaks my layout.”*

Blazy enforces constraints that can expose existing layout issues
(e.g. *missing dimensions, unstable containers, unsafe overrides*).

Layout instability is most commonly associated with missing
[**aspect ratios**](#aspect-ratio) or undefined dimensions, including for
externally sourced assets.

**To investigate further, please provide:**

- Configuration screenshots
- Output HTML
- Browser metrics (CLS/LCP)
- Steps to reproduce
- **Console** tab output (via browser developer tools)
- (Un-)install BigPipe to fully understand layout rendering strategies

Reports are not dismissed; evidence is required to evaluate and resolve issues.
Without reproducible context, the underlying cause cannot be determined.

---

> *“Blazy caused my CWV regression.”*

Blazy does not directly manipulate browser metrics.

When a regression is observed, it is typically associated with one or more of
the following:

- Configuration changes
- Layout changes
- Media priority adjustments
- JavaScript behavior

Please include measurements and reproduction steps and compare against
documented [configurations](#optimization) so the issue can be investigated.

---

> *“Why not just lazy-load everything?”*

Lazy-loading all content indiscriminately is a known anti-pattern for LCP and
CLS.

Blazy treats lazy-loading as a tool rather than a default and limits its use
where it would negatively affect performance. This approach was established
through experience and iteration, well before modern metrics such as
**Core Web Vitals** were formalized.

---

#### 12.c. On Quality and Ongoing Maintenance

> *“Buggy & fatal errors.”*

During the 2.17 development period, issues of this nature did occur. Those
periods have since stabilized.

Should you encounter bugs, please use the project issue queue. Reports that
identify regressions or edge cases are appreciated and have contributed
meaningfully to improvements under the
[contribution guidelines](#contribution). Many contributors have received
[due credit](https://www.drupal.org/node/2663268/committers) for their efforts.

---

### A Friendly Closing Note

Blazy is opinionated because performance is delicate, and small decisions
compound.

Blazy does not promise perfect Lighthouse scores, nor does it attempt to control
or measure whole-page performance.

Lighthouse reflects aggregate page behavior;
Blazy is a **media-level solution**, shaping the browser behaviors that media
introduces into the rendering pipeline, including media delivery and
media-induced layout shifts, as one part of a larger ecosystem.

Blazy exists to make good outcomes repeatable,
undesirable outcomes harder to reach,
and tradeoffs visible rather than hidden.

The goal is not restriction —
the goal is to **make sustainable performance easier to achieve**.

---

> Opinionated systems prevent classes of problems.
> Unopinionated systems document them afterward.

---
<a href="#top">Back to Top &uarr;</a>
---
