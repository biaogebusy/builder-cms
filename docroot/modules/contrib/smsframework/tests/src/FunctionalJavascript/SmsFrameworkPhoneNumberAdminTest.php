<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\FunctionalJavascript;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\sms\Functional\SmsFrameworkTestTrait;

/**
 * Tests phone number administration user interface.
 *
 * @group SMS Framework
 */
final class SmsFrameworkPhoneNumberAdminTest extends WebDriverTestBase {

  use SmsFrameworkTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sms', 'block', 'entity_test'];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->drupalPlaceBlock('page_title_block');
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');

    $account = $this->drupalCreateUser([
      'administer smsframework',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Test using existing fields for new phone number settings.
   */
  public function testPhoneNumberFieldExisting(): void {
    $field_storage = $this->entityTypeManager->getStorage('field_storage_config');
    $field_instance = $this->entityTypeManager->getStorage('field_config');

    // Create a field so it appears as a pre-existing field.
    /** @var \Drupal\field\FieldStorageConfigInterface $field_telephone */
    $field_telephone = $field_storage->create([
      'entity_type' => 'entity_test',
      'field_name' => mb_strtolower($this->randomMachineName()),
      'type' => 'telephone',
    ]);
    $field_telephone->save();

    $field_instance->create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => $field_telephone->getName(),
    ])->save();

    $this->drupalGet('admin/config/smsframework/phone_number/add');
    $session = $this->assertSession();
    $session->selectExists('entity_bundle')
      ->selectOption('entity_test|entity_test');
    $session->waitForElement('xpath', '//option[@value="' . $field_telephone->getName() . '"]');
    $session->selectExists('field_mapping[phone_number]')
      ->selectOption($field_telephone->getName());
    $session->buttonExists('Save')
      ->click();

    $this->drupalGet('admin/config/smsframework/phone_number/entity_test.entity_test');
    $session->optionExists('edit-field-mapping-phone-number', $field_telephone->getName())
      ->hasAttribute('selected');
  }

}
