
***
## <a name="troubleshooting"> </a>TROUBLESHOOTING
Please read this before submitting issues. This brief is worth reading to save
you from headaches.

If you don't find it solve your issues, check out:
[Slick Gotchas](https://www.drupal.org/project/issues/search?issue_tags=slick%20gotchas)

1. When upgrading from Slick v1.3.6 to later version, try to re-save options at:
   * `/admin/config/media/slick`
   * `/admin/structure/types/manage/CONTENT_TYPE/display`
   * `/admin/structure/views/view/VIEW_NAME`

     Only if trouble to see the new options, or when options don't apply
     properly. Most likely true when the library adds/changes options, or the
     module does something new. This is normal for any library even commercial
     ones, so bear with it.

2. Always clear the cache, and re-generate JS (if aggregation is on) when
   updating the module to ensure things are picked up:
   * `/admin/config/development/performance`

3. If you are customizing template files, or theme functions, be sure to
   re-check against the latest.

4. Slick and Blazy versions must be the latest. Too much version difference will
   most likely cause issues. Different branches will surely error.

5. Skins are permanently cached. Clear cache if new skins you created or
   provided by sub-modules do not appear immediately.

6. Lightboxes under `Media switcher` option are permanently cached. Clear cache
   if new lightbox modules installed do not appear immediately.

7. Do not use ID for theming. Use more reliable CSS classes, instead. ID is
   more useful for machines than themers. In Slick, it is to sync navigation.


## KNOWN ISSUES
1. Slick admin CSS may not be compatible with private or contrib admin
   themes. Only if trouble with admin display, please disable it at:

   `/admin/config/media/blazy`

2. [D7] The Slick lazyLoad is not compatible with Picture. Slick only
   facilitates Picture to get in. The image formatting is taken over by
   Picture. Some other options such as Aspect ratio is currently not
   supported either.

3. The following is not module related, but worth a note:
   * If the total < `slidesToShow`, Slick behaves. Previously added a workaround
     to fix this, but later dropped and handed over to the core instead.
     Brought back the temp fix for 1.6+ as per 10/18/16:
     See https://github.com/kenwheeler/slick/issues/262
   * Fade option with `slideToShow` > 1 will screw up.
   * `variableWidth` ignores `slidesToShow`.
   * Too much `centerPadding` at small device affects `slidesToShow`.
   * If thumbnail display is `Infinite`, the main one must be infinite too, else
     incorrect syncing.
   * `adaptiveHeight` is no good for vertical.

4. Lighbox integration
   * `Infinite` option will create duplicates or clone slides which look more
     obvious if `slidesToShow` > 1. This means that lightboxes (Colorbox,
     Photobox, PhotoSwipe) will have dups.
     **Solution:**

     Disable `Infinite` option.
     UPDATE: 2021/6, added fix specific for Colorbox ported from Splide.
