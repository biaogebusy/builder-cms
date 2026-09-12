<?php

namespace Drupal\Tests\message\Functional;

/**
 * Tests the global message settings form.
 *
 * @group Message
 */
class MessageSettingsFormTest extends MessageTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['message', 'node', 'user'];

  /**
   * Tests submitting the settings form.
   */
  public function testSettingsForm(): void {
    $account = $this->drupalCreateUser(['administer message templates']);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/message/message');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('settings[purge_enable]');
    $this->assertSession()->fieldExists('delete_on_entity_delete[]');

    $edit = [
      'settings[purge_enable]' => TRUE,
      'settings[purge_methods][quota][enabled]' => TRUE,
      'quota' => 25,
      'delete_on_entity_delete[]' => ['node', 'user'],
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $config = $this->config('message.settings');
    $this->assertTrue($config->get('purge_enable'));
    $this->assertEqualsCanonicalizing(['node', 'user'], $config->get('delete_on_entity_delete'));
    $this->assertArrayHasKey('quota', $config->get('purge_methods'));
    $this->assertEquals(25, $config->get('purge_methods')['quota']['data']['quota']);

    // Disabling purge should clear configured purge methods.
    $edit = [
      'settings[purge_enable]' => FALSE,
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $config = $this->config('message.settings');
    $this->assertFalse($config->get('purge_enable'));
    $this->assertEquals([], $config->get('purge_methods'));
  }

}
