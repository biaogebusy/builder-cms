<?php

namespace Drupal\Tests\key\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests input of keys.
 *
 * @group key
 */
class KeyInputTest extends BrowserTestBase {

  use KeyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['key'];

  /**
   * A user with the 'administer keys' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['administer keys']);
  }

  /**
   * Tests the a long key input.
   */
  public function testLongKey() {
    $this->drupalLogin($this->adminUser);
    $assert_session = $this->assertSession();

    // Add a key with a 4000 characters.
    $this->drupalGet('admin/config/system/keys/add');

    $edit = [
      'id' => 'testing_key',
      'label' => 'Testing Key',
      'key_type' => 'authentication',
      'key_input_settings[key_value]' => str_pad('', 4000, 'z'),
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains(sprintf('The key %s has been added.', $edit['label']));

    // Go to the Key page.
    $this->drupalGet('admin/config/system/keys/manage/testing_key');
    $assert_session->statusCodeEquals(200);
  }

}
