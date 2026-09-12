<?php

namespace Drupal\Tests\tour\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that tours can be started through the tour button block.
 */
#[Group('tour')]
class TourButtonBlockTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'tour', 'tour_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the tour button block in the sidebar.
    $this->drupalPlaceBlock('tour_button_block', ['region' => 'content']);

    // Grant permission to view tours to anonymous users.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access tour']);
  }

  /**
   * Tests that tours can be started using the tour button block.
   */
  public function testTourButtonBlock(): void {
    // Check that tours can be started by anonymous users if they have the
    // relevant permission and the block with the tour button is present.
    $this->drupalGet('tour-test-1-no-action');

    // The tour button should be visible.
    $this->assertTrue($this->getTourButton()->isVisible());
    $this->assertEquals('Tour', $this->getTourButton()->getText());

    // The tour should not yet have been started, so the first tour tip should
    // not be visible.
    $this->assertFalse($this->getSession()->getDriver()->wait(10000, "jQuery('.tip-tour-test-1').is(':hidden')"));

    // Click the button. Now the tour tip should become visible.
    $this->getTourButton()->click();
    $this->assertJsCondition("jQuery('.tip-tour-test-1').is(':visible')");

    // When the user doesn't have permission to view tours, the button should
    // not be present.
    user_role_revoke_permissions(RoleInterface::ANONYMOUS_ID, ['access tour']);
    $this->getSession()->getDriver()->reload();
    $this->assertFalse($this->getTourButton());
  }

  /**
   * Returns the tour button that is present in the page.
   *
   * @return \Behat\Mink\Element\NodeElement|false
   *   The tour button, or FALSE if there is no tour button.
   */
  protected function getTourButton(): NodeElement|bool {
    $elements = $this->cssSelect('.js-tour-start-button');
    if (empty($elements)) {
      return FALSE;
    }
    return reset($elements);
  }

}
