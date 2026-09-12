<?php

declare(strict_types=1);

namespace Drupal\Tests\tour\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests tour triggering functionality.
 */
#[Group('tour')]
class TourTriggerTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'toolbar',
    'tour_test',
  ];

  /**
   * Tests tour triggering functionality.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException|\Drupal\Core\Entity\EntityStorageException
   */
  public function testTourTrigger(): void {
    $this->drupalLogin($this->createUser([
      'access toolbar',
      'access tour',
    ]));
    $this->drupalGet('/tour-test-4');
    $page = $this->getSession()->getPage();

    // Clicking on toolbar tour button starts the tour.
    $page->find('css', '.js-tour-start-toolbar')->press();
    $this->assertTourStarted();

    // Verify aria attributes.
    $this->assertSame('dialog', $page->findButton('Tour')->getAttribute('aria-haspopup'));
    // Pressing once again, stops the tour.
    $page->pressButton('End tour');
    $this->assertTourStopped();

    // Pressing a button with the 'js-tour-start-toolbar' class starts the tour.
    $page->pressButton('Trigger 1');
    $this->assertTourStarted();
    $page->pressButton('End tour');
    $this->assertTourStopped();

    // Clicking an element having 'js-tour-start-toolbar' class starts the tour.
    $h3 = $page->findById('trigger2');
    $h3->click();
    $this->assertTourStarted();
    $page->pressButton('End tour');
    $this->assertTourStopped();

    // Uninstall toolbar module to test that tour triggering is fully decoupled.
    \Drupal::service('module_installer')->uninstall(['toolbar']);
    $this->getSession()->reload();
    $this->assertSession()->buttonNotExists('Tour');

    // Trigger again the tour from a button having 'js-tour-start-toolbar'
    // class.
    $page->pressButton('Trigger 1');
    $this->assertTourStarted();
    // Pressing once again, stops the tour.
    $page->pressButton('End tour');
    $this->assertTourStopped();
  }

  /**
   * Asserts that the tour has been successfully started.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertTourStarted(): void {
    $assert_session = $this->assertSession();

    $assert_session->pageTextContains('The first tip');
    $assert_session->pageTextContains('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.');
    $assert_session->pageTextContains('1 of 2');
    $assert_session->buttonExists('Next');

    // Advance to the next tip.
    $this->getSession()->getPage()->pressButton('Next');

    $assert_session->pageTextContains('The 2nd tip');
    $assert_session->pageTextContains('Lorem ipsum2');
    $assert_session->pageTextContains('2 of 2');
    $assert_session->buttonExists('End tour');
  }

  /**
   * Asserts that the tour has is stopped.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertTourStopped(): void {
    $this->assertSession()->buttonNotExists('Close');
  }

}
