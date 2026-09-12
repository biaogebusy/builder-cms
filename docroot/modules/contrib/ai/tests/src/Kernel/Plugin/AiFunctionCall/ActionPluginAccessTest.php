<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;

/**
 * Tests access control on the action plugin function call wrapper.
 *
 * @coversDefaultClass \Drupal\ai\Plugin\AiFunctionCall\ActionPluginBase
 *
 * @group ai
 */
class ActionPluginAccessTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
    'node',
    'user',
    'text',
    'field',
    'filter',
  ];

  /**
   * The AI function call plugin manager.
   *
   * @var \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   */
  protected $functionCallManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'filter', 'node']);

    $this->createContentType(['type' => 'page', 'name' => 'Page']);

    // Capture outgoing mail so the send-email action can succeed without
    // actually delivering anything.
    $this->config('system.mail')->set('interface.default', 'test_mail_collector')->save();

    // A role that the "add role" action will try to assign.
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();

    // Burn uid 1 (the superuser) so that the users created in each test do not
    // bypass access control and produce false positives.
    $this->setUpCurrentUser();

    $this->functionCallManager = \Drupal::service('plugin.manager.ai.function_calls');
  }

  /**
   * A known, mapped non-entity action is gated by its mapped permission.
   */
  public function testKnownNonEntityActionRequiresMappedPermission(): void {
    // Without 'administer actions': denied.
    $this->setCurrentUser($this->createUser());
    $function = $this->functionCallManager->createInstance('action_plugin:action_message_action');
    $function->setContextValue('message', 'Hello');
    $function->execute();
    $this->assertStringContainsString('access_denied', $function->getReadableOutput());

    // With 'administer actions': executes.
    $this->setCurrentUser($this->createUser(['administer actions']));
    $function = $this->functionCallManager->createInstance('action_plugin:action_message_action');
    $function->setContextValue('message', 'Hello');
    $function->execute();
    $this->assertStringContainsString('success', $function->getReadableOutput());
  }

  /**
   * An unknown non-entity action defers to the action's own access() check.
   */
  public function testUnknownNonEntityActionUsesActionsOwnAccessCheck(): void {
    // The fixture action is not in the curated map and gates itself on
    // 'administer site configuration'. A user lacking it is denied...
    $this->setCurrentUser($this->createUser(['administer actions']));
    $function = $this->functionCallManager->createInstance('action_plugin:ai_test_noop_action');
    $function->execute();
    $this->assertStringContainsString('access_denied', $function->getReadableOutput());

    // ...and a user the action's own access() allows may execute it.
    $this->setCurrentUser($this->createUser(['administer site configuration']));
    $function = $this->functionCallManager->createInstance('action_plugin:ai_test_noop_action');
    $function->execute();
    $this->assertStringContainsString('success', $function->getReadableOutput());
  }

  /**
   * Entity actions delegate to the entity's own access check.
   */
  public function testEntityActionDelegatesToEntityAccess(): void {
    $node = $this->createNode(['type' => 'page', 'status' => NodeInterface::PUBLISHED]);

    // A user with no node permissions cannot unpublish it.
    $this->setCurrentUser($this->createUser());
    $function = $this->functionCallManager->createInstance('action_plugin:entity:unpublish_action:node');
    $function->setContextValue('entity:node', 'node:' . $node->id());
    $function->execute();
    $this->assertStringContainsString('access_denied', $function->getReadableOutput());

    $node = $this->reloadNode($node);
    $this->assertTrue($node->isPublished(), 'Node remains published after a denied action.');

    // A user who may update the node (and edit its status field) can unpublish.
    $this->setCurrentUser($this->createUser(['bypass node access', 'administer nodes']));
    $function = $this->functionCallManager->createInstance('action_plugin:entity:unpublish_action:node');
    $function->setContextValue('entity:node', 'node:' . $node->id());
    $function->execute();
    $this->assertStringContainsString('success', $function->getReadableOutput());

    $node = $this->reloadNode($node);
    $this->assertFalse($node->isPublished(), 'Node is unpublished after an allowed action.');
  }

  /**
   * The save action is a normal entity action gated only by entity access.
   */
  public function testSaveActionDelegatesToEntityAccess(): void {
    $node = $this->createNode(['type' => 'page', 'status' => NodeInterface::PUBLISHED]);

    // No node permissions: denied.
    $this->setCurrentUser($this->createUser());
    $function = $this->functionCallManager->createInstance('action_plugin:entity:save_action:node');
    $function->setContextValue('entity:node', 'node:' . $node->id());
    $function->execute();
    $this->assertStringContainsString('access_denied', $function->getReadableOutput());

    // Entity update access is sufficient - no extra permission required.
    $this->setCurrentUser($this->createUser(['bypass node access', 'administer nodes']));
    $function = $this->functionCallManager->createInstance('action_plugin:entity:save_action:node');
    $function->setContextValue('entity:node', 'node:' . $node->id());
    $function->execute();
    $this->assertStringContainsString('success', $function->getReadableOutput());
  }

  /**
   * Scenario 1: adding a role to your own account as a privileged user works.
   */
  public function testAddRoleToOwnPrivilegedUser(): void {
    $user = $this->createUser(['administer users']);
    $this->setCurrentUser($user);

    $output = $this->runAddRole($user, 'editor');

    $this->assertStringContainsString('success', $output);
    $this->assertTrue($this->reloadUser($user)->hasRole('editor'));
  }

  /**
   * Scenario 2: a non-privileged user cannot add a role to their own account.
   */
  public function testAddRoleToOwnNonPrivilegedUser(): void {
    $user = $this->createUser();
    $this->setCurrentUser($user);

    $output = $this->runAddRole($user, 'editor');

    $this->assertStringContainsString('access_denied', $output);
    $this->assertFalse($this->reloadUser($user)->hasRole('editor'));
  }

  /**
   * Scenario 3: a privileged user can add a role to another user.
   */
  public function testAddRoleToOtherUserAsPrivilegedUser(): void {
    $target = $this->createUser();
    $this->setCurrentUser($this->createUser(['administer users']));

    $output = $this->runAddRole($target, 'editor');

    $this->assertStringContainsString('success', $output);
    $this->assertTrue($this->reloadUser($target)->hasRole('editor'));
  }

  /**
   * Scenario 4: a non-privileged user cannot add a role to another user.
   */
  public function testAddRoleToOtherUserAsNonPrivilegedUser(): void {
    $target = $this->createUser();
    $this->setCurrentUser($this->createUser());

    $output = $this->runAddRole($target, 'editor');

    $this->assertStringContainsString('access_denied', $output);
    $this->assertFalse($this->reloadUser($target)->hasRole('editor'));
  }

  /**
   * Scenario 5: a privileged user can send an email.
   */
  public function testSendEmailAsPrivilegedUser(): void {
    $this->setCurrentUser($this->createUser(['administer actions']));

    $this->assertStringContainsString('success', $this->runSendEmail());
  }

  /**
   * Scenario 6: a non-privileged user cannot send an email.
   */
  public function testSendEmailAsNonPrivilegedUser(): void {
    $this->setCurrentUser($this->createUser());

    $this->assertStringContainsString('access_denied', $this->runSendEmail());
  }

  /**
   * Runs the "add role to user" action and returns its readable output.
   */
  protected function runAddRole(UserInterface $target, string $rid): string {
    $function = $this->functionCallManager->createInstance('action_plugin:user_add_role_action');
    $function->setContextValue('entity:user', 'user:' . $target->id());
    $function->setContextValue('rid', $rid);
    $function->execute();
    return $function->getReadableOutput();
  }

  /**
   * Runs the "send email" action and returns its readable output.
   */
  protected function runSendEmail(): string {
    $function = $this->functionCallManager->createInstance('action_plugin:action_send_email_action');
    $function->setContextValue('recipient', 'recipient@example.com');
    $function->setContextValue('subject', 'Subject');
    $function->setContextValue('message', 'Message body');
    $function->execute();
    return $function->getReadableOutput();
  }

  /**
   * Reloads a user from storage, bypassing the static cache.
   */
  protected function reloadUser(UserInterface $user): UserInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $storage->resetCache([$user->id()]);
    return $storage->load($user->id());
  }

  /**
   * Reloads a node from storage, bypassing the static cache.
   */
  protected function reloadNode(NodeInterface $node): NodeInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    return $storage->loadUnchanged($node->id());
  }

}
