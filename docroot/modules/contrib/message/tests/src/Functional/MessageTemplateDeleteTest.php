<?php

namespace Drupal\Tests\message\Functional;

use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;

/**
 * Tests deleting message templates via the confirm form.
 *
 * @group Message
 */
class MessageTemplateDeleteTest extends MessageTestBase {

  /**
   * Tests that templates in use cannot be deleted.
   */
  public function testDeleteBlockedWhenMessagesExist(): void {
    $account = $this->drupalCreateUser(['administer message templates']);
    $this->drupalLogin($account);

    $this->createMessageTemplate(
      'used_template',
      'Used template',
      'In use',
      ['Hello']
    );
    Message::create(['template' => 'used_template'])->save();

    $this->drupalGet('admin/structure/message/delete/used_template');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('You cannot remove this message template');
    $this->assertSession()->buttonNotExists('Delete');
    $this->assertNotNull(MessageTemplate::load('used_template'));
  }

  /**
   * Tests deleting an unused message template.
   */
  public function testDeleteUnusedTemplate(): void {
    $account = $this->drupalCreateUser(['administer message templates']);
    $this->drupalLogin($account);

    $this->createMessageTemplate(
      'unused_template',
      'Unused template',
      'Not in use',
      ['Hello']
    );

    $this->drupalGet('admin/structure/message/delete/unused_template');
    $this->assertSession()->buttonExists('Delete');
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('The message template Unused template has been deleted.');
    $this->assertNull(MessageTemplate::load('unused_template'));
  }

}
