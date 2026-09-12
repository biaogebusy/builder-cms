<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Kernel tests for message.install hooks.
 *
 * @group Message
 */
class MessageInstallTest extends KernelTestBase {

  use MessageTemplateCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'message',
    'system',
    'user',
    'views',
  ];

  /**
   * Config names excluded from schema checking.
   *
   * Needed so we can stage the pre-update action plugin ID.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = [
    'system.action.legacy_message_delete',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'message', 'system', 'user']);
    $this->installEntitySchema('message');
    $this->installEntitySchema('user');
    $this->container->get('module_handler')->loadInclude('message', 'install');
  }

  /**
   * Tests message_uninstall() deletes message settings config.
   */
  public function testUninstall(): void {
    $this->assertNotEmpty($this->config('message.settings')->get());
    message_uninstall();
    $this->assertNull($this->config('message.settings')->get('purge_enable'));
    $this->assertTrue($this->config('message.settings')->isNew());
  }

  /**
   * Tests message_update_8100() fixes the typo permission.
   */
  public function testUpdate8100(): void {
    $role = Role::create([
      'id' => 'typo_role',
      'label' => 'Typo role',
    ]);
    $role->save();

    // Role::save() strips unknown permissions, so inject the typo via config.
    // cspell:ignore adminster
    $this->config('user.role.typo_role')
      ->set('permissions', ['adminster messages'])
      ->save();
    $this->container->get('entity_type.manager')
      ->getStorage('user_role')
      ->resetCache();

    message_update_8100();

    $role = Role::load('typo_role');
    $this->assertInstanceOf(RoleInterface::class, $role);
    // cspell:ignore adminster
    $this->assertFalse($role->hasPermission('adminster messages'));
    $this->assertTrue($role->hasPermission('administer messages'));
  }

  /**
   * Tests message_update_8102() migrates the delete action plugin ID.
   */
  public function testUpdate8102(): void {
    // Create a valid action, then stage the legacy plugin ID in config.
    $this->container->get('entity_type.manager')
      ->getStorage('action')
      ->create([
        'id' => 'legacy_message_delete',
        'label' => 'Delete messages',
        'type' => 'message',
        'plugin' => 'entity:delete_action:message',
        'configuration' => [],
      ])
      ->save();
    $this->config('system.action.legacy_message_delete')
      ->set('plugin', 'message_delete_action')
      ->save();
    $this->container->get('entity_type.manager')
      ->getStorage('action')
      ->resetCache();

    message_update_8102();

    $this->assertEquals(
      'entity:delete_action:message',
      $this->config('system.action.legacy_message_delete')->get('plugin')
    );
  }

  /**
   * Tests message_update_8105() updates the message view path.
   */
  public function testUpdate8105(): void {
    $this->config('views.view.message')
      ->setData([
        'langcode' => 'en',
        'status' => TRUE,
        'dependencies' => ['module' => ['message']],
        'id' => 'message',
        'label' => 'Message',
        'module' => 'views',
        'description' => '',
        'tag' => '',
        'base_table' => 'message_field_data',
        'base_field' => 'mid',
        'display' => [
          'page_1' => [
            'display_plugin' => 'page',
            'id' => 'page_1',
            'display_title' => 'Page',
            'position' => 1,
            'display_options' => [
              'path' => 'admin/content/messages',
            ],
          ],
        ],
      ])
      ->save(TRUE);

    message_update_8105();

    $this->assertEquals(
      'admin/content/message',
      $this->config('views.view.message')->get('display.page_1.display_options.path')
    );
  }

  /**
   * Tests message_update_8106() installs changed and backfills from created.
   */
  public function testUpdate8106(): void {
    $template = $this->createMessageTemplate(
      'install_changed',
      'Install changed',
      'Description',
      ['Changed field update.']
    );
    $message = Message::create(['template' => $template->id()]);
    $message->setCreatedTime(1234567890);
    $message->save();
    $mid = (int) $message->id();

    $update_manager = $this->container->get('entity.definition_update_manager');
    $changed = $update_manager->getFieldStorageDefinition('changed', 'message');
    $this->assertNotNull($changed);
    $update_manager->uninstallFieldStorageDefinition($changed);

    $data_table = $this->container->get('entity_type.manager')
      ->getDefinition('message')
      ->getDataTable();
    $this->assertFalse(
      $this->container->get('database')->schema()->fieldExists($data_table, 'changed')
    );

    // Ensure created remains so the update can copy it.
    $this->container->get('database')->update($data_table)
      ->fields(['created' => 1234567890])
      ->condition('mid', $mid)
      ->execute();

    message_update_8106();

    $this->assertTrue(
      $this->container->get('database')->schema()->fieldExists($data_table, 'changed')
    );
    $changed_value = $this->container->get('database')->select($data_table, 'm')
      ->fields('m', ['changed'])
      ->condition('mid', $mid)
      ->execute()
      ->fetchField();
    $this->assertEquals('1234567890', (string) $changed_value);

    // Re-running on a site that already has the field must fail.
    try {
      message_update_8106();
      $this->fail('Expected message_update_8106() to fail when changed already exists.');
    }
    catch (\Throwable $e) {
      $this->assertNotEmpty($e->getMessage());
    }
  }

}
