<?php

namespace Drupal\Tests\profile\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileType;

/**
 * Tests basic functionality of profiles.
 *
 * @group profile
 */
class ProfileTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity',
    'profile',
    'views',
  ];

  /**
   * Testing demo user 1.
   *
   * @var \Drupal\user\UserInterface
   */
  public $user1;

  /**
   * Testing demo user 2.
   *
   * @var \Drupal\user\UserInterface
   */
  public $user2;

  /**
   * Profile entity storage.
   *
   * @var \Drupal\profile\ProfileStorageInterface
   */
  public $profileStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('profile');
    $this->installEntitySchema('view');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['profile', 'user']);

    $this->profileStorage = $this->container->get('entity_type.manager')->getStorage('profile');
    $this->user1 = $this->createUser();
    $this->user2 = $this->createUser();
  }

  /**
   * Tests the profile entity and its methods.
   */
  public function testProfile() {
    $types_data = [
      'profile_type_0' => ['label' => $this->randomMachineName()],
      'profile_type_1' => ['label' => $this->randomMachineName()],
    ];
    /** @var \Drupal\profile\Entity\ProfileTypeInterface[] $types */
    $types = [];
    foreach ($types_data as $id => $values) {
      $types[$id] = ProfileType::create(['id' => $id] + $values);
      $types[$id]->save();
    }

    // Create a new profile.
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = $this->profileStorage->create([
      'type' => $types['profile_type_0']->id(),
    ]);

    $profile->setOwnerId($this->user1->id());
    $this->assertEquals($this->user1->id(), $profile->getOwnerId());

    $profile->setCreatedTime('1554159046');
    $this->assertEquals('1554159046', $profile->getCreatedTime());

    $profile->setChangedTime('1554159090');
    $this->assertEquals('1554159090', $profile->getChangedTime());

    $this->assertEquals('default', $profile->getData('test', 'default'));
    $profile->setData('test', 'value');
    $this->assertEquals('value', $profile->getData('test', 'default'));
    $profile->unsetData('test');
    $this->assertNull($profile->getData('test'));
    $this->assertEquals('default', $profile->getData('test', 'default'));

    // Save the profile.
    $profile->save();
    $expected_label = new TranslatableMarkup('@type #@id', [
      '@type' => $types['profile_type_0']->label(),
      '@id' => $profile->id(),
    ]);
    $this->assertEquals($expected_label, $profile->label());

    // List profiles for the user and verify that the new profile appears.
    $list = $this->profileStorage->loadByProperties(['uid' => $this->user1->id()]);
    $list_ids = array_keys($list);
    $this->assertEquals($list_ids, [$profile->id()]);

    // Create a second profile.
    $user1_profile1 = $profile;
    $user1_profile = $this->profileStorage->create([
      'type' => $types['profile_type_0']->id(),
      'uid' => $this->user1->id(),
    ]);
    $user1_profile->save();

    // List profiles for the user and verify that both profiles appear.
    $list = $this->profileStorage->loadByProperties(['uid' => $this->user1->id()]);
    $list_ids = array_keys($list);
    $this->assertEquals($list_ids, [$user1_profile1->id(), $user1_profile->id()]);

    // Delete the second profile and verify that the first still exists.
    $user1_profile->delete();
    $list = $this->profileStorage->loadByProperties(['uid' => $this->user1->id()]);
    $list_ids = array_keys($list);
    $this->assertEquals($list_ids, [$user1_profile1->id()]);

    // Create a profile for the second user.
    $user2_profile1 = $this->profileStorage->create([
      'type' => $types['profile_type_0']->id(),
      'uid' => $this->user2->id(),
    ]);
    $user2_profile1->save();

    // Delete the first user and verify that all of its profiles are deleted.
    $this->user1->delete();
    $list = $this->profileStorage->loadByProperties(['uid' => $this->user1->id()]);
    $list_ids = array_keys($list);
    $this->assertEquals($list_ids, []);

    // List profiles for the second user and verify that they still exist.
    $list = $this->profileStorage->loadByProperties(['uid' => $this->user2->id()]);
    $list_ids = array_keys($list);
    $this->assertEquals($list_ids, [$user2_profile1->id()]);
  }

  /**
   * Tests comparing profiles.
   */
  public function testCompare() {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_fullname',
      'entity_type' => 'profile',
      'type' => 'text',
    ]);
    $field_storage->save();
    foreach (['customer_billing', 'customer_shipping'] as $profile_type_id) {
      $profile_type = ProfileType::create([
        'id' => $profile_type_id,
        'label' => $profile_type_id,
      ]);
      $profile_type->save();

      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => $profile_type_id,
        'label' => 'Full name',
      ]);
      $field->save();
    }

    $first_profile = Profile::create([
      'type' => 'customer_billing',
      'uid' => 1,
      'field_fullname' => 'John Smith',
    ]);
    $second_profile = Profile::create([
      'type' => 'customer_billing',
      'uid' => 1,
      'field_fullname' => '',
    ]);
    $third_profile = Profile::create([
      'type' => 'customer_shipping',
      'uid' => 2,
      'field_fullname' => 'John Smith',
    ]);

    $this->assertTrue($first_profile->equalToProfile($third_profile));
    $this->assertFalse($first_profile->equalToProfile($third_profile, [
      'type', 'field_fullname',
    ]));
    $this->assertFalse($first_profile->equalToProfile($second_profile));
    $this->assertTrue($first_profile->equalToProfile($second_profile, ['type']));
  }

  /**
   * Tests populating a profile using another profile's field values.
   */
  public function testPopulate() {
    $profile_type = ProfileType::create([
      'id' => 'customer',
      'label' => 'Customer',
    ]);
    $profile_type->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_fullname',
      'entity_type' => 'profile',
      'type' => 'text',
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $profile_type->id(),
      'label' => 'Full name',
    ]);
    $field->save();

    $first_profile = Profile::create([
      'type' => 'customer',
      'uid' => 1,
      'field_fullname' => 'John Smith',
      'status' => FALSE,
    ]);
    $second_profile = Profile::create([
      'type' => 'customer',
      'uid' => 1,
      'field_fullname' => '',
      'status' => FALSE,
    ]);
    $third_profile = Profile::create([
      'type' => 'customer',
      'uid' => 2,
      'field_fullname' => 'Jane Smith',
      'status' => TRUE,
    ]);

    $third_profile->populateFromProfile($second_profile, ['field_fullname']);
    // Confirm that the configurable field was transferred.
    $this->assertEmpty($third_profile->get('field_fullname')->value);
    // Confirm that the base fields were not changed.
    $this->assertEquals(2, $third_profile->getOwnerId());
    $this->assertTrue($third_profile->isPublished());

    $third_profile->populateFromProfile($first_profile);
    // Confirm that the configurable field was transferred.
    $this->assertEquals('John Smith', $third_profile->get('field_fullname')->value);
    // Confirm that the base fields were not changed.
    $this->assertEquals(2, $third_profile->getOwnerId());
    $this->assertTrue($third_profile->isPublished());
  }

  /**
   * Tests default profile functionality.
   */
  public function testDefaultProfile() {
    $profile_type = ProfileType::create([
      'id' => 'test_defaults',
      'label' => 'test_defaults',
    ]);
    $profile_type->save();

    /** @var \Drupal\profile\Entity\ProfileInterface $profile1 */
    $profile1 = Profile::create([
      'type' => $profile_type->id(),
      'uid' => $this->user1->id(),
    ]);
    $profile1->save();
    // Confirm that the profile was set as default.
    $this->assertTrue($profile1->isDefault());

    /** @var \Drupal\profile\Entity\ProfileInterface $profile2 */
    $profile2 = Profile::create([
      'type' => $profile_type->id(),
      'uid' => $this->user1->id(),
    ]);
    $profile2->setDefault(TRUE);
    $profile2->save();

    // Confirm that setting the second profile as default removed the
    // flag from the first profile.
    $profile2 = $this->reloadEntity($profile2);
    $profile1 = $this->reloadEntity($profile1);
    $this->assertTrue($profile2->isDefault());
    $this->assertFalse($profile1->isDefault());

    // Verify that an unpublished profile cannot be the default.
    $profile2->setUnpublished();
    $profile2->save();
    $this->assertFalse($profile2->isDefault());

    $profile1 = $this->reloadEntity($profile1);
    $this->assertFalse($profile1->isDefault());
    // Confirm that re-saving the other published profile sets it as default.
    $profile1->save();
    $this->assertTrue($profile1->isDefault());

    // Confirm that profile may be still saved, even if it references
    // non-existing user.
    /** @var \Drupal\profile\Entity\ProfileInterface $profile1 */
    $profile3 = Profile::create([
      'type' => $profile_type->id(),
      'uid' => $this->user2->id() + 100,
    ]);
    $profile3->save();
    // Confirm that the profile was not set as default.
    $this->assertFalse($profile3->isDefault());

  }

  /**
   * Tests revisions.
   */
  public function testProfileRevisions() {
    $profile_type = ProfileType::create([
      'id' => 'test_defaults',
      'label' => 'test_defaults',
    ]);
    $profile_type->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'profile_fullname',
      'entity_type' => 'profile',
      'type' => 'text',
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $profile_type->id(),
      'label' => 'Full name',
    ]);
    $field->save();

    // Create new profiles.
    /** @var \Drupal\profile\Entity\Profile $profile1 */
    $profile1 = Profile::create([
      'type' => $profile_type->id(),
      'uid' => $this->user1->id(),
      'profile_fullname' => $this->randomMachineName(),
    ]);
    $profile1->save();

    $profile1 = $this->reloadEntity($profile1);
    $existing_profile_id = $profile1->id();
    $existing_revision_id = $profile1->getRevisionId();

    $profile1->get('profile_fullname')->setValue($this->randomMachineName());
    $profile1->save();

    $profile1 = $this->reloadEntity($profile1);
    $this->assertEquals($existing_profile_id, $profile1->id());
    $this->assertEquals($existing_revision_id, $profile1->getRevisionId());

    $profile_type->set('allow_revision', TRUE);
    $profile_type->save();

    // Create new profiles.
    /** @var \Drupal\profile\Entity\Profile $profile2 */
    $profile2 = Profile::create([
      'type' => $profile_type->id(),
      'uid' => $this->user1->id(),
      'profile_fullname' => $this->randomMachineName(),
    ]);
    $profile2->setDefault(TRUE);
    $profile2->save();

    $profile2 = $this->reloadEntity($profile2);
    $existing_profile_id = $profile2->id();
    $existing_revision_id = $profile2->getRevisionId();

    // Changing profiles support revisions.
    $profile2->get('profile_fullname')->setValue($this->randomMachineName());
    $profile2->setNewRevision();
    $profile2->save();

    $profile2 = $this->reloadEntity($profile2);
    $this->assertEquals($existing_profile_id, $profile2->id());
    $this->assertNotEquals($existing_revision_id, $profile2->getRevisionId());

    $existing_profile_id = $profile2->id();
    $existing_revision_id = $profile2->getRevisionId();

    // Random save does not create a revision.
    $profile2->save();
    $profile2 = $this->reloadEntity($profile2);
    $this->assertEquals($existing_profile_id, $profile2->id());
    $this->assertEquals($existing_revision_id, $profile2->getRevisionId());

  }

}
