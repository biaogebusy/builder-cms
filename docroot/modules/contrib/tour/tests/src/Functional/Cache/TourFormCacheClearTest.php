<?php

namespace Drupal\Tests\tour\Functional\Cache;

use Drupal\Tests\tour\Functional\TourTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests cache clear when updating tours.
 */
#[Group('tour')]
class TourFormCacheClearTest extends TourTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['toolbar'];

  /**
   * Test cache clear when changing route.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testFormCacheClear(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer tour',
      'access toolbar',
      'access tour',
    ]));
    $this->drupalGet('admin/config/user-interface/tour');

    $this->assertSession()->buttonExists('Tour');

    $this->drupalGet('admin/config/user-interface/tour/manage/tours');
    $this->submitForm(['routes' => 'fail'], 'Save');

    $this->drupalGet('admin/config/user-interface/tour');
    $this->assertSession()->buttonExists('No tour');
  }

}
