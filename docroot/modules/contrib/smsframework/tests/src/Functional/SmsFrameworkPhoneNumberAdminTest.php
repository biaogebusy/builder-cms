<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\sms\Entity\PhoneNumberVerification;

/**
 * Tests phone number administration user interface.
 *
 * @group SMS Framework
 */
final class SmsFrameworkPhoneNumberAdminTest extends SmsFrameworkBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'entity_test'];

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
   * Tests phone number list.
   */
  public function testPhoneNumberList(): void {
    $this->drupalGet('admin/config/smsframework/phone_number');
    $this->assertSession()->responseContains(t('No phone number settings found.'));
    $this->assertSession()->linkByHrefExists('admin/config/smsframework/phone_number/add');

    // Ensure statistics are appearing on list.
    $this->createPhoneNumberSettings('entity_test', 'entity_test');
    $entity = EntityTest::create();
    $quantity = [6, 2, 4];

    /** @var \Drupal\sms\Entity\PhoneNumberVerificationInterface $verification */
    // Expired.
    for ($j = 0; $j < $quantity[0]; $j++) {
      $verification = PhoneNumberVerification::create();
      $verification
        ->setStatus(FALSE)
        ->set('created', 0)
        ->setEntity($entity)
        ->save();
    }
    // Verified.
    for ($j = 0; $j < $quantity[1]; $j++) {
      $verification = PhoneNumberVerification::create();
      $verification
        ->setStatus(TRUE)
        ->setEntity($entity)
        ->save();
    }
    // Unverified.
    for ($j = 0; $j < $quantity[2]; $j++) {
      $verification = PhoneNumberVerification::create();
      $verification
        ->setStatus(FALSE)
        ->setEntity($entity)
        ->save();
    }

    $this->drupalGet('admin/config/smsframework/phone_number');
    $this->assertSession()->responseContains('<td>entity_test</td>
                      <td>' . $quantity[0] . '</td>
                      <td>' . $quantity[1] . '</td>
                      <td>' . ($quantity[0] + $quantity[2]) . '</td>
                      <td>' . array_sum($quantity) . '</td>');
  }

  /**
   * CRUD a phone number settings via UI.
   */
  public function testPhoneNumberCrud(): void {
    // Add a new phone number config.
    $this->drupalGet('admin/config/smsframework/phone_number/add');
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      'entity_bundle' => 'entity_test|entity_test',
      'field_mapping[phone_number]' => '!create',
    ];
    $this->drupalGet('admin/config/smsframework/phone_number/add');
    $this->submitForm($edit, 'Save');

    $this->assertSession()->addressEquals('admin/config/smsframework/phone_number');
    $t_args = ['%id' => 'entity_test.entity_test'];
    $this->assertSession()->responseContains(t('Phone number settings %id created.', $t_args));
    $this->assertSession()->responseContains('<td>entity_test</td>');
    $this->assertSession()->linkByHrefExists('admin/config/smsframework/phone_number/entity_test.entity_test');
    $this->assertSession()->linkByHrefExists('admin/config/smsframework/phone_number/entity_test.entity_test/delete');

    // Ensure a phone number config cannot have the same bundle as pre-existing.
    $this->drupalGet('admin/config/smsframework/phone_number/add');
    $this->assertSession()->optionNotExists('edit-entity-bundle', 'entity_test|entity_test');

    // Edit phone number settings.
    $this->drupalGet('admin/config/smsframework/phone_number/entity_test.entity_test');
    $this->assertSession()->fieldExists('field_mapping[phone_number]');
    $this->assertSession()->fieldNotExists('entity_bundle');
    $optionElement = $this->assertSession()->optionExists('edit-field-mapping-phone-number', 'phone_number');
    static::assertTrue($optionElement->hasAttribute('selected'));

    // Ensure edit form is saving correctly.
    $this->drupalGet('admin/config/smsframework/phone_number/entity_test.entity_test');
    $this->submitForm([
      'code_lifetime' => '7777',
    ], 'Save');
    static::assertEquals(7777, $this->config('sms.phone.entity_test.entity_test')->get('verification_code_lifetime'));

    // Delete new phone number settings.
    $this->drupalGet('admin/config/smsframework/phone_number/entity_test.entity_test/delete');
    $this->assertSession()->responseContains(t('Are you sure you want to delete SMS phone number settings %label?', [
      '%label' => 'entity_test.entity_test',
    ]));
    $this->drupalGet('admin/config/smsframework/phone_number/entity_test.entity_test/delete');
    $this->submitForm([], 'Delete');
    $this->assertSession()->addressEquals('admin/config/smsframework/phone_number');
    $this->assertSession()->responseContains(t('Phone number settings %label was deleted.', [
      '%label' => 'entity_test.entity_test',
    ]));
    $this->assertSession()->responseContains('No phone number settings found.');
  }

  /**
   * Test field creation for new phone number settings.
   */
  public function testPhoneNumberFieldCreate(): void {
    $field_name_telephone = 'phone_number';

    // Test the unique field name generator by creating pre-existing fields.
    $field_storage = $this->entityTypeManager->getStorage('field_storage_config');
    $field_storage->create([
      'entity_type' => 'entity_test',
      'field_name' => $field_name_telephone,
      'type' => 'telephone',
    ])->save();

    $this->drupalGet('admin/config/smsframework/phone_number/add');
    $this->submitForm([
      'entity_bundle' => 'entity_test|entity_test',
      'field_mapping[phone_number]' => '!create',
    ], 'Save');

    $field_name_telephone .= '_2';
    $field_config = $field_storage->load('entity_test.' . $field_name_telephone);
    static::assertTrue($field_config instanceof FieldStorageConfigInterface, 'Field config created.');

    // Ensure field name is associated with config.
    $this->drupalGet('admin/config/smsframework/phone_number/entity_test.entity_test');
    $this->assertSession()->statusCodeEquals(200);
    $optionElement = $this->assertSession()->optionExists('edit-field-mapping-phone-number', $field_name_telephone);
    static::assertTrue($optionElement->hasAttribute('selected'));
  }

}
