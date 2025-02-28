<?php

namespace Drupal\Tests\replication\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Verifies Replication settings page.
 *
 * @group replication
 */
class ReplicationSettingsPageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'multiversion',
    'user',
    'replication',
  ];

  /**
   * User that can access replication settings page.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->user = $this->drupalCreateUser(['access administration pages']);
  }

  /**
   * Test the forms.
   */
  public function testReplicationConfigurationForms() {
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/replication/settings');
    $this->assertText('Replication settings');
    $this->assertText('Replication configuration');
    $this->assertFieldByName('mapping_type', 'uid_1');
    $this->assertFieldByName('uid', '');
    $this->assertFieldByName('changes_limit', 100);
    $this->assertFieldByName('bulk_docs_limit', 100);
    $this->assertFieldByName('replication_execution_limit', 1);
    $this->assertFieldByName('verbose_logging', FALSE);

    // Edit config and save.
    $edit = [
      'mapping_type' => 'uid',
      'uid' => $this->user->id(),
      'changes_limit' => 200,
      'bulk_docs_limit' => 200,
      'replication_execution_limit' => 4,
      'verbose_logging' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');
    // Check field values after form save.
    $this->assertText('The configuration options have been saved.');
    $this->assertText('Replication settings');
    $this->assertText('Replication configuration');
    $this->assertFieldByName('mapping_type', 'uid');
    $this->assertFieldByName('uid', $this->user->id());
    $this->assertFieldByName('changes_limit', 200);
    $this->assertFieldByName('bulk_docs_limit', 200);
    $this->assertFieldByName('replication_execution_limit', 4);
    $this->assertFieldByName('verbose_logging', TRUE);
  }

}
