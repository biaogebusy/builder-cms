# Ace — as a Drupal library

This repository packages the **minified no-conflict build** of
[Ace (Ajax.org Cloud9 Editor)](https://github.com/ajaxorg/ace-builds) as a Composer
`drupal-library`, so that a Drupal site can install it with Composer instead of copying files out
of `node_modules` or downloading a zip by hand.

`drupal/ace_editor` requires this package directly, the same way `drupal/anchor_link` requires
[`vardot/ckeditor5-anchor-drupal`](https://github.com/Vardot/ckeditor5-anchor-drupal). Nothing
needs to require it at the project level.

## Installation

```bash
composer require vardot/ace
```

With `composer/installers` and the usual Drupal `installer-paths`, the files land at:

```
web/libraries/ace/src-min-noconflict/ace.js
web/libraries/ace/src-min-noconflict/ext-searchbox.js
web/libraries/ace/src-min-noconflict/ext-language_tools.js
web/libraries/ace/src-min-noconflict/mode-*.js
web/libraries/ace/src-min-noconflict/theme-*.js
web/libraries/ace/src-min-noconflict/worker-*.js
```

which is where `drupal/ace_editor` looks: it scans `/libraries/ace` for `ace.js` and prefers the
minified no-conflict build over the other three.

## What is shipped, and what is not

Upstream `ace-builds` ships the same library four times — `src/`, `src-min/`, `src-noconflict/`
and `src-min-noconflict/` — around 58 MB in total. Only `src-min-noconflict/` is shipped here:

- **minified**, so it is what a site should actually serve, and
- **no-conflict**, so it does not define the global AMD `define()` and `require()` and cannot
  clash with another loader on the page.

The demos, the kitchen-sink page and the unminified builds are not shipped. If you need them, use
[ajaxorg/ace-builds](https://github.com/ajaxorg/ace-builds) directly.

The `1.x` branch and the `1.44.0` tag onward carry this layout. Tags up to `v1.2.8`, and the
`master` branch, are the original 2017 full fork and are left untouched.

## Versioning

Tags follow the upstream `ace-builds` release they are built from. `1.44.0` here is the build of
[ace-builds 1.44.0](https://github.com/ajaxorg/ace-builds).

## Upstream

- Pre-built files: https://github.com/ajaxorg/ace-builds
- Source: https://github.com/ajaxorg/ace
- Documentation: https://ace.c9.io/
- Licence: BSD-3-Clause (see [LICENSE](LICENSE)) — © Ajax.org B.V.

## Maintainers

- [Vardot](https://github.com/vardot)
