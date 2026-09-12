
***
## <a name="faq"> </a>FAQ

### CURRENT DEVELOPMENT STATUS
A stable production release is anticipated following comprehensive community
feedback, final code sanitation, and performance optimization. We maintain an
open-contribution model—technical patches and audits are highly encouraged.

### PROGRAMMATIC INTERFACE
For deep-level integration and custom implementation, refer to the
[blazy.api.php](https://git.drupalcode.org/project/blazy/blob/3.0.x/blazy.api.php) documentation.

---

### BLAZY (Namespace) VS. B-LAZY (Class)
`blazy` identifies the module namespace; `b-lazy` is the designated CSS selector
for the lazy-loading engine.

- **The `.blazy` Wrapper:** This is applied to the **top-level container**
(e.g., `.field`, `.view`, `.item-list`). It acts as the configuration hub where
script options are injected via the `[data-blazy]` attribute to override global behaviors on a per-instance basis, only if needed.
- **The `.b-lazy` Target:** This is applied to the **individual asset** (IMG,
VIDEO, DIV). While typically a child of `.blazy`, it is the specific node the
engine monitors for intersection.

### BLAZY:DONE VS. BIO:DONE EVENTS
- **`blazy:done`**: Dispatched upon the successful loading of an
**individual element**.
- **`bio:done`**: Dispatched when an **entire collection** has completed its
lifecycle.

> **Deprecation Warning:** Since 2.17, we have moved to
**colonized event names** (e.g., `blazy:done.MYMODULE`). The legacy dotted
notation (`blazy.done`) is deprecated and will be removed in **3.x**. If using
the provided listeners, transition your event listeners immediately to ensure
long-term stability.

---

### WHAT IS THE `.blazy` CSS CLASS FOR?
The `.blazy` class serves as a **performance boundary**. By limiting the
script’s scan to specific containers rather than the global DOM, we achieve:
1.  **Scope Control:** The engine ignores irrelevant nodes, reducing main-thread
    execution time.
2.  **Architectural Flexibility:** This allows multiple containers on a single
    page to have unique features—such as one utilizing multi-breakpoint images
    and another serving image-to-iframe media—without logic collisions.

### WHY NOT `BLAZY__LAZY` (BEM)?
`b-lazy` is the native selector for the underlying JS logic. We prioritize
**functional standards over naming conventions**. Respecting the engine's
defaults ensures maximum performance and a smaller footprint by avoiding
unnecessary abstraction layers.

---

### <a name="theme-blazy"> </a> THEME_BLAZY(): THE SINGLE SOURCE OF TRUTH
As of 2.17, `theme_blazy()` has replaced the redundant internal logic of various
sub-modules (`theme_slick_slide()`, `theme_splide_slide()`, etc.). It is not
replacing their established `theme_ITEM()`, just their contents when
we all have dups with IMAGE/MEDIA + CAPTIONS constructs.

**The "Why":**
- **DRY Execution:**

  Dramatically reduces code duplication—a core tenet of our architecture.
- **Unified Enhancements:**

  New features (like hover effects or SVG description support) are deployed once
  and instantly inherited across the entire ecosystem.
- **Streamlined Maintenance:**

  Bug fixes in the central engine immediately stabilize all integrating modules.

**Migration Path:**
If you are currently overriding `theme_ITEM()` templates, migrate your logic
before the 3.x release:

  - **Preprocessing:** Use `THEME_preprocess_blazy()`.

  - **Captions:** Utilize `hook_blazy_caption_alter()`.

  - **State Management:** Use the `settings.blazies` object to steer HTML
    changes conditionally.

  - **Last Resort:** Override `blazy.html.twig`. Note that even the core
    author avoids this—the provided hooks are 100% sufficient for custom
    architectural requirements.

---
<a href="#top">Back to Top &uarr;</a>
---
