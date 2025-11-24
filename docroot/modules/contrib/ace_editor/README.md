# Ace Editor

[Ace Editor](https://ace.c9.io/) is a code editor written in JavaScript, allowing you to edit HTML,
PHP and JavaScript (and more). It provides syntax highlighting, proper
indentation, keyboard shortcuts, find and replace (including regular
expressions).

This module integrates the Ace editor into Drupal's node/block edit forms,
for editing raw HTML/PHP/JavaScript (with more) in a familiar way.
It also provides a display formatter, along with a text filter and
an API to embed and show code snippets in your content.


## Table of contents

- Requirements
- Installation
- Uninstallation
- Features
- Maintainers

## Requirements

This module requires the [ACE Editor JavaScript library](https://github.com/ajaxorg/ace-builds)

Please download any of the available builds of the latest version and **copy it under** _/libraries/_ so _ace.js_ file can be found under _{drupal_root}/libraries/{any-sub-dir}/ace.js_. Alternatively, use Composer, see "Installation" below.</li>


## Installation

Install with Composer (recommended) or manually, by following these steps.

[Downloading third-party libraries using Composer](https://www.drupal.org/docs/develop/using-composer/manage-dependencies#third-party-libraries)

### Install with Composer

Define npm-asset repository in the `composer.json`` file, to allow downloading the ACE Editor JavaScript library to the correct folder:

```
composer config repositories.assets composer https://asset-packagist.org
composer config --json extra.installer-types '["npm-asset", "bower-asset"]'
composer config --json extra.installer-paths.web\/libraries\/ace '["npm-asset/ace-builds"]'
composer config --unset extra.installer-paths.web\/libraries\/\{\$name\}
composer config --json extra.installer-paths.web\/libraries\/\{\$name\} '["type:drupal-library", "type:bower-asset", "type:npm-asset"]'
```

Download the ACE Builds library. or require it by composer with.
```
composer require npm-asset/ace-builds:~1.0
```

Better to get the library from https://www.npmjs.com/package/ace-builds


Download the ACE Editor module and install it:

```
composer require drupal/ace_editor:~2.0
drush en ace_editor
```

The Ace library will be downloaded to the `/libraries` folder with `$ composer install/update`. Only one version
(minified, noconflict...) is required. Other folders (including `/demo`) can be removed.


### Install manually

1. Download the latest version of the Ace Editor at
   https://github.com/ajaxorg/ace-builds/ or directly
   via https://github.com/ajaxorg/ace-builds/archive/master.zip
   Do not use a version < 1.4.0.
1. Extract and place the files tree contents of only one of the releases
   (minified, noconflict...) under `/libraries/ace` so that`ace.js`
   is located at `/libraries/ace/ace.js`.
1. Download, extract and copy the "Ace Editor" module to your
   `/modules` or `/modules/contrib` directory.
1. Enable the "Ace Editor" module on your Drupal Extent page,
   under the Administration heading. An "Ace Editor" filter format
   is added. You can create a new Text format for use it, or
   enable the Ace editor for other filter formats at
   `/admin/config/content/formats`.


## Uninstallation

The module adds a text format named 'Ace Editor' on installation. This filter
is not disabled when the module is uninstalled, to preserve any content
saved using it. If you are sure that there is no valuable content in your
database saved under the 'Ace Editor' filter format, you can manually disable
the filter format at `admin/config/content/formats`.


## Features

**Edit HTML, PHP, YAML... in your entities and blocks like a pro**

Go to _admin/config/content/formats_, create/edit a Text format and select 'Ace'
as the Text Editor. Afterwards, configura the Editor settings. Then head over to
a block or entity containing a textarea with the correct text format and hack 
away!

**Autocompletion**

Press **Ctrl+Space** to use the autocomplete option while coding.

**Display fields using syntax highlighting**

Manage the display of any text area fields attached to an entity and select
the "Ace Editor" format. This outputs the content of the field
as a ready-only editor, with syntax highlighting in your entity view using
the selected options.


**Embed code snippets in the body of your entities or blocks**

Add the syntax highlighting filter to any of your text formats. The module
displays text inside an `<ace>` tag as code using the custom formatting options
specified as attributes to the `<ace>` tag.

**You can override the default options by adding attributes to the `<ace>` tag**

Here are the possible values:

```
theme
  clouds = Clouds
  clouds_midnight = Clouds Midnight
  cobalt = Cobalt
  crimson_editor = Crimson Editor
  dawn = Dawn
  idle_fingers = Idle Fingers
  kr_theme = krTheme
  merbivore = Merbivore
  merbivore_soft = Merbivore Soft
  mono_industrial = Mono Industrial
  monokai = Monokai
  pastel_on_dark = Pastel on dark
  solarized_dark = Solarized Dark
  solarized_light = Solarized Light
  textmate = TextMate
  twilight = Twilight
  tomorrow = Tomorrow
  vibrant_ink = Vibrant Ink
```

... and any other `theme-*.js` file available in `\libraries\ace`

```
syntax
  c_cpp = C/C++
  clojure = Clojure
  coffee = CoffeeScript
  csharp = C#
  css = CSS
  groovy = Groovy
  html = HTML
  java = Java
  javascript = JavaScript
  json = JSON
  ocaml = OCaml
  perl = Perl
  php = PHP
  python = Python
  scala = Scala
  scss = SCSS
  ruby = Ruby
  svg = SVG
  textile = Textile
  xml = XML
```

... and any other `mode-*.js` file available in `\libraries\ace`

- `height`:  300px, 75% etc.
- `width`: 100%, 600px etc.
- `font-size`: All compatible CSS values for font-size
- `line-numbers`: 1 or 0 (on/off)
- `print-margin`: 1 or 0 (on/off)
- `invisibles`: 1 or 0 (on/off)

### Examples:

```
<ace theme="textmate" height="200px" font-size="12pt" print-margins="1">
<ace theme="twilight" syntax="php" height="200px" width="50%">
<ace height="100px" width="100%" invisibles="1">
```

**Known limitations**

- Support multiple fields using the AceFormatter with different settings
  (see https://www.drupal.org/project/ace_editor/issues/2999328)
- 'auto' height in Ace formatter
  (see https://www.drupal.org/project/ace_editor/issues/2846046)


## Maintainers
- Growiel - [growiel](https://www.drupal.org/u/growiel)
- nylin - [nylin](https://www.drupal.org/user/816040)
- Anatoly Kirillov - [kiritoly](https://www.drupal.org/u/kiritoly)
- Gonzalo Torrevejano - [interdruper](https://www.drupal.org/u/interdruper)
- Boby aloysius johnson - [boaloysius](https://www.drupal.org/u/boaloysius)
- Rajab Natshah - [rajab-natshah](https://www.drupal.org/u/rajab-natshah)