<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Term tests.
 *
 * @group content_lock
 */
class ContentLockTermTest extends BrowserTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'content_lock',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test simultaneous edit on block.
   */
  public function testContentLockTerm() {

    // Create vocabulary and terms.
    $vocabulary = $this->createVocabulary();
    $term1 = $this->createTerm($vocabulary);

    $admin = $this->drupalCreateUser([
      'administer taxonomy',
      'administer content lock',
    ]);

    $user1 = $this->drupalCreateUser([
      'administer taxonomy',
      'access content',
    ]);
    $user2 = $this->drupalCreateUser([
      'administer taxonomy',
      'break content lock',
    ]);

    // We protect the bundle created.
    $this->drupalLogin($admin);
    $edit = [
      'taxonomy_term[bundles][' . $term1->bundle() . ']' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // We lock term1.
    $this->drupalLogin($user1);
    // Edit a term without saving.
    $this->drupalGet("taxonomy/term/{$term1->id()}/edit");
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit term1.
    $this->drupalLogin($user2);
    $this->drupalGet("taxonomy/term/{$term1->id()}/edit");
    $assert_session->pageTextContains("This content is being edited by the user {$user1->getDisplayName()} and is therefore locked to prevent other users changes.");
    $assert_session->linkExists('Break lock');
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We save term1 and unlock it.
    $this->drupalLogin($user1);
    $this->drupalGet("taxonomy/term/{$term1->id()}/edit");
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->drupalGet('/taxonomy/term/' . $term1->id() . '/edit');
    $this->submitForm([], 'Save');

    // We lock term1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("taxonomy/term/{$term1->id()}/edit");
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit term1.
    $this->drupalLogin($user1);
    $this->drupalGet("taxonomy/term/{$term1->id()}/edit");
    $assert_session->pageTextContains("This content is being edited by the user {$user2->getDisplayName()} and is therefore locked to prevent other users changes.");
    $assert_session->linkNotExists('Break lock');
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We unlock term1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("taxonomy/term/{$term1->id()}/edit");
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->drupalGet('/taxonomy/term/' . $term1->id() . '/edit');
    $this->submitForm([], 'Save');
  }

}
