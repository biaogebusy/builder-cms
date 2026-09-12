
***
## <a name="galleries"> </a>MULTIMEDIA GALLERY VIA VIEWS UI
#### Using **Blazy Grid**
For massive galleries, using **Blazy Grid + Lightbox** (Colorbox, PhotoSwipe,
etc.) is objectively faster than a slider-only implementation for static
viewing. Grid is the recommended alternative to sliders.

1. Add a Views style **Blazy Grid** for entities containing Media or Image.
2. Add a Blazy formatter for the Media or Image field.
3. Add any lightbox under **Media switcher** option.
4. Limit the values to 1 under **Multiple field settings** > **Display**, if
   any multi-value field.

#### Without **Blazy Grid**
If you can't use **Blazy Grid** for a reason, maybe having a table, HTML list,
etc., try the following:

1. Add a CSS class under **Advanced > CSS class** for any reasonable supported/
   supportive lightbox in the format **blazy--LIGHTBOX-gallery**, e.g.:
   + **blazy--colorbox-gallery**
   + **blazy--flybox-gallery**
   + **blazy--intense-gallery**
   + **blazy--mfp-gallery** (Magnific Popup)
   + **blazy--photoswipe-gallery**
   + **blazy--slick-lightbox-gallery**
   + **blazy--splidebox-gallery**
   + **blazy--zooming-gallery**

  Note the double dashes BEM modifier "**--**", just to make sure we are on the
  same page that you are intentionally creating a blazy LIGHTBOX gallery.
  All this is taken care of if using **Blazy Grid** under **Format**.
  The View container will then have the following attributes:

  `class="blazy blazy--LIGHTBOX-gallery ..." data-blazy data-LIGHTBOX-gallery`

2. Add a Blazy formatter for the Media or Image field.
3. Add the relevant lightbox under **Media switcher** option based on the given
   CSS class at #1.

#### Bonus
* With [Splidebox](https://drupal.org/project/splidebox), this can be used to
  have simple profile, author, product, portfolio, etc. grids containing links
  to display them directly on the same page as ajaxified lightboxes.
* With [IO](https://drupal.org/project/io), this can be used to have simple
  and modern Views infinite pagers as grid displays.
* With the new 2.17 `theme_blazy()` as a replacement for sub-modules
  `theme_ITEM()` contents, it will be easier to have hoverable product effects
  like seen at many commercial e-commerce themes.


#### <a name="views-gotchas"> </a>VIEWS GOTCHAS
Be sure to leave `Style settings > Use field template` unchecked.
If checked, the gallery is locked to a single entity, that is no Views gallery,
but gallery per field. The same applies when using Blazy formatter with VIS/IO
pager, alike, or inside Slick Carousel, GridStack, etc. If confusing, just
toggle this option, and you'll know which works. Only checked if Blazy formatter
is a standalone output from Views so to use field template in this case.

Check out the relevant sub-module docs for details.

---
<a href="#top">Back to Top &uarr;</a>
---
