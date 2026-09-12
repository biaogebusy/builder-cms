<?php

declare(strict_types=1);

namespace Drupal\Tests\blazy\FunctionalJavascript;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\blazy\Traits\BlazyCreationTestTrait;
use Drupal\Tests\blazy\Traits\BlazyUnitTestTrait;
use Drupal\blazy\BlazyApi;
use Drupal\filter\FilterPluginCollection;
use Drupal\filter\FilterProcessResult;

/**
 * Tests the Blazy Filter JavaScript using Selenium, or Chromedriver.
 */
/**
 * A D12 compat, please update or ignore.
 *
 * @phpstan-ignore-next-line
 */
#[Group('blazy')]
/**
 * A D12 compat, please update or ignore.
 *
 * @phpstan-ignore-next-line
 */
#[RunTestsInSeparateProcesses]
class BlazyFilterJavaScriptTest extends WebDriverTestBase {

  use BlazyUnitTestTrait;
  use BlazyCreationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $minkDefaultDriverClass = DrupalSelenium2Driver::class;

  /**
   * {@inheritdoc}
   */
  protected $imagePath;

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    'field',
    'filter',
    'image',
    'media',
    'node',
    'text',
    'blazy',
    'blazy_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpVariables();

    $this->root                   = $this->container->getParameter('app.root');
    $this->fileSystem             = $this->container->get('file_system');
    $this->entityFieldManager     = $this->container->get('entity_field.manager');
    $this->formatterPluginManager = $this->container->get('plugin.manager.field.formatter');
    $this->blazyAdmin             = $this->container->get('blazy.admin');
    $this->blazyOembed            = $this->container->get('blazy.oembed');
    $this->blazyManager           = $this->container->get('blazy.manager');
    $this->testPluginId           = 'blazy_filter';
    $this->maxParagraphs          = 120;

    $this->setupFilterFormat();
    if ($this->filterFormatFull) {
      $this->filterFormatFull->setFilterConfig('blazy_filter', [
        'status' => TRUE,
        'settings' => [
          'filter_tags' => [
            'img' => 'img',
            'iframe' => 'iframe',
          ],
        ],
      ]);
      $this->filterFormatFull->save();
    }

    $this->imagePath = $this->getImagePath(TRUE);

    $this->setUpRealImage();
  }

  /**
   * Test the Blazy filter has media-wrapper--blazy for IMG and IFRAME elements.
   */
  public function testFilterDisplay() {
    $text = $this->dummyText();
    $settings = BlazyApi::init();
    $settings['extra_text'] = $text;

    $this->setUpContentTypeTest($this->bundle);
    $this->setUpContentWithItems($this->bundle, $settings);

    $session = $this->getSession();

    $this->drupalGet('node/' . $this->entity->id());

    // Ensures Blazy is not loaded on page load.
    // @todo with Native lazyload, b-loaded is enforced on page load. And
    // since the testing browser Chrome support it, it is irrelevant.
    // @todo $this->assertSession()->elementNotExists('css', '.b-loaded');
    // Capture the initial page load moment.
    $this->createScreenshot($this->imagePath . '/1_blazy_filter_initial.png');

    // Wait a moment.
    $this->getSession()->wait(6000);
    $this->assertSession()->elementExists('css', '.b-lazy');

    // Trigger Blazy to load images by scrolling down window.
    $session->executeScript('window.scrollTo(0, document.body.scrollHeight);');

    // Wait a moment.
    $this->getSession()->wait(6000);

    // Capture the loading moment after scrolling down the window.
    $this->createScreenshot($this->imagePath . '/2_blazy_filter_loading.png');

    // Verifies that our filter works identified by media-wrapper--blazy class.
    $this->assertSession()->elementExists('css', '.media-wrapper--blazy');
    $this->assertSession()->elementContains('css', '.media-wrapper--blazy', 'b-lazy');

    // Verifies attributes and URIs are cleaned out.
    // The problem with raw attributes were discrete behaviors causing failed
    // lazy load, nothing related to skiddies businesses. It appears fixed since
    // blazy:2.17-beta1+, see #3374519.
    // See https://www.drupal.org/node/3129738.
    // See https://mink.behat.org/en/latest/guides/traversing-pages.html#css-selector.
    $this->assertSession()->elementExists('css', 'img[usemap]');
    $this->assertSession()->elementExists('css', 'img[data-onmouseover]');

    $this->assertSession()->elementNotExists('css', 'img[onmouseover]');
    $this->assertSession()->elementNotExists('css', 'img[alt*=strong]');

    // Already sanitized by text editor since D 10.6.2.
    // $this->assertSession()->elementExists('css', 'img[data-src^=alert]');
    // Verifies that we have data URI disallowed. Ensure to not too broad given
    // valid Blazy lazy-load post-processed placeholder.
    $this->assertSession()->elementNotExists('css', 'img[src^="data:image/jpg;base64"]');
    $this->assertSession()->elementNotExists('xpath', '//img[contains(@src, "data:image/jpg;base64")]');

    $this->assertSession()->elementExists('xpath', '//img[contains(@class, "width-full")]');

    // Also verifies that [data-unblazy] should not be touched, nor lazyloaded.
    $this->assertSession()->elementNotContains('css', '.media-wrapper--blazy', 'data-unblazy');

    // Verifies that one of the images is there once loaded.
    /** @phpstan-ignore-next-line */
    $loaded = $this->assertSession()->waitForElement('css', '.b-loaded');
    $this->assertNotEmpty($loaded);

    // Capture the loaded moment.
    // The screenshots are at sites/default/files/simpletest/blazy.
    $this->createScreenshot($this->imagePath . '/3_blazy_filter_loaded.png');

    // Verifies the library is loaded.
    /** @var \Drupal\filter\FilterProcessResult $result */
    ['result' => $result, 'html' => $html] = $this->applyFilter($text);
    $this->assertNotSame($html, $text);
    $attachments = $result->getAttachments();
    $this->assertContains('blazy/filter', $attachments['library']);
    $this->assertArrayHasKey('blazy', $attachments['drupalSettings']);
  }

  /**
   * Applies the `@Filter=blazy_fiter` filter to text, pipes to raw content.
   *
   * @param string $text
   *   The text string to be filtered.
   * @param string $identifier
   *   The any text which identifies this filter.
   * @param string $langcode
   *   The language code of the text to be filtered.
   *
   * @return array
   *   The filtered text, wrapped in a FilterProcessResult object, and possibly
   *   with associated assets, cacheability metadata and placeholders.
   */
  protected function applyFilter($text, $identifier = 'media-wrapper--blazy', $langcode = 'en') {
    $this->assertStringNotContainsString($identifier, $text);
    $result = $this->processText($text, $langcode);
    $html = $result->getProcessedText();
    $this->assertStringContainsString($identifier, $html);

    return ['result' => $result, 'html' => $html];
  }

  /**
   * Processes text through the provided filters, taken from media embed.
   *
   * @param string $text
   *   The text string to be filtered.
   * @param string $langcode
   *   The language code of the text to be filtered.
   * @param string[] $filter_ids
   *   (optional) The filter plugin IDs to apply to the given text, in the order
   *   they are being requested to be executed.
   *
   * @return \Drupal\filter\FilterProcessResult
   *   The filtered text, wrapped in a FilterProcessResult object, and possibly
   *   with associated assets, cacheability metadata and placeholders.
   *
   * @see \Drupal\filter\Element\ProcessedText::preRenderText()
   */
  protected function processText($text, $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED, array $filter_ids = ['blazy_filter']) {
    $manager = $this->container->get('plugin.manager.filter');
    $bag = new FilterPluginCollection($manager, []);
    $filters = [];
    foreach ($filter_ids as $filter_id) {
      $filters[] = $bag->get($filter_id);
    }

    $render_context = new RenderContext();
    /** @var \Drupal\filter\FilterProcessResult $filter_result */
    $filter_result = $this->container->get('renderer')->executeInRenderContext($render_context, function () use ($text, $filters, $langcode) {
      $metadata = new BubbleableMetadata();
      foreach ($filters as $filter) {
        /** @var \Drupal\filter\FilterProcessResult $result */
        $result = $filter->process($text, $langcode);
        $metadata = $metadata->merge($result);
        $text = $result->getProcessedText();
      }
      return (new FilterProcessResult($text))->merge($metadata);
    });
    if (!$render_context->isEmpty()) {
      $filter_result = $filter_result->merge($render_context->pop());
    }
    return $filter_result;
  }

  /**
   * Returns a dummy text.
   */
  protected function dummyText(): string {
    $uuid = $this->dummyItem->uuid();
    $text = '<div style="width: 640px;">';
    $text .= '<iframe src="https://www.youtube.com/watch?v=uny9kbh4iOEd" width="640" height="360"></iframe>';
    $text .= '<img src="' . $this->url . '" width="320" height="320" />';
    $text .= '<img src="' . $this->dummyUrl . '" width="320" height="320" data-entity-type="file" data-entity-uuid="' . $uuid . '"/>';
    $text .= '<img src="https://www.drupal.org/files/project-images/slick-carousel-drupal.png" width="215" height="162" />';
    $text .= '<img data-unblazy src="' . $this->url . '" width="320" height="320" />';
    $text .= '<IMG SRC="javascript:alert(\'XSS B\');">';
    $text .= '<IMG SRC=javascript:alert(\'XSS C\')>';
    $text .= '<IMG SRC=JaVaScRiPt:alert(\'XSS D\')>';
    $text .= '<IMG SRC=javascript:alert("XSS E")>';
    $text .= '<IMG SRC=`javascript:alert("RSnake says, \'XSS F\'")`>';
    $text .= '<IMG SRC=javascript:alert(String.fromCharCode(88,83,83))>';
    $text .= '<IMG SRC=# onmouseover="alert(\'xxs G\')">';
    $text .= '<IMG SRC= onmouseover="alert(\'xxs H\')">';
    $text .= '<IMG onmouseover="alert(\'xxs I\')">';
    $text .= '<IMG SRC=/ onerror="alert(String.fromCharCode(88,83,83))"></img>';
    $text .= '<IMG SRC=&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;&#97;&#108;&#101;&#114;&#116;&#40;
&#39;&#88;&#83;&#83;&#39;&#41;>';
    $text .= '<IMG SRC=&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000114&#0000105&#0000112&#0000116&#0000058&#0000097&
#0000108&#0000101&#0000114&#0000116&#0000040&#0000039&#0000088&#0000083&#0000083&#0000039&#0000041>';
    $text .= '<IMG SRC=&#x6A&#x61&#x76&#x61&#x73&#x63&#x72&#x69&#x70&#x74&#x3A&#x61&#x6C&#x65&#x72&#x74&#x28&#x27&#x58&#x53&#x53&#x27&#x29>';
    $text .= '#"><img src=M onerror=alert(\'XSS R\');>';
    $text .= '<IMG SRC="jav	ascript:alert(\'XSS J\');">';
    $text .= '<IMG SRC="jav&#x09;ascript:alert(\'XSS K\');">';
    $text .= '<IMG SRC="jav&#x0A;ascript:alert(\'XSS L\');">';
    $text .= '<IMG SRC="jav&#x0D;ascript:alert(\'XSS M\');">';
    $text .= '<IMG SRC=" &#14;  javascript:alert(\'XSS N\');">';
    $text .= '<IMG DYNSRC="javascript:alert(\'XSS O\')">\';';
    $text .= '<IMG LOWSRC="javascript:alert(\'XSS P\')">';
    $text .= '<IMG SRC=\'vbscript:msgbox("XSS Q")\'>';
    $text .= '<IMG SRC="livescript:[code]">';
    $text .= '<img onmouseover="JaVaScRiPt:alert(\'XSS D\')" class="width-full" width="900" height="1600" alt="<strong>The dosage probation roadmap, shows 4 phases described in a set of shapes</strong>" src="' . $this->url . '" usemap="#image_map2">
<map name="image_map2">
<area alt="Step 1" href="/node/1" coords="158,224,314,317,315,377,156,469,109,346,0" shape="polygon">
<area alt="Step 2" href="/node/2" coords="377,85,380,268,327,299,168,208,241,100,0" shape="polygon">
</map>';
    $text .= '<img alt="Preview" src="data:image/jpg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBxdWFsaXR5ID0gNzUK/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0YMiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgAQwBkAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8AppKQepq2knycPVfyh6U3BXpX0CkeI1Y1ra6MZHzVrWj/AGiQbmxXLJLtNbukKtzuBkwRRLa4Qeti7ql7EkQRW+ZBgmufa+cnaCSK07ixMsjBWLDNMfw/dEAxREg9DUJxSNHzNlVZ3MffmnR9Se9WRpV2pAaMjmr6aSywltvzdzSuh6mLLME9qrNKWPBq3qFu0bDK4z6iqcEZZsAVpFGcpCM7YwwqP7PLcELGhA6k10WnaN9rIaRWUCt+LR4YzkLtx2purGJCpOWpytpoLNAGbJJort1hULwBiis3XZoqCPPXSNWbGR6VXkjGwk8Vl6d4n0a8H+m3c1lIOqvCXB+hXP6gVrtrHhKOEv8A21JLkfcWFgx/MVyxrxjodMqTkrle1sftbHEu33xW5peh3yXACTKM89P51QsvEPhsoVgvo7cqeHlU5I/LFXrTx3oZnJe9lZkG1XMR2t9P/r4pzxMtooUKEFq2dZaaJsaNXkDOTyQf0roYrWKKFI22kKK4+18e6TJFm2mjVc48yQng/wCRViTX7GWzWRdQChgVMqEcn2NedVxE3o0/uO6nRitUzo5lsQRHvjEh/hzzVcwQyI6o649AfyrirnxNY2O2WK7ikbPzEgFsZ9Mc/iaoXHxEicFdsiY+7hQAa0pSqvpoRUVJdTotU0hZcu7bTjjJqnpGn2ccn75uhyWJrh9S8bz3cm5pZQEHyqrhQR6njmsW48Qi4hLXLTyRk4+/09q7FWdrM5XTV7o9f1HxHY6dtCtFCmcfMwBPPb8qlh1CTUrQtZvFIO7JIGA+pHevA7q8hLhlEgjYcZ/rVjTdXl027S5s72SBgeqn+Y7j2p+0gloZ2qN+R7HO2rid8LxngLkgUVzln8XHFuFudOjmkU4MkZ2BvfaQcGio+t2+yafV0/tHif2uaQ5PmBup64NTQ3IAwMFvUjg1m+SyNgYJBwTmp41yO4PriuOPMdEuUvfaHn3CR8EDgbeB+QqzDNNGASeCP4QaywZIN2whtwI3bc8fTtTo2kYfMciquyLI6K1MkMxdSJI8Zbd0P4CpXmeEFLaZ1KnJwSB+XSsa2aeaQyNuEf3SVGfpxVtrTUrm2FwtrIsbMR5hXAJpJyTK92xaOoTY+Z1kBI+bGDTJZ/3RYkuc/kPpVCSGdOZT34AHFRSStsKuO+RirvU7mf7s0PtBCh1iXJ/icZ/TpTluMNmW3icHr8uCPxFZPmjG0sykc9MinJNGCWeVio/uisnzFqxpSvGX/dWiRgjpknFUX8wyEc7e9RS3MJOUMirj+LnmqjTvv3BvypPmHobkcrbBiFj6kDFFZ8NxmIfO+e+CaKizNNTuNMTTdJTZHpdnIeNzTJvZvxPT8K1pNZ017fy59FsDEOiiMDFcW2pAg81C9+zLjPFeiqVN6tHDKtNaJnf2Wo6cqYsLS2hZm+ZTEoHPvirtlpelC6ZBpln5kg3MyoCP/rV5nBqssGQmD9a07HXr9Zd8cgBA6jjFU6MZfCJYlx+NHq9tpWmYxbwx28pztXYMZqR4mEGxbWMyBcEMMBj9a4S28YTmRHl5cHlv6111v4iiktopXkBLAErjmuKtgba9zsoY1S07FO40+DU5ktZo0hfdyhUgEZ9xz+dUZ/AumlWMCxyMcZxn5c1sTeJbPywu9d46FhzVU+JYgjMjBlAxwa2oYOouuhlXxlLqjjNZ8CS2bKYYw6N1w2Ntc5P4duoQc2ryAc4Q5rsdR8VSISoJ5Heqmk+JkiuN8q8g9B0NdTwsVo9zkWKvqtjg7vT7m3w0lpJGvbcpxU+laJqGq3CxWlq7jIyxGFH1NerXT6dq0W63fazcuh/w/Cnx77eArFKwI+6uelNYNPW4njLaIzLH4a6atsDe30nnnlhCAqj2GQaKdLc37NlbjPHPy9DRT+oLuT/aP908x3H1oyfWiiuZHSOBrUsDiNqKK3o/Ec9f4SN3YOcEjmla8uExtmcY96KKbbJiloR/aZieZG61djmkMP3z0ooq6bdyKsVbYo3Mjk8sTUMbHd1oorJv3jaKXKaunTyrJw7Ct+K4mOQZGx9aKK9HD6xPMxWktCRJpMH5z1ooorc5kf/Z" src="data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D&#039;http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg&#039;%20viewBox%3D&#039;0%200%201%201&#039;%2F%3E" width="100" height="67"/>';
    $text .= '<img src="https://drupal.org/files/One.gif" width="350" height="250" />';
    $text .= '</div>';

    return $text;
  }

}
