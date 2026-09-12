<?php

namespace Drupal\Tests\webform\FunctionalJavascript;

/**
 * Tests webform filter javascript.
 *
 * @group webform_javascript
 */
class WebformFilterJavaScriptTest extends WebformWebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['webform', 'help'];

  /**
   * Test filter.
   */
  public function testFilter(): void {
    // Set admin theme to claro.
    \Drupal::service('theme_installer')->install(['claro']);
    \Drupal::configFactory()->getEditable('system.theme')
      ->set('admin', 'claro')
      ->save();

    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    /* ********************************************************************** */

    $this->drupalLogin($this->rootUser);

    // Check filter loaded.
    $this->drupalGet('/admin/help/webform');

    // Check results.
    $assert_session->waitForElementVisible('css', '.webform-help-videos-summary');
    $assert_session->waitForText('53 videos');
    $this->assertTrue($page->findLink('Introduction to Webform for Drupal 8 | Slides')->isVisible());
    sleep(1);
    $this->assertFalse($page->find('css', '.webform-help-videos-no-results')->isVisible());
    $this->assertFalse($page->find('css', '.webform-form-filter-reset')->isVisible());

    // Check no results.
    $session->executeScript("jQuery('.webform-form-filter-text').val('xxx').keyup()");
    $assert_session->waitForText('0 add-ons');
    $this->assertFalse($page->findLink('Introduction to Webform for Drupal 8 | Slides')->isVisible());
    $this->assertTrue($page->find('css', '.webform-help-videos-no-results')->isVisible());
    $this->assertTrue($page->find('css', '.webform-form-filter-reset')->isVisible());

    // Check reset.
    $session->executeScript("jQuery('.webform-form-filter-reset').click()");
    $assert_session->waitForElementVisible('css', '.webform-help-videos-summary');
    $assert_session->waitForText('53 videos');
    $this->assertTrue($page->findLink('Introduction to Webform for Drupal 8 | Slides')->isVisible());
    $this->assertFalse($page->find('css', '.webform-help-videos-no-results')->isVisible());
    $this->assertFalse($page->find('css', '.webform-form-filter-reset')->isVisible());
  }

}
