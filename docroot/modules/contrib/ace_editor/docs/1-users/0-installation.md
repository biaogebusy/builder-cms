# Installation and Setup

Ace Editor integrates the Ace code editor with Drupal. The module ships the
integration code only — you provide the Ace JavaScript library.

## 1. Install the module

With Composer:

```bash
composer require drupal/ace_editor
drush en ace_editor
```

## 2. Download the Ace library

The module does **not** bundle the library. Download a recent
[`ace-builds`](https://github.com/ajaxorg/ace-builds) release and copy its
`src-min-noconflict` build into your site's libraries directory:

```bash
git clone --depth 1 --branch v1.43.3 https://github.com/ajaxorg/ace-builds.git
mkdir -p web/libraries/ace
cp -a ace-builds/src-min-noconflict web/libraries/ace/
```

The module scans `/libraries/ace`, `/libraries/ace-builds` and its own
`libraries/` directory, looking recursively for `ace.js`. Any of these layouts
works; the example above results in
`web/libraries/ace/src-min-noconflict/ace.js`.

You may also manage the library with Composer via
[asset-packagist](https://asset-packagist.org) (`npm-asset/ace-builds`), if you
prefer a Composer-managed workflow.

## 3. Verify the library was found

Visit **Reports → Status report** (`/admin/reports/status`). The *Ace library*
row shows either the path where it was found or an error explaining that it is
missing.

## 4. Choose how to use it

Ace Editor has three independent integrations — enable whichever you need:

- [Using the Code Editor](1-using-the-editor.md) on edit forms.
- [Displaying Code (Formatter)](2-formatter.md) on rendered entities.
- [Embedding Snippets (Filter)](3-filter.md) inside body text.

See [Configuration](../2-admins/0-configuration.md) for the full set of options.
