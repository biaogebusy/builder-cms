<?php

namespace Drupal\xmlsitemap;

use Drupal\Component\Utility\Html;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;

/**
 * Extended class for writing XML Sitemap files.
 */
class XmlSitemapWriter extends \XMLWriter {

  /**
   * Document URI.
   *
   * @var string
   */
  protected $uri = NULL;

  /**
   * Counter for the sitemap elements.
   *
   * @var int
   */
  protected $sitemapElementCount = 0;

  /**
   * Flush counter for sitemap links.
   *
   * @var int
   */
  protected $linkCountFlush = 500;

  /**
   * Sitemap object to be written.
   *
   * @var \Drupal\xmlsitemap\XmlSitemapInterface
   */
  protected $sitemap;

  /**
   * Sitemap page to be written.
   *
   * @var int|string
   */
  protected $page;

  /**
   * Constructors and XmlSitemapWriter object.
   *
   * @param \Drupal\xmlsitemap\XmlSitemapInterface $sitemap
   *   The XML Sitemap.
   * @param int|string $page
   *   The current page of the sitemap being generated.
   *
   * @throws \InvalidArgumentException
   *   If the page is invalid.
   * @throws \Drupal\xmlsitemap\XmlSitemapGenerationException
   *   If the file URI cannot be opened.
   */
  public function __construct(XmlSitemapInterface $sitemap, $page) {
    if ($page !== 'index' && !filter_var($page, FILTER_VALIDATE_INT)) {
      throw new \InvalidArgumentException("Invalid XML Sitemap page $page.");
    }

    $this->sitemap = $sitemap;
    $this->page = $page;
    $this->uri = xmlsitemap_sitemap_get_file($sitemap, $page);
    $this->openUri($this->uri);
  }

  /**
   * {@inheritdoc}
   *
   * @throws XmlSitemapGenerationException
   *   If the file URI cannot be opened.
   */
  public function openUri(string $uri): bool {
    $return = parent::openUri($uri);
    if (!$return) {
      throw new XmlSitemapGenerationException("Could not open file $uri for writing.");
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   *
   * @throws XmlSitemapGenerationException
   *   Throws exception when document cannot be started.
   */
  public function startDocument(?string $version = '1.0', ?string $encoding = 'UTF-8', ?string $standalone = NULL): bool {
    $this->setIndent(FALSE);
    $result = parent::startDocument($version, $encoding);
    if (!$result) {
      throw new XmlSitemapGenerationException("Unknown error occurred while writing to file {$this->uri}.");
    }
    if (\Drupal::config('xmlsitemap.settings')->get('xsl')) {
      $this->writeXsl();
    }
    $result &= $this->startElement($this->isIndex() ? 'sitemapindex' : 'urlset', TRUE);
    return $result;
  }

  /**
   * Adds the XML stylesheet to the XML page.
   */
  public function writeXsl() {
    $xls_url = Url::fromRoute('xmlsitemap.sitemap_xsl')->toString();
    $settings = \Drupal::config('language.negotiation');
    if ($settings) {
      $url_settings = $settings->get('url');
      if (isset($url_settings['source']) && $url_settings['source'] == 'domain') {
        $scheme = \Drupal::request()->getScheme();
        $context = $this->sitemap->getContext();
        $base_url = $scheme . '://' . $url_settings['domains'][$context['language']];
        $xls_url = Url::fromRoute('xmlsitemap.sitemap_xsl');
        $xls_url = $base_url . '/' . $xls_url->getInternalPath();
      }
    }
    $this->writePi('xml-stylesheet', 'type="text/xsl" href="' . $xls_url . '"');
    $this->writeRaw(PHP_EOL);
  }

  /**
   * Return an array of attributes for the root element of the XML.
   *
   * @return array
   *   Returns root attributes.
   */
  public function getRootAttributes() {
    $attributes['xmlns'] = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    // @todo Should content_moderation implement hook_xmlsitemap_root_attributes_alter() instead?
    $attributes['xmlns:xhtml'] = 'http://www.w3.org/1999/xhtml';
    if (\Drupal::state()->get('xmlsitemap_developer_mode')) {
      $attributes['xmlns:xsi'] = 'http://www.w3.org/2001/XMLSchema-instance';
      $attributes['xsi:schemaLocation'] = 'http://www.sitemaps.org/schemas/sitemap/0.9';
      if ($this->isIndex()) {
        $attributes['xsi:schemaLocation'] .= ' http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd';
      }
      else {
        $attributes['xsi:schemaLocation'] .= ' http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd';
      }
    }

    \Drupal::moduleHandler()->alter('xmlsitemap_root_attributes', $attributes, $this->sitemap);

    return $attributes;
  }

  /**
   * {@inheritdoc}
   *
   * @param bool $root
   *   Specify if it is root element or not.
   */
  public function startElement(string $name, $root = FALSE): bool{
    $return = parent::startElement($name);

    if ($return && $root) {
      foreach ($this->getRootAttributes() as $key => $value) {
        $return &= $this->writeAttribute($key, $value);
      }
      $return &= $this->writeRaw(PHP_EOL);
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   *
   * @param string|array $content
   *   The element contents or an array of the elements' sub-elements.
   */
  public function writeElement(string $name, string|array|null $content = NULL): bool {
    if (is_array($content)) {
      $return = $this->startElement($name);
      $return &= $this->writeRaw($this->formatXmlElements($content));
      $return &= $this->endElement();
    }
    else {
      $return = parent::writeElement($name, Html::escape(static::toString($content)));
    }
    $return &= $this->writeRaw(PHP_EOL);

    // After a certain number of elements have been added, flush the buffer
    // to the output file.
    $this->sitemapElementCount++;
    if (($this->sitemapElementCount % $this->linkCountFlush) == 0) {
      $this->flush();
    }
    return $return;
  }

  /**
   * Getter of the document uri.
   *
   * @return string
   *   Document uri.
   */
  public function getUri() {
    return $this->uri;
  }

  /**
   * Getter of the element count.
   *
   * @return int
   *   Element counters.
   */
  public function getSitemapElementCount() {
    return $this->sitemapElementCount;
  }

  /**
   * {@inheritdoc}
   *
   * @throws XmlSitemapGenerationException
   */
  public function endDocument(): bool {
    $return = parent::endDocument();

    if (!$return) {
      throw new XmlSitemapGenerationException("Unknown error occurred while writing to file {$this->uri}.");
    }

    if (xmlsitemap_var('gz')) {
      $file_gz = $this->uri . '.gz';
      file_put_contents($file_gz, gzencode(file_get_contents($this->uri), 9));
    }

    return $return;
  }

  /**
   * If the page being written is the index.
   *
   * @return bool
   *   TRUE if the sitemap index is being written, or FALSE otherwise.
   */
  protected function isIndex() {
    return $this->page === 'index';
  }

  /**
   * Copy of Drupal 7's format_xml_elements() function.
   *
   * The extra whitespace has been removed.
   *
   * @param array $array
   *   An array where each item represents an element and is either a:
   *   - (key => value) pair (<key>value</key>)
   *   - Associative array with fields:
   *     - 'key': element name
   *     - 'value': element contents
   *     - 'attributes': associative array of element attributes or an
   *       \Drupal\Core\Template\Attribute object
   *   In both cases, 'value' can be a simple string, or it can be another
   *   array with the same format as $array itself for nesting.
   *
   * @return string
   *   The XML output.
   */
  public static function formatXmlElements(array $array) {
    $output = '';
    foreach ($array as $key => $value) {
      if (is_numeric($key)) {
        if ($value['key']) {
          $output .= '<' . $value['key'];
          if (isset($value['attributes'])) {
            if (is_array($value['attributes'])) {
              $value['attributes'] = new Attribute($value['attributes']);
            }
            $output .= static::toString($value['attributes']);
          }
          if (isset($value['value']) && $value['value'] != '') {
            $output .= '>' . (is_array($value['value']) ? static::formatXmlElements($value['value']) : Html::escape(static::toString($value['value']))) . '</' . $value['key'] . '>';
          }
          else {
            $output .= ' />';
          }
        }
      }
      else {
        $output .= '<' . $key . '>' . (is_array($value) ? static::formatXmlElements($value) : Html::escape(static::toString($value))) . "</{$key}>";
      }
    }
    return $output;
  }

  /**
   * Convert translatable strings and URLs to strings.
   *
   * @param mixed $value
   *   The value to turn into a string.
   *
   * @return string
   *   The string value.
   */
  public static function toString($value) {
    if (is_object($value)) {
      if ($value instanceof Url) {
        return $value->toString();
      }
    }

    return (string) $value;
  }

}
