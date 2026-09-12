<?php

namespace Drupal\ace_editor\Plugin\Filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Filters implementation for Ace Editor.
 *
 * @Filter(
 *   id = "ace_filter",
 *   title = @Translation("Ace Filter"),
 *   description = @Translation("Use @open and @close tags to show it with syntax highlighting. Add attributes to the @open tag to control formatting, see the module's documentation for examples.", arguments = {"@open" = "<ace>", "@close" = "</ace>"}),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 *   settings = {
 *           "theme" = "cobalt",
 *           "syntax" = "html",
 *           "height" = "500px",
 *           "width" = "700px",
 *           "font_size" = "12pt",
 *           "line_numbers" = TRUE,
 *           "show_invisibles" = FALSE,
 *           "print_margins" = TRUE,
 *           "auto_complete" = TRUE,
 *           "use_wrap_mode" = TRUE,
 *     }
 * )
 */
class AceFilter extends FilterBase {

  /**
   * Setting form for filters.
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $settings = $this->settings;
    $config = \Drupal::config('ace_editor.settings');

    return [
      'theme' => [
        '#type' => 'select',
        '#title' => $this->t('Theme'),
        '#options' => $config->get('theme_list'),
        '#attributes' => [
          'style' => 'width: 150px;',
        ],
        '#default_value' => $settings['theme'],
      ],
      'syntax' => [
        '#type' => 'select',
        '#title' => $this->t('Syntax'),
        '#description' => $this->t('The syntax that will be highlighted.'),
        '#options' => $config->get('syntax_list'),
        '#attributes' => [
          'style' => 'width: 150px;',
        ],
        '#default_value' => $settings['syntax'],
      ],
      'height' => [
        '#type' => 'textfield',
        '#title' => $this->t('Height'),
        '#description' => $this->t('The height of the editor in either pixels or percents.'),
        '#attributes' => [
          'style' => 'width: 100px;',
        ],
        '#default_value' => $settings['height'],
      ],
      'width' => [
        '#type' => 'textfield',
        '#title' => $this->t('Width'),
        '#description' => $this->t('The width of the editor in either pixels or percents.'),
        '#attributes' => [
          'style' => 'width: 100px;',
        ],
        '#default_value' => $settings['width'],
      ],
      'font_size' => [
        '#type' => 'textfield',
        '#title' => $this->t('Font size'),
        '#description' => $this->t('The the font size of the editor.'),
        '#attributes' => [
          'style' => 'width: 100px;',
        ],
        '#default_value' => $settings['font_size'],
      ],
      'line_numbers' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show line numbers'),
        '#default_value' => $settings['line_numbers'],
      ],
      'print_margins' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show print margin (80 chars)'),
        '#default_value' => $settings['print_margins'],
      ],
      'show_invisibles' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show invisible characters (whitespaces, EOL...)'),
        '#default_value' => $settings['show_invisibles'],
      ],
      'use_wrap_mode' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Toggle word wrapping'),
        '#default_value' => $settings['use_wrap_mode'],
      ],
    ];
  }

  /**
   * Processing the filters and return the processed result.
   */
  public function process($text, $langcode) {
    // The settings and libraries are built for THIS text only, so a filtered
    // field carries exactly its own snippets. A previous version accumulated
    // every field on the page in a drupal_static, which a render-cached field
    // then re-emitted with the wrong instances.
    $js_settings = [
      'instances' => [],
      'theme_settings' => $this->getConfiguration()['settings'],
    ];
    // Collect the theme/mode libraries of every tag; declared before the loop
    // so an earlier tag's libraries are not dropped by a later one.
    $attach_lib = [];

    // Do NOT html_entity_decode the whole text: that would reverse the
    // sanitization of any filter that ran before this one (for example
    // "Limit allowed HTML tags"). Only the snippet content inside an <ace>
    // tag is decoded below, and it is handed to Ace as text, never as HTML.
    if (preg_match_all("/<ace.*?>(.*?)\s*<\/ace>/s", $text, $match)) {

      foreach ($match[0] as $key => $value) {
        // Generate a truly unique id to append as element ID.
        $unique_id = uniqid();
        $element_id = 'ace-editor-inline' . $unique_id;
        $content = html_entity_decode(trim($match[1][$key], "\n\r\0\x0B"));
        $replace = '<pre id="' . $element_id . '"></pre>';
        // Override settings with attributes on the tag.
        $settings = $this->getConfiguration()['settings'];

        foreach ($this->tagAttributes('ace', $value) as $attribute_key => $attribute_value) {
          $settings[$attribute_key] = $attribute_value;

          if ($attribute_key == "theme" && \Drupal::service('library.discovery')->getLibraryByName('ace_editor', 'theme.' . $attribute_value)) {
            $attach_lib[] = "ace_editor/theme." . $attribute_value;
          }
          if ($attribute_key == "syntax" && \Drupal::service('library.discovery')->getLibraryByName('ace_editor', 'mode.' . $attribute_value)) {
            $attach_lib[] = "ace_editor/mode." . $attribute_value;
          }
        }

        // Append this instance to js settings data structure.
        $js_settings['instances'][] = [
          'id' => $element_id,
          'content' => $content,
          'settings' => $settings,
        ];
        $text = $this->strReplaceOnce($value, $replace, $text);
      }

      $result = new FilterProcessResult($text);
      $attach_lib[] = 'ace_editor/filter';
      $result->setAttachments(
        [
          'library' => $attach_lib,
          'drupalSettings' => [
            // Pass settings variable ace_formatter to javascript.
            'ace_filter' => $js_settings,
          ],
        ]
      );

      return $result;
    }

    $result = new FilterProcessResult($text);
    return $result;
  }

  /**
   * Get all attributes of an <ace> tag in key/value pairs.
   *
   * @return array
   *   The attributes, keyed by name, empty when the tag carries none.
   */
  public function tagAttributes($element_name, $xml) {
    // Grab the string of attributes inside the editor tag.
    $found = preg_match('#<' . $element_name . '\s+([^>]+(?:"|\'))\s?/?>#', $xml, $matches);

    if ($found == 1) {
      $attribute_array = [];
      $attribute_string = $matches[1];
      // Match attribute-name attribute-value pairs.
      $found = preg_match_all('#([^\s=]+)\s*=\s*(\'[^<\']*\'|"[^<"]*")#', $attribute_string, $matches, PREG_SET_ORDER);
      if ($found != 0) {
        // Create an associative array that matches attribute names
        // with their values.
        foreach ($matches as $attribute) {
          $value = substr($attribute[2], 1, -1);
          // Normalise boolean-ish attribute values. intval() alone turned
          // "TRUE" into 0, the opposite of what the author wrote.
          $lower = strtolower($value);
          if ($lower === '1' || $lower === 'true') {
            $value = 1;
          }
          elseif ($lower === '0' || $lower === 'false') {
            $value = 0;
          }
          $attribute_array[str_replace('-', '_', $attribute[1])] = $value;
        }
        return $attribute_array;
      }
    }
    // A tag without attributes, or attributes the regular expression could not
    // extract, is simply an empty set: the caller iterates over the result.
    return [];
  }

  /**
   * Custom function to replace the code only once.
   *
   * Probably not the most efficient way, but at least it works.
   */
  public function strReplaceOnce($needle, $replace, $haystack) {
    // Looks for the first occurence of $needle in $haystack
    // and replaces it with $replace.
    $pos = strpos($haystack, $needle);
    if ($pos === FALSE) {
      // Nothing found.
      return $haystack;
    }
    return substr_replace($haystack, $replace, $pos, strlen($needle));
  }

}
