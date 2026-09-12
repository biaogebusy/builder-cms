
***
## <a name="roadmap"> </a>ROADMAP/ TODO
* [x] Adds a basic configuration to load the library, probably an image
  formatter.

  2/24/2016

* [x] Media entity image/video, and Video embed field lazyloading, if any.

  10/25/2016

  Added both simple Blazy Media formatter and Views field Media Entity.

* [x] Makes a solid lazyloading solution for IMG, DIV, IFRAME tags.

  4/9/2017

  Added IFRAME (Blazy Video), apart from existing IMG/ DIV (CSS background).

* [x] Core Media integration.

  01/03/2019 (basic) - 15/08/2023 (full-fledged)

* [x] Core Responsive Image integration.

  05/02/2020

  Added multi-breakpoint CSS background and Aspect ratio Fluid supports.

* [?] Blazy 4.x, D12 readiness at min D11, not D10:

      24/01/2026 (started) - ? (finished)

      The 4.x goal is to reduce convenience for efficiency and
      finally correctness at 5.x.

      - Add `declare(strict_types=1);` to all .php files, excluding `.module`.
      - Convert all procedural hooks into #[(Hook)] attributes.
      - Convert most internal static into instance classes.
      - Explicit return and parameter types to a great extent.
      - Postponed `blazy.api.php` procedural changes till Blazy 5.x, except for
        the new method replacements relevant to 4.x major changes.
      - Refactor public services to reduce inheritance, except for plugins.
      - Remove method aliases to a great extent.
      - Remove property aliases to a great extent.

* [?] Maturity and stabilization.

  This follows the well-known principle of **premature optimization** in
  software developments.

---
<a href="#top">Back to Top &uarr;</a>
---
