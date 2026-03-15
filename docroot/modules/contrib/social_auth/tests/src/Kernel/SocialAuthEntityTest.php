<?php

namespace Drupal\Tests\social_auth\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\social_auth\Entity\SocialAuth;

/**
 * Tests social_auth entity.
 *
 * @group social_auth
 */
class SocialAuthEntityTest extends EntityKernelTestBase {

  /**
   * The social_auth entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  private EntityInterface $entity;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $entityStorage;

  /**
   * The entity values to creation.
   *
   * @var array
   */
  private array $values = [];

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['social_api', 'social_auth'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('social_auth');

    $this->entityStorage = $this->entityTypeManager->getStorage('social_auth');

    $user = $this->drupalCreateUser();
    $this->values = [
      'user_id' => $user->id(),
      'plugin_id' => 'social_auth_provider_test',
      'provider_user_id' => 'provider_id_test',
      'additional_data' => ['foo' => 'bar'],
      'token' => 'token_test',
    ];

    $this->entity = $this->entityStorage->create($this->values);
    $this->entity->save();
  }

  /**
   * Tests entity creation.
   */
  public function testEntityCreation() {
    $entity1 = SocialAuth::create($this->values);
    $entity2 = $this->entityStorage->create($this->values);

    $values1 = $entity1->toArray();
    $values2 = $entity2->toArray();
    unset($values1['uuid'], $values2['uuid'], $values1['token'], $values2['token']);

    self::assertEquals($values1, $values2);
  }

  /**
   * Tests getter for user_id field.
   */
  public function testUserId() {
    self::assertEquals($this->values['user_id'], $this->entity->getUserId());
  }

  /**
   * Tests getter/setter for additional_data field.
   */
  public function testAdditionalData() {
    self::assertEquals($this->values['additional_data'], $this->entity->getAdditionalData());

    $new_value = [];
    $this->entity->setAdditionalData($new_value);
    $this->entity->save();
    self::assertEquals($new_value, $this->entity->getAdditionalData());
  }

}
