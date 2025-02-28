<?php

namespace Drupal\Tests\verf\Functional;

use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;

/**
 * Tests the integration between Views Entity Reference Filter and Views.
 *
 * @group verf
 */
class IntegrationTest extends BrowserTestBase {

  use EntityReferenceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['verf_test_views'];

  /**
   * The node type used in the tests.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  private $nodeType;

  /**
   * The view we're using.
   *
   * @var \Drupal\views\Entity\View
   */
  private $view;

  /**
   * The admin user.
   *
   * @var \Drupal\user\Entity\User|false
   */
  private $admin;

  /**
   * The author user.
   *
   * @var \Drupal\user\Entity\User|false
   */
  private $author;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->nodeType = $this->drupalCreateContentType();
    $this->createEntityReferenceField('node', $this->nodeType->id(), 'field_refs', 'Refs', 'node');

    $this->admin = $this->drupalCreateUser([], $this->randomString(), true);
    $this->author = $this->drupalCreateUser(['view own unpublished content']);
  }

  /**
   * Tests that the Views Entity Reference Filter works.
   */
  public function testFilterWorks() {
    $referencedNode = $this->drupalCreateNode(['type' => $this->nodeType->id()]);
    $referencingNode = $this->drupalCreateNode([
      'type' => $this->nodeType->id(),
      'field_refs' => [['target_id' => $referencedNode->id()]],
    ]);

    $this->drupalGet('verftest');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertPageContainsNodeTeaserWithText($referencedNode->getTitle());
    $this->assertPageContainsNodeTeaserWithText($referencingNode->getTitle());

    $this->assertSelectOptionCanBeSelected('Refs (VERF selector)', $referencedNode->getTitle());
    $this->getSession()->getPage()->pressButton('Apply');

    $this->assertPageContainsNodeTeaserWithText($referencingNode->getTitle());
    $this->assertPageNotContainsNodeTeaserWithText($referencedNode->getTitle());
  }

  /**
   * Asserts that a node teaser containing the given text is present.
   *
   * @param string $text
   *   The text to look for.
   *
   * @throws \Exception
   */
  protected function assertPageContainsNodeTeaserWithText($text) {
    try {
      $this->assertPageNotContainsNodeTeaserWithText($text);

    }
    catch (\Exception $e) {
      // Text was found, we're good.
      return;
    }

    throw new \Exception("No teaser could be found with the text: $text");
  }

  /**
   * Asserts that no node teaser containing the given text is present.
   *
   * @param string $text
   *   The text that must not be present.
   */
  protected function assertPageNotContainsNodeTeaserWithText($text) {
    $teasers = $this->getSession()->getPage()->findAll('css', '.node--view-mode-teaser');

    foreach ($teasers as $teaser) {
      $this->assertStringNotContainsString($text, $teaser->getText());
    }
  }

  /**
   * Asserts that an option with a given value can be selected in a select.
   *
   * @param string $locator
   *   input id, name or label
   * @param string $value
   *   option value
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function assertSelectOptionCanBeSelected($locator, $value) {
    $this->getSession()->getPage()->selectFieldOption($locator, $value);
  }

  /**
   * Asserts that no option with a given value can be selected in a select.
   *
   * @param string $locator
   *   input id, name or label
   * @param string $value
   *   option value
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function assertSelectOptionCanNotBeSelected($locator, $value) {
    try {
      $this->getSession()->getPage()->selectFieldOption($locator, $value);
    }
    catch (ElementNotFoundException $e) {
      // The element could not be found, which is good.
      return;
    }

    throw new \AssertionError("$value could be selected in $locator while that should not be possible");
  }

  /**
   * Tests that a user can only select items they have access to.
   */
  public function testRegression2720953() {
    $published = $this->drupalCreateNode([
      'type' => $this->nodeType->id(),
    ]);
    $published->setOwner($this->author)->save();
    $unpublished = $this->drupalCreateNode([
      'type' => $this->nodeType->id(),
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $unpublished->setOwner($this->author)->save();
    $referencingPublished = $this->drupalCreateNode([
      'type' => $this->nodeType->id(),
      'field_refs' => [['target_id' => $published->id()]],
    ]);
    $referencingUnpublished = $this->drupalCreateNode([
      'type' => $this->nodeType->id(),
      'field_refs' => [['target_id' => $unpublished->id()]],
    ]);

    $this->drupalLogin($this->admin);
    $this->drupalGet('verftest');
    $this->assertSelectOptionCanBeSelected('Refs (VERF selector)', $published->getTitle());
    $this->assertSelectOptionCanBeSelected('Refs (VERF selector)', $unpublished->getTitle());

    $this->drupalLogin($this->author);
    $this->drupalGet('verftest');
    $this->assertSelectOptionCanBeSelected('Refs (VERF selector)', $published->getTitle());
    $this->assertSelectOptionCanBeSelected('Refs (VERF selector)', $unpublished->getTitle());

    $this->drupalLogout();
    $this->drupalGet('verftest');
    $this->assertSelectOptionCanBeSelected('Refs (VERF selector)', $published->getTitle());
    $this->assertSelectOptionCanBeSelected('Refs (VERF selector)', '- Restricted access -');
    $this->assertSelectOptionCanNotBeSelected('Refs (VERF selector)', $unpublished->getTitle());
  }

}
