<?php

declare(strict_types=1);

namespace Drupal\metatag_custom_tags\Plugin\metatag\Tag;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Random;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom configured meta tags will be available.
 *
 * The meta tag's values will be based upon this annotation.
 *
 * @MetatagTag(
 *   id = "metatag_custom_tag",
 *   deriver = "Drupal\metatag_custom_tags\Plugin\Derivative\MetaTagCustomTagDeriver",
 *   label = @Translation("Custom Tag"),
 *   description = @Translation("This plugin will be cloned from these settings for each custom tag."),
 *   name = "metatag_custom_tag",
 *   weight = 1,
 *   group = "metatag_custom_tags",
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class MetaTagCustomTag extends MetaNameBase {

  /**
   * The attributes of this tag.
   *
   * @var array
   */
  protected $attributes;

  /**
   * The string this tag uses for the element itself.
   *
   * @var string
   */
  protected $htmlElement;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Additional elements.
    $this->htmlElement = $plugin_definition['htmlElement'] ?? 'meta';
    $this->htmlValueAttribute = $plugin_definition['htmlValueAttribute'] ?? '';
    $this->attributes = $plugin_definition['attributes'] ?? [];
  }

  /**
   * Generate the HTML tag output for a meta tag.
   *
   * @return array
   *   A render array.
   */
  public function output(): array {
    // If there is no value, just return either an empty array.
    if (is_null($this->value) || $this->value == '') {
      return [];
    }

    // Get configuration.
    $separator = $this->getSeparator();

    // If this contains embedded image tags, extract the image URLs.
    if ($this->type() === 'image') {
      $value = $this->parseImageUrl($this->value);
    }
    else {
      $value = PlainTextOutput::renderFromHtml($this->value);
    }

    $values = $this->multiple() ? explode($separator, $value) : [$value];
    $elements = [];
    foreach ($values as $value) {
      $value = $this->tidy($value);
      if ($value != '' && $this->requiresAbsoluteUrl()) {
        // Relative URL.
        if (parse_url($value, PHP_URL_HOST) == NULL) {
          $value = $this->request->getSchemeAndHttpHost() . $value;
        }
        // Protocol-relative URL.
        elseif (substr($value, 0, 2) === '//') {
          $value = $this->request->getScheme() . ':' . $value;
        }
      }

      // If tag must be secure, convert all http:// to https://.
      if ($this->secure() && strpos($value, 'http://') !== FALSE) {
        $value = str_replace('http://', 'https://', $value);
      }

      $value = $this->trimValue($value);

      $attributes = [];
      foreach ($this->attributes as $attribute) {
        $attributes[$attribute['name']] = $attribute['value'];
      }
      // Add value attribute.
      $attributes[$this->htmlValueAttribute] = $value;
      // Filter empty attributes.
      $attributes = array_filter($attributes);

      $elements[] = [
        '#tag' => $this->htmlElement,
        '#attributes' => $attributes,
      ];
    }

    return $this->multiple() ? $elements : reset($elements);
  }

  /**
   * The xpath string which identifies this meta tag presence on the page.
   *
   * @return array
   *   A list of xpath-formatted string(s) for matching a field on the page.
   */
  public function getTestOutputExistsXpath(): array {
    return ["//" . $this->htmlElement . "[@" . $this->attributes[0]['name'] . "='{$this->attributes[0]['value']}']"];
  }

  /**
   * The xpath string which identifies this meta tag's output on the page.
   *
   * @param array $values
   *   The field names and values that were submitted.
   *
   * @return array
   *   A list of xpath-formatted string(s) for matching a field on the page.
   */
  public function getTestOutputValuesXpath(array $values): array {
    $xpath_strings = [];
    foreach ($values as $value) {
      $xpath_strings[] = "//" . $this->htmlElement . "[@" . $this->attributes[0]['name'] . "='{$this->attributes[0]['value']}' and @" . $this->htmlValueAttribute . "='{$value}']";
    }
    return $xpath_strings;
  }

}
