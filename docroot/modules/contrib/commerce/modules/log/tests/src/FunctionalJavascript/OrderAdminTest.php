<?php

namespace Drupal\Tests\commerce_log\FunctionalJavascript;

use Drupal\Tests\commerce_order\FunctionalJavascript\OrderWebDriverTestBase;

/**
 * Tests the order admin UI.
 *
 * @group commerce
 * @group commerce_log
 */
class OrderAdminTest extends OrderWebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_log',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_order',
      'add commerce_log commerce_order admin comment',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests submit the admin order comment form via Control + Enter.
   */
  public function testSubmitOrderCommentViaControlPlusEnter() {
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->loggedInUser->getEmail(),
      'uid' => $this->loggedInUser,
      'store_id' => $this->store,
    ]);

    $this->drupalGet($order->toUrl('canonical'));
    $this->assertSession()->pageTextContains('Comment on this order');
    $page = $this->getSession()->getPage();
    $summary_element = $page->find('css', '[data-drupal-selector="edit-log-comment"] summary');
    $summary_element->click();
    $this->assertSession()->pageTextContains('No log entries.');
    $comment = sprintf('Urgent order for %s!', $this->loggedInUser->getEmail());
    $comment_field = $page->findField('Comment');
    $this->getSession()->getPage()->fillField('Comment', $comment);

    $selector = '#' . $comment_field->getAttribute('id');
    $script = <<<JS
(function (selector) {
  const btn = document.querySelector(selector);
    btn.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', ctrlKey: true}));
})('{$selector}')

JS;
    $options = [
      'script' => $script,
      'args' => [],
    ];
    $this->getSession()->getDriver()->getWebDriverSession()->execute($options);

    $this->assertSession()->pageTextNotContains('No log entries.');
    $this->assertSession()->pageTextContains($comment);
  }

}
