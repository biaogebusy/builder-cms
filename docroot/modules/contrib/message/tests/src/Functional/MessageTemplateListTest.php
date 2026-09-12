<?php

namespace Drupal\Tests\message\Functional;

/**
 * Testing the listing functionality for the Message template entity.
 *
 * @group Message
 */
class MessageTemplateListTest extends MessageTestBase {

  /**
   * The user object.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * Listing of message templates.
   */
  public function testEntityTypeList(): void {
    $this->user = $this->drupalCreateUser(['administer message templates']);
    $this->drupalLogin($this->user);

    $this->createMessageTemplate(
      'list_template',
      'List template label',
      'List template description',
      ['Body text']
    );

    $this->drupalGet('admin/structure/message');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('List template label');
    $this->assertSession()->pageTextContains('List template description');
    $this->assertSession()->linkExists('Edit');
    $this->assertSession()->linkExists('Delete');
  }

}
