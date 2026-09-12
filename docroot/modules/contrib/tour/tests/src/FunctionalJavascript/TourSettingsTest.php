<?php

declare(strict_types=1);

namespace Drupal\Tests\tour\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the settings form of Tour.
 */
#[Group('tour')]
class TourSettingsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['toolbar', 'tour_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test label settings.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testLabelSettings(): void {
    $this->drupalLogin($this->createUser([
      'access toolbar',
      'access tour',
    ]));

    $this->drupalGet('tour-test-1');
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->hasButton('Tour'));

    $this->drupalGet('tour-test-2');
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->hasButton('No tour'));

    \Drupal::configFactory()->getEditable('tour.settings')
      ->set('display_custom_labels', TRUE)
      ->set('tour_avail_text', 'Take a tour of this page.')
      ->set('tour_no_avail_text', 'No tour available for this page.')
      ->save();
    $this->rebuildAll();

    $this->drupalGet('tour-test-1');
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->hasButton('Take a tour of this page.'));

    $this->drupalGet('tour-test-2');
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->hasButton('No tour available for this page.'));
  }

  /**
   * Test hide when tour is empty settings.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testHideWhenEmptySetting(): void {
    $this->drupalLogin($this->createUser([
      'access toolbar',
      'access tour',
    ]));

    $this->drupalGet('tour-test-1');
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->hasButton('Tour'));

    $this->drupalGet('tour-test-2');
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->hasButton('No tour'));

    \Drupal::configFactory()->getEditable('tour.settings')
      ->set('hide_tour_when_empty', TRUE)
      ->save();
    $this->rebuildAll();

    $this->drupalGet('tour-test-1');
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->hasButton('Tour'));

    $this->drupalGet('tour-test-2');
    $page = $this->getSession()->getPage();
    $this->assertFalse($page->hasButton('No tour'));
  }

}
