# Colorbox

Colorbox is a light-weight, customizable lightbox plugin for jQuery 1.4.3+.
This module allows for integration of Colorbox into Drupal.
The jQuery library is a part of Drupal since version 5+.

- [jQuery](https://jquery.com/)
- [Colorbox](https://www.jacklmoore.com/colorbox/)


## Contents of this file

- Features
- Requirements
- Optional HTML captions
- Installation
- Configuration
- Add a custom Colorbox style to your theme
- Drush


## Features

The Colorbox module:

- Works as a Formatter in entities and in views.
- Excellent integration with core image field and image styles and the Insert
  module
- Choose between a default style and a number of other styles that are included.
- Style the Colorbox with a custom Colorbox style in your theme.
- Drush command, drush colorbox-plugin, to download and install the Colorbox
  plugin in "libraries/".

The Colorbox plugin:

-  Compatible with: jQuery 1.3.2+ in Firefox, Safari, Chrome, Opera, Internet
  Explorer 7+
- Supports photos, grouping, slideshow, ajax, inline, and iframed content.
- Lightweight: 10KB of JavaScript (less than 5KBs gzipped).
- Appearance is controlled through CSS so it can be restyled.
- Can be extended with callbacks & event-hooks without altering the source
  files.
- Completely unobtrusive, options are set in the JS and require no changes to
  existing HTML.
- Preloads upcoming images in a photo group.
- Currently used on more than 2 million websites.
- Released under the MIT License.


## Requirements

Just [Colorbox](https://www.jacklmoore.com/colorbox/) plugin in "libraries".


## Optional HTML captions

Colorbox allows you to place a caption at the bottom of the lightbox.
If you wish to use HTML in your captions, you must install the DOMPurify
library. In your libraries folder, you will need
DOMPurify/dist/purify.min.js.

You can install DOMPurify using drush:
`drush colorbox:dompurify`

Or, if you prefer, you can download DOMPurify directly from:
[DOMPurify](https://github.com/cure53/DOMPurify/releases/latest)

From the above link, you can download a zip or tar.gz archive file.
To avoid security issues, only install the dist directory, and nothing else from
the archive. The drush command above only installs the dist directory.

The DOMPurify library is optional. Without DOMPurify, the Colorbox module
will convert all captions to plain text.


## Installation


1. Install the module as normal, see link for instructions.
   [Link](https://www.drupal.org/documentation/install/modules-themes/modules-8)

2. Download and unpack the Colorbox plugin in "libraries" inside root folder.
   Make sure the path to the plugin file becomes:
   `libraries/colorbox/jquery.colorbox-min.js`
   [Colorbox plugin link](https://github.com/jackmoore/colorbox/archive/master.zip)
   Drush users can use the command `drush colorbox-plugin`.

3. Change the permission of colorbox plugin inside 'libraries' folder.
   Right click on 'libraries' folder -> properties -> "permissions"
   and change to full permission.

4. Go to "Administer" -> "Extend" and enable the Colorbox module.


## Configuration


- Go to "Configuration" -> "Media" -> "Colorbox" to find all the configuration
  options.


## Add a custom Colorbox style to your theme

The easiest way is to start with either the default style or one of the example
styles included in the Colorbox JS library download. Simply copy the entire
style folder to your theme and rename it to something logical like "mycolorbox".
Inside that folder are both a .css and .js file, rename both of those as well to
match your folder name: i.e. "colorbox_mycolorbox.css" and
"colorbox_mycolorbox.js"

Add entries in your theme's .info file for the Colorbox CSS/JS files:

stylesheets[all][] = mycolorbox/colorbox_mycolorbox.css
scripts[] = mycolorbox/colorbox_mycolorbox.js

Go to "Configuration" -> "Media" -> "Colorbox" and select "None" under
"Styles and Options". This will leave the styling of Colorbox up to your theme.
Make any CSS adjustments to your "colorbox_mycolorbox.css" file.

## Use Responsive Images

Choose "Colorbox Responsive" as your image formatter. Standalone, only the
content/trigger image can make use of responsive image styles. If you install
the colorbox_inline module, responsive image styles will be available for the
Colorbox image as well. Link: https://www.drupal.org/project/colorbox_inline

## Drush

A Drush command is provided for easy installation of the Colorbox plugin itself.

- `drush colorbox-plugin`

The command will download the plugin and unpack it in "libraries/".
It is possible to add another path as an option to the command, but not
recommended unless you know what you are doing.


## Required Drupal core version

Drupal 9.3+ or 10.x


## License
GPL (see LICENSE)
