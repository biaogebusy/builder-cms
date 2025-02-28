CodeSnippet

CKEditor 5 Note
===============

CodeSnippet now has an upgrade path to using CKEditor 5 in Drupal core. After upgrading, you no longer need the
CodeSnippet module for CKEditor 4. See this URL for more information:

https://www.drupal.org/project/drupal/issues/3356929

Installation
============

This module requires the core CKEditor module and the CodeSnippet plugin
from CKEditor.com.

1. Download the CodeSnippet plugin (AT LEAST version 4.5.11) from
   http://ckeditor.com/addon/codesnippet.
2. Place the plugin folder in the root libraries folder
   (/libraries/codesnippet).
3. Enable CodeSnippet in the Drupal admin.
4. Configure your WYSIWYG toolbar to include the buttons.

Basic Usage
===========

After completing the installation steps above, go to the filter format you
want to configure (must be using CKEditor).

CodeSnippet:

Drag the CodeSnippet icon into the active toolbar, and the config form will
appear below with a syntax highlighting style and supported languages
option.  By default, all are checked for you.  Uncheck ones you won't need,
it's optional.  This only controls the options in the dialog window of
CKEditor when inserting a code snippet.

Note that your filter format must support the use of pre and code tags under
allowed tags as well, if using anything other than Full HTML.  You also need
to configure the HTML filter (if Limit Allowed Tags is enabled) to allow the
class attribute like so:

  <code class=""> <pre class="">

Supporting Multiple Stylesheets
===============================

While this module allows each filter format to configure a stylesheet for
highlighting, the HLJS plugin does not yet support this feature.  See this
issue for more details, including a possible workaround to implementing it
in your own style:

https://github.com/isagalaev/highlight.js/issues/862

If you are using multiple filter formats on a page, note that the highest
weighted filter formats settings will be added to the page last and
therefore that style will override any of the other HLJS styles selected in
other formats.

For now, it is best to only configure one format for highlighting, or, use
the same style library for all formats.

CodeSnippet Supported Languages
===============================

To add new options to the supported languages option in the admin form, you
can use a form alter hook within your own custom module to add on:

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 * Add extra languages for CodeSnippet
 * @param $form
 * @param FormStateInterface $form_state
 * @param $form_id
 */
function MYMODULE_form_filter_format_edit_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  if (isset($form['editor']['settings']['subform']['plugins']['codesnippet'])) {
    $form['editor']['settings']['subform']['plugins']['codesnippet']['highlight_languages']['#options']['cpp'] = 'C++';
    $form['editor']['settings']['subform']['plugins']['codesnippet']['highlight_languages']['#options']['d'] = 'D';
    $form['editor']['settings']['subform']['plugins']['codesnippet']['highlight_languages']['#options']['rust'] = 'Rust';
    asort($form['editor']['settings']['subform']['plugins']['codesnippet']['highlight_languages']['#options']);
  }
}

This would add C++, D, and Rust to the list of languages to check off, which
will then make them available in the dialog of CKEditor CodeSnippet.

An important thing to note is that the key of the array item needs to match
the expected code class for HighlightJS for proper coloring.  If you are
unsure of the class name, refer to the HighlightJS live demo page and
inspect the codeblock of what you want, and check the class on the code HTML
element.

Additionally, you will need to add any new languages to HighlightJS by
customizing its build.  You can customize the build at
https://highlightjs.org/download/

1. Select all the languages you want to support with syntax highlighting
   and download it.
2. Overwrite /libraries/codesnippet/lib/highlight/highlight.pack.js with
   this new file.
3. Clear Drupal caches.

Note that code previews syntax highlighting may not look 100% right (in the
WYSIWYG), but typically will when viewed on the frontend.

Out of the box, the included version of highlightjs comes with these
languages (as defined in config/install/codesnippet.settings.yml):

languages:
  apache: 'Apache'
  bash: 'Bash'
  coffeescript: 'CoffeeScript'
  css: 'CSS'
  dart: 'Dart'
  dockerfile: 'Dockerfile'
  dust: 'Dust'
  gherkin: 'Gherkin'
  go: 'Go'
  haml: 'HAML'
  handlebars: 'Handlebars'
  ini: 'Ini'
  java: 'Java'
  javascript: 'JavaScript'
  json: 'JSON'
  less: 'Less'
  makefile: 'Makefile'
  markdown: 'Markdown'
  nginx: 'Nginx'
  php: 'PHP'
  perl: 'Perl'
  powershell: 'Powershell'
  puppet: 'Puppet'
  python: 'Python'
  ruby: 'Ruby'
  scss: 'SCSS'
  sql: 'SQL'
  twig: 'Twig'
  typescript: 'TypeScript'
  yaml: 'Yaml'
  xml: 'XML'

Note that if you want to highlight HTML code snippets, you need to use the
XML option.  It works for both.
