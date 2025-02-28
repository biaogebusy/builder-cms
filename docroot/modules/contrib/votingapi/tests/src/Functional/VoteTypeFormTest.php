<?php

namespace Drupal\Tests\votingapi\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the behavior of the Vote Type form.
 *
 * @group VotingAPI
 */
class VoteTypeFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['votingapi', 'votingapi_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Tests that a Vote Type can be created properly through the UI.
   */
  public function testVoteTypeCreation(): void {
    // First verify that a user can't access the Vote Type form without having
    // the proper permissions.
    $this->drupalGet('admin/structure/vote-types/add');
    $this->assertSession()->statusCodeEquals(403);

    // Log in as a user with proper permissions.
    $this->drupalLogin($this->drupalCreateUser(['administer vote types']));

    $this->drupalGet('admin/structure/vote-types/add');
    $this->assertSession()->statusCodeEquals(200);

    // Now create a new Vote Type.
    $type_label = 'Rating';
    $machine_name = 'rating';
    $value_type = 'points';
    $description = 'A test Vote Type for testing.';

    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();
    $page->fillField('Name', $type_label);
    $page->fillField('Machine-readable name', $machine_name);
    $page->fillField('Value type', $value_type);
    $page->fillField('Description', $description);
    $page->pressButton('Save vote type');

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    // The default Vote Type set up by Voting API.
    $assert->pageTextContains('The default tag for votes on content.');
    // The Vote Type set up by the votingapi_test module.
    $assert->pageTextContains('A generic vote used for testing purposes.');
    // The Vote Type we created above.
    $assert->pageTextContains($description);

    // The success message.
    $assert->pageTextContains("The vote type $type_label has been added.");

    // Verify that the Vote Type we created above exists in the entity storage.
    $vote_type = $this->entityTypeManager->getStorage('vote_type');
    $this->assertEquals($type_label, $vote_type->load($machine_name)->label());
  }

}
