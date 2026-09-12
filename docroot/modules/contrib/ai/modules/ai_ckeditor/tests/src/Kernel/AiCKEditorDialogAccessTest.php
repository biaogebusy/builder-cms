<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_ckeditor\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests access control for AI CKEditor routes.
 *
 * @group ai_ckeditor
 */
#[RunTestsInSeparateProcesses]
class AiCKEditorDialogAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'editor',
    'ckeditor5',
    'ai',
    'ai_test',
    'ai_ckeditor',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['filter', 'user']);
    // Create the root user (uid=1) so test users don't bypass permissions.
    $this->createUser();
  }

  /**
   * Tests that the dialog route denies access without permission.
   */
  public function testDialogRouteDeniesAccessWithoutPermission(): void {
    $user = $this->createUser([]);
    $this->setCurrentUser($user);

    /** @var \Drupal\Core\Access\AccessManagerInterface $access_manager */
    $access_manager = $this->container->get('access_manager');
    $access = $access_manager->checkNamedRoute('ai_ckeditor.dialog', [], $user);

    $this->assertFalse($access);
  }

  /**
   * Tests that the dialog route grants access with permission.
   */
  public function testDialogRouteGrantsAccessWithPermission(): void {
    $user = $this->createUser(['use ai ckeditor']);
    $this->setCurrentUser($user);

    /** @var \Drupal\Core\Access\AccessManagerInterface $access_manager */
    $access_manager = $this->container->get('access_manager');
    $access = $access_manager->checkNamedRoute('ai_ckeditor.dialog', [], $user);

    $this->assertTrue($access);
  }

}
