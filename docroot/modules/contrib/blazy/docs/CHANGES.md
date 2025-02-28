
***
## <a name="changes"></a>NOTABLE CHANGES  
Always check out release notes, if any issues with the latest changes.

* _Blazy 2.18_, 2023/09/30:
  + Warm fixes for 2.18 regressions.
  + Added **Media switcher > Image linked by Link field** option.
  + Added a dedicated **Link** field option to Blazy Media formatter.
* _Blazy 2.18_, 2023/09/24:
  + Mildly hot fixes for 2.17 regressions.
* _Blazy 2.17_, 2023/09/18:
   + Updated blazy.api.php.
   + Cold fixes for few minor regressions and self organizations.
   + **Breaking change**: changed `settings` to `#settings`, etc. Check out CR:
     [Blazy ecosystem breaking change](https://www.drupal.org/node/3375158).
   + **Deprecated**:
     * Colorbox body classes for local classes in `#colorbox` selector, e.g.:
       `body.colorbox-on--media` becomes `#colorbox.b-colorbox--iframe`, etc.
     * `blazy.done` event for colonized `blazy:done` to allow namespacing.
     * `bio.intersecting` event for colonized `bio:intersecting`.
   + **Removed**:
     * Sliders' lazy loads are no longer supported for just Blazy. Reasons: They
       are far more inferior than Blazy at so many levels, and brought more
       complications aka insanity for very minimal benefit.
     * Removed duplicated `blazy.resizing` event for `bio:resizing`.
   + **New features**:  
     * On your permissions at Blazy UI, `theme_blazy()` is now capable to
       replace sub-modules theme_ITEM() content, e.g.: theme_slick_slide(), etc.
     * Added a Flybox, a non-disruptive lightbox.
     * audio with BG cover, soundcloud, smarter Fluid ratio.
     * Added supports for local audio with background cover via settings.image.
     * Added supports for `SVG Image Field` module.
     * Added image ALT and TITLE for VEF which has none.
     * Re-purposed `Blazy Image with VEF (deprecated)` formatter for SVG (WIP).
     * Removed stone-aged admin CSS for modern Native Grid.
     * Added new events: `bio:done` for entire collections, `bio:resizing`,
       `bio:resized`, `blazy:mediaPlaying`, `blazy:mediaStopped`.
   + **New options**: Added additional config options at Blazy UI. Be sure to
     check out for `visible_class`, `wrapper_class`, `deprecated_class`,
     `use_oembed`, `lazy_html`, `use_encodedbox`, etc. options if using them.
   + Renamed legacy Foundation grid CSS classes to avoid conflicts with core
    `block` CSS classes:
     * `block-GRIDSTYLE` to `b-GRIDSTYLE`, e.g.: `block-nativegrid` to
       `b-nativegrid`, `block-grid` to `b-grid`, etc.
     * `block-count-N` to `b-count-N`
     * `LONGSIZE-block-GRIDSTYLE-N` to `b-GRIDSTYLE--SHORTSIZE-N`, e.g.:
       `small-block-nativegrid-2` to `b-nativegrid--sm-2`, etc.  

      Your CSS overrides, if any, will continue working till 3.x, no rushes.
      If broken for a reason, please copy them from previous releases into
      your theme till you have time to update them.

* _Blazy 2.16_, 2023/06/02:
   + Hotdamn fix for D10 breaking changes with formatter lightboxes.
* _Blazy 2.13_, 2022/05/31:
   + [#3282785](https://drupal.org/node/3282785), hotdamn fix.
* _Blazy 2.12_, 2022/05/28:
  + Regression fixes for [Optimization](https://drupal.org/node/3257511).
* _Blazy 2.11_, 2022/05/07:
  + Regression fixes for [Optimization](https://drupal.org/node/3257511).
* _Blazy 2.10_, 2022/04/16:
  + Regression fixes for [Optimization](https://drupal.org/node/3257511).
* _Blazy 2.9_, 2022/03/07:
  + [#3268089](https://drupal.org/node/3268089), hotdamn fix.
* _Blazy 2.8_, 2022/03/06:
  + Added `defer` loading as per [#3120696](https://drupal.org/node/3120696).
  + Regression fixes:
    * blur, BG.
    * [#3266748](https://drupal.org/node/3266748)
    * [#3266482](https://drupal.org/node/3266482)
* _Blazy 2.7_, 2022/02/20:
  + If you found these optimization-period releases still have oversight bugs,
    please lock it at Blazy 2.5 till the next hot fix releases. Kindly report
    any uncovered regressions, or issues for quick fixes. It is still a
    need-feedback release. Rest assured, we'll continue breaking this module
    innocently with a hiatus of used-up free-time and less buggier one, till
    this issue [Massive optimization](https://drupal.org/node/3257511) is marked
    as postponed or fixed.
    Thanks for understanding + good spirit for betterment :)
  + Added core D9.2 webp client-side fallback for those who want to support old
    browsers and want modern ones have cleaner native image markups.
  + Added `core/once` compat to save headaches and easy migration when min D9.2.
  + Added `settings.blazies` grouping for sanity and to avoid conflict with
    sub-modules till all settings converted into BlazySettings at 3+.
  + Moved media-related classes/ services into `Drupal\blazy\Media` namespace.
  + Added Magnific Popup as decent replacement for Colorbox and Photobox.
  + [Hot fix](https://drupal.org/node/3263027) for D8 `app.root` compat.
* _Blazy 2.6_, 2022/02/07:
  + [Preloading](https://drupal.org/node/3262804).
  + [Anti-pattern buffer](https://drupal.org/node/3262724).
  + Works absurdly fine at IE9 for core lazy functionality. Not fancy features
    like Blur or Animation, etc. Unless you include some polyfills on your own.
  + [Drupal 10 ready](https://drupal.org/node/3254692).
  + `dBlazy.js` is pluginized, has minimal jQuery replacement methods to DRY.
    Check out `js/components/jquery/blazy.photobox.js` for a sample.
  + `dBlazy.js` removed many old IEs fallback. Some were moved into polyfill
    which can be ditched via Blazy UI to abandon IE supports. Should you need
    to support more, please find and include polyfill into your theme globally.
  + Old bLazy is now a [fallback for IO](https://drupal.org/node/3258851) to
    have a single source of truth to minimize competitions and complications.
    Competition is great to measure survival, but not within a module codebase.
    The library is forked at Blazy 2.6, and no longer required from now on.
    Both lazyloader scripts (IO + bLazy) can be ditched via `No JavaScript`.
  + [Decoupled lazyload JavaScript](https://drupal.org/node/3257512). Now Blazy
    works without JavaScript within/without JavaScript browsers.
    Even [AMP](https://drupal.org/node/3101810) pages.
    Any javascript-related issues might no longer be valid when
    `No JavaScript lazy` enabled. Unless the exceptions are met or for those
    who still support old IEs, and cannot ditch lazyloader script, yet.
  + [Massive optimization](https://drupal.org/node/3257511). Please report any
    uncovered regressions, or issues for quick fixes. Thanks.
