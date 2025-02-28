<?php

namespace Drupal\Tests\lightning_core\Functional;

use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Tests\BrowserTestBase;

/**
 * @group lightning_core
 */
class RevisionUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lightning_core',
  ];

  /**
   * Tests Lightning Core's integration with the core entity revision UI.
   */
  public function testRevisionUi() {
    $assert_session = $this->assertSession();

    $node_type = $this->drupalCreateContentType()->id();
    $node = $this->drupalCreateNode([
      'type' => $node_type,
    ]);

    $account = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($account);
    $this->drupalGet($node->toUrl('edit-form'));
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Revision information');
    $assert_session->fieldExists('Create new revision');
    $assert_session->fieldExists('Revision log message');

    EntityFormMode::create([
      'id' => 'node.default',
      'label' => 'Default',
      'targetEntityType' => 'node',
      'third_party_settings' => [
        'lightning_core' => [
          'revision_ui' => FALSE,
        ],
      ],
    ])->save();

    $this->getSession()->reload();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Revision information');
    $assert_session->fieldNotExists('Create new revision');
    $assert_session->fieldNotExists('Revision log message');
  }

}
