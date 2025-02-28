<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\Utility\DataTypeHelperInterface;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

/**
 * Strips HTML tags from fulltext fields and decodes HTML entities.
 *
 * @SearchApiProcessor(
 *   id = "html_filter",
 *   label = @Translation("HTML filter"),
 *   description = @Translation("Strips HTML tags from fulltext fields and decodes HTML entities. Use this processor when indexing HTML data – for example, node bodies for certain text formats. The processor also allows to boost (or ignore) the contents of specific elements."),
 *   stages = {
 *     "pre_index_save" = 0,
 *     "preprocess_index" = -15,
 *     "preprocess_query" = -15,
 *   }
 * )
 */
class HtmlFilter extends FieldsProcessorPluginBase {

  /**
   * The data type helper.
   *
   * @var \Drupal\search_api\Utility\DataTypeHelperInterface|null
   */
  protected $dataTypeHelper;

  /**
   * Retrieves the data type helper.
   *
   * @return \Drupal\search_api\Utility\DataTypeHelperInterface
   *   The data type helper.
   */
  public function getDataTypeHelper() {
    return $this->dataTypeHelper ?: \Drupal::service('search_api.data_type_helper');
  }

  /**
   * Sets the data type helper.
   *
   * @param \Drupal\search_api\Utility\DataTypeHelperInterface $data_type_helper
   *   The new data type helper.
   *
   * @return $this
   */
  public function setDataTypeHelper(DataTypeHelperInterface $data_type_helper) {
    $this->dataTypeHelper = $data_type_helper;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();

    $configuration += [
      'title' => TRUE,
      'alt' => TRUE,
      'tags' => [
        'h1' => 5,
        'h2' => 3,
        'h3' => 2,
        'strong' => 2,
        'b' => 2,
        'em' => 1.5,
        'u' => 1.5,
      ],
    ];

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Index title attribute'),
      '#description' => $this->t('If set, the contents of title attributes will be indexed.'),
      '#default_value' => $this->configuration['title'],
    ];

    $form['alt'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Index alt attribute'),
      '#description' => $this->t('If set, the alternative text of images will be indexed.'),
      '#default_value' => $this->configuration['alt'],
    ];

    $dumper = new Dumper();
    $tags = $dumper->dump($this->configuration['tags'], 2);
    $tags = str_replace('\r\n', "\n", $tags);
    $tags = str_replace('"', '', $tags);

    $t_args[':url'] = Url::fromUri('https://en.wikipedia.org/wiki/YAML')->toString();
    $form['tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Tag boosts'),
      '#description' => $this->t('Specify special boost values for certain HTML elements, in <a href=":url">YAML file format</a>. The boost values of nested elements are multiplied, elements not mentioned will have the default boost value of 1. Assign a boost of 0 to ignore the text content of that HTML element.', $t_args),
      '#default_value' => $tags,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $tags = trim($form_state->getValue('tags'));
    if (!$tags) {
      $form_state->setValue('tags', []);
      return;
    }
    $errors = [];
    try {
      $parser = new Parser();
      $tags = $parser->parse($tags);
      if (!is_array($tags)) {
        $errors[] = $this->t('Tags is not a valid YAML map. See @link for information on how to write correctly formed YAML.', ['@link' => 'http://yaml.org']);
        $tags = [];
      }
    }
    catch (ParseException $exception) {
      $errors[] = $this->t('Tags is not a valid YAML map. See @link for information on how to write correctly formed YAML.', ['@link' => 'http://yaml.org']);
      $tags = [];
    }
    foreach ($tags as $key => $value) {
      $tag = "<$key>";
      if (is_array($value)) {
        $errors[] = $this->t("Boost value for tag @tag can't be an array.", ['@tag' => $tag]);
      }
      elseif (!is_numeric($value)) {
        $errors[] = $this->t('Boost value for tag @tag must be numeric.', ['@tag' => $tag]);
      }
      elseif ($value < 0) {
        $errors[] = $this->t('Boost value for tag @tag must be non-negative.', ['@tag' => $tag]);
      }
      elseif ($value == 1) {
        unset($tags[$key]);
      }
      else {
        $tags[$key] = (float) $value;
      }
    }
    $form_state->setValue('tags', $tags);
    if ($errors) {
      $message = array_shift($errors);
      foreach ($errors as $error) {
        $args = [
          '@message1' => $message,
          '@message2' => $error,
        ];
        $message = new FormattableMarkup('@message1<br />@message2', $args);
      }
      $form_state->setError($form['tags'], $message);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function processField(FieldInterface $field) {
    parent::processField($field);

    foreach ($field->getValues() as $value) {
      if ($value instanceof TextValueInterface) {
        $value->setProperty('strip_html');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function processFieldValue(&$value, $type) {
    // Remove invisible content.
    $text = preg_replace('@<(applet|audio|canvas|command|embed|iframe|map|menu|noembed|noframes|noscript|script|style|svg|video)[^>]*>.*</\1>@siU', ' ', $value);
    $is_text_type = $this->getDataTypeHelper()->isTextType($type);
    if ($is_text_type) {
      // Let removed tags still delimit words.
      $text = str_replace(['<', '>'], [' <', '> '], $text);
      $text = $this->handleAttributes($text);
    }
    if ($this->configuration['tags'] && $is_text_type) {
      $text = strip_tags($text, '<' . implode('><', array_keys($this->configuration['tags'])) . '>');
      $value = $this->parseHtml($text);
    }
    else {
      $text = strip_tags($text);
      $value = $this->normalizeText(trim($text));
    }
  }

  /**
   * Copies configured attributes out of HTML tags so they are indexed.
   *
   * @param string $text
   *   The text to process, with spaces added around all HTML tags.
   *
   * @return string
   *   The same text, with the contents of attributes "alt" and/or "title" (as
   *   configured) copied into their element contents so they can be indexed.
   */
  protected function handleAttributes(string $text): string {
    // Determine which attributes should be indexed and bail early if it's none.
    $handled_attributes = [];
    foreach (['alt', 'title'] as $attr) {
      if ($this->configuration[$attr]) {
        $handled_attributes[] = $attr;
      }
    }
    if (!$handled_attributes) {
      return $text;
    }

    $processed_text = '';
    $pos = 0;
    $text_len = mb_strlen($text);
    // Go through the whole text, looking for HTML tags.
    while ($pos < $text_len) {
      // Find start of HTML tag.
      // Since there is always a space in front of a "<" character, we do not
      // need to write "$start_pos === FALSE" explicitly to check for a match.
      $start_pos = mb_strpos($text, '<', $pos);
      // Add everything from the last position to this start tag (or the end of
      // the string, if we found none) to the processed text.
      $processed_text .= mb_substr($text, $pos, $start_pos ? $start_pos - $pos : NULL);
      if (!$start_pos) {
        break;
      }

      // Find end of HTML tag.
      // As above for $start_pos, $end_pos cannot be 0 since it must be greater
      // than $start_pos. So, no need to check for FALSE strictly.
      $end_pos = mb_strpos($text, '>', $start_pos + 1);
      // Extract the contents of the tag, and add it to the processed text.
      $tag_contents = mb_substr($text, $start_pos, $end_pos ? $end_pos + 1 - $start_pos : NULL);
      $processed_text .= $tag_contents;
      if (!$end_pos) {
        break;
      }
      // Next, we want to begin searching right after the end of this HTML tag.
      $pos = $end_pos + 1;

      // Split the tag contents, without the angle brackets, into the element
      // name and the rest.
      $tag_contents = trim($tag_contents, '<> ');
      [$element_name, $tag_contents] = explode(' ', $tag_contents, 2) + [1 => NULL];
      // If there is just the element name, no need to look for attributes.
      if (!$tag_contents) {
        continue;
      }

      // This will match all the attributes we're looking for.
      $attr_regex = '(?:' . implode('|', $handled_attributes) . ')';
      $pattern = "/(?:^|\s)$attr_regex\s*+=\s*+(['\"])/Su";
      $flags = PREG_OFFSET_CAPTURE | PREG_SET_ORDER;
      if (preg_match_all($pattern, $tag_contents, $matches, $flags)) {
        foreach ($matches as $match) {
          // Now just extract the attribute value as everything between the
          // matched quote character and the next such character.
          // Unfortunately, preg_match_all() reports positions in bytes, not
          // characters, so we need to use a bit of magic to reconcile this with
          // our usual handling of Unicode.
          $quote_char = $match[1][0];
          /** @var int $quote_pos */
          $quote_pos = $match[1][1];
          $tag_contents_from_quote = substr($tag_contents, $quote_pos + 1);
          $length = mb_strpos($tag_contents_from_quote, $quote_char);
          $attr_value = mb_substr($tag_contents_from_quote, 0, $length);
          // Take care of self-closing tags, so users are still able to set a
          // boost for, for instance, the "alt" attribute from an "img" tag.
          if ($tag_contents[-1] === '/') {
            $attr_value = " <$element_name> $attr_value </$element_name>";
          }
          $processed_text .= ' ' . $attr_value;
        }
      }
    }

    return $processed_text;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    $value = str_replace(['<', '>'], [' <', '> '], $value);
    $value = strip_tags($value);
    $value = $this->normalizeText($value);
  }

  /**
   * Tokenizes an HTML string according to the HTML elements.
   *
   * Assigns boost values to the elements' contents accordingly.
   *
   * @param string $text
   *   The HTML string to parse, passed by reference. After the method call, the
   *   variable will contain the portion of the string after the current
   *   element, or an empty string (if there is no current element).
   * @param string|null $active_tag
   *   (optional) The currently active tag, for which a closing tag has to be
   *   found. Internal use only.
   * @param float $boost
   *   (optional) The currently active boost value. Internal use only.
   *
   * @return \Drupal\search_api\Plugin\search_api\data_type\value\TextTokenInterface[]
   *   Tokenized text with appropriate scores.
   */
  protected function parseHtml(&$text, $active_tag = NULL, $boost = 1.0) {
    $ret = [];
    while (($pos = strpos($text, '<')) !== FALSE) {
      $text_before = substr($text, 0, $pos);
      $text_after = substr($text, $pos + 1);
      // Attempt some small error tolerance when literal "<" characters aren't
      // escaped properly (and are free-standing).
      if (!preg_match('#^(/?)([-:_a-zA-Z0-9]+)#', $text_after, $m)) {
        $text = $text_before . '&lt;' . $text_after;
        continue;
      }
      if ($boost && $pos > 0) {
        $value = $this->normalizeText($text_before);
        if ($value !== '') {
          $ret[] = Utility::createTextToken($value, $boost);
        }
      }
      $text = $text_after;
      $pos = strpos($text, '>');
      $empty_tag = $text[$pos - 1] == '/';
      $text = substr($text, $pos + 1);
      if ($m[1]) {
        // Closing tag.
        if ($active_tag && $m[2] == $active_tag) {
          return $ret;
        }
      }
      elseif (!$empty_tag) {
        // Opening tag => recursive call.
        $inner_boost = $boost * ($this->configuration['tags'][$m[2]] ?? 1);
        $ret = array_merge($ret, $this->parseHtml($text, $m[2], $inner_boost));
      }
    }
    if ($text) {
      $value = $this->normalizeText($text);
      if ($value !== '') {
        $ret[] = Utility::createTextToken($value, $boost);
      }
      $text = '';
    }
    return $ret;
  }

  /**
   * Removes superfluous whitespace and unescapes HTML entities.
   *
   * @param string $value
   *   The text to process.
   *
   * @return string
   *   The text without unnecessary whitespace and HTML entities transformed
   *   back to plain text.
   */
  protected function normalizeText($value) {
    $value = Html::decodeEntities($value);
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return $value;
  }

}
