<?php

namespace Drupal\Tests\page_manager\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\page_manager\Entity\PageVariant;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that pages and variants can be translated.
 *
 * @group page_manager
 */
class PageManagerConfigTranslationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * @todo Remove page_manager_ui from the list once config_translation does not
   *   require a UI in https://www.drupal.org/node/2670718.
   */
  protected static $modules = ['block', 'page_manager', 'page_manager_ui', 'node', 'config_translation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('de')->save();

    $this->drupalLogin($this->drupalCreateUser(['administer site configuration', 'translate configuration']));

    PageVariant::create([
      'variant' => 'http_status_code',
      'label' => 'HTTP status code',
      'id' => 'http_status_code',
      'page' => 'node_view',
    ])->save();
  }

  /**
   * Tests config translation.
   */
  public function testTranslation() {
    $this->drupalGet('admin/config/regional/config-translation');
    $this->assertSession()->linkByHrefExists('admin/config/regional/config-translation/page');
    $this->assertSession()->linkByHrefExists('admin/config/regional/config-translation/page_variant');

    $this->drupalGet('admin/config/regional/config-translation/page');
    $this->assertSession()->pageTextContains('Node view');
    $this->clickLink('Translate');
    $this->clickLink('Add');
    $this->assertSession()->fieldExists('translation[config_names][page_manager.page.node_view][label]');

    $this->drupalGet('admin/config/regional/config-translation/page_variant');
    $this->assertSession()->pageTextContains('HTTP status code');
    $this->clickLink('Translate');
    $this->clickLink('Add');
    $this->assertSession()->fieldExists('translation[config_names][page_manager.page_variant.http_status_code][label]');
  }

}
