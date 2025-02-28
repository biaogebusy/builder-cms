<?php

namespace Drupal\Tests\profile\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileType;

/**
 * Tests token resolution for profiles.
 *
 * @requires module token
 * @group profile
 */
class ProfileTokenTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'filter',
    'profile',
    'views',
    'token',
  ];

  /**
   * The user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The profile storage.
   *
   * @var \Drupal\profile\ProfileStorageInterface
   */
  protected $profileStorage;

  /**
   * The entity view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $viewBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('profile');
    $this->installEntitySchema('view');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(self::$modules);
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->profileStorage = $entity_type_manager->getStorage('profile');
    $this->viewBuilder = $entity_type_manager->getViewBuilder('profile');
    $this->user = $this->createUser();
  }

  /**
   * Tests tokens for profiles.
   */
  public function testToken() {
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
    $profile = Profile::create([
      'type' => $profile_type->id(),
      'uid' => $this->user->id(),
      'profile_fullname' => $this->randomMachineName(),
    ]);
    $profile->save();
    $profile = $this->reloadEntity($profile);

    // Load $field_token_output with the output of
    // [user:profile:profile_fullname].
    $token_service = \Drupal::service('token');
    $field_token = '[user:' . $profile_type->id() . ':profile_fullname]';
    $field_token_output = $token_service->replace($field_token, ['user' => $this->user]);

    // Load $entity_token_output with the output of [user:profile].
    $entity_token = '[user:' . $profile_type->id() . ']';
    $entity_token_output = $token_service->replace($entity_token, ['user' => $this->user]);

    // Load the profile entity and render the field value so
    // it can be compared to the token output.
    $entity_type_manager = \Drupal::entityTypeManager();

    /** @var \Drupal\Core\Entity\EntityViewBuilderInterface $view_builder */
    $view_builder = $entity_type_manager->getViewBuilder('profile');
    $entity_view = $view_builder->view($profile, 'token');
    $field_view = $view_builder->viewField($profile->get('profile_fullname'));

    // Add the pre_render method to match the rendered output of a field token.
    $field_output['#pre_render'][] = '\Drupal\token\TokenFieldRender::preRender';

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $rendered_field = $renderer->renderPlain($field_view);
    $rendered_entity = $renderer->renderRoot($entity_view);

    // Verify the tokens matches the rendered values.
    $this->assertStringContainsString($field_token_output, $rendered_field);
    $this->assertStringContainsString($entity_token_output, $rendered_entity);
  }

}
