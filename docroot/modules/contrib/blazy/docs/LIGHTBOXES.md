
***
## <a name="lightboxes"> </a> MULTIMEDIA LIGHTBOXES

All lightbox integrations are optional. If the required modules and/or libraries
are not present, no options will appear under **Media Switcher**, with the
exception of the default **Flybox**, available since version 2.17.

If expected options do not appear, clear caches, as **Media Switcher** options
may be permanently cached.

Most lightboxes (though not all) support responsive images, audio, and local or
remote video, SoundCloud, including HTML media type like Instagram, Pinterest,
etc. Known lightboxes with **Responsive Image** support include:

- Colorbox
- Magnific Popup
- Slick Lightbox
- Splidebox
- Blazy PhotoSwipe

**Additional notes:**

- **Magnific Popup** and **Splidebox** also support `<picture>`.
- **Splidebox** additionally supports AJAX content.
- Other lightboxes may offer partial or undocumented support.

---

### Built-in Blazy Lightboxes

Blazy provides two minimal built-in lightboxes:

- **Blazybox**

    Used in Intense, IO Browser, Slick Browser, ElevateZoomPlus, and similar
    integrations. It primarily serves as a fallback when third-party lightboxes
    do not support multimedia content.

- **Flybox** (since 2.17)

    A non-disruptive lightbox, similar to picture-in-picture, available via
    **Media Switcher**. Flybox is designed primarily for remote video, audio,
    SoundCloud, and similar media—not images.

    Flybox works best with non-grid layouts, allowing users to continue
    browsing the page while consuming media.

    Feature development is sponsor-driven. Potential enhancements include:
    - Automatic fly-out based on visibility or engagement timing (e.g. ads,
    e-commerce suggestions)
    - Integration with Zooming features, ElevateZoomPlus, and other lightboxes

---

### Lightbox Requirements

- **Colorbox**, **PhotoSwipe**, and similar integrations require both their
  corresponding modules and libraries.

- **Magnific Popup** requires only its library:

    `/libraries/magnific-popup/dist/jquery.magnific-popup.min.js`

    No Drupal module is required, as Magnific Popup exposes no reusable
    settings or configuration. Blazy provides its own initializer to enable
    advanced features such as:

    - Local and remote video
    - Responsive images and `<picture>`
    - Fieldable captions
    - Extended multimedia support not fully provided by upstream modules

---

### <a name="dompurify"> </a> Lightbox Captions with DOMPurify

To enable HTML content in lightbox captions, install **DOMPurify** via Composer
(see the [COMPOSER](#composer) section):

```bash
composer require npm-asset/dompurify
```

Alternatively, DOMPurify can be downloaded directly from:
[DOMPurify releases](https://github.com/cure53/DOMPurify/releases/latest)

**If downloading manually:**

* Install only the `dist` directory
* Do not include additional files from the archive

The Composer method installs the full package by default.

Blazy lightboxes support captions inside lightbox overlays. When HTML captions
are used, the DOMPurify library is required. Place one of the following files
inside your libraries directory:

* `DOMPurify/dist/purify.min.js`
* `dompurify/dist/purify.min.js`

Ensure all library files are directly accessible by the browser and do not
return 404 or 403 errors.

If using the Colorbox module, follow its recommended library path to avoid
duplicate folders. Blazy will automatically detect and use any valid
installation.

DOMPurify is optional. Without it, Blazy (sub-)modules will sanitize captions
server-side using basic sanitization rules.

---
<a href="#top">Back to Top &uarr;</a>
---
