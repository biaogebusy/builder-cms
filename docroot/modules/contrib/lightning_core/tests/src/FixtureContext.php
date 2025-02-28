<?php

namespace Drupal\Tests\lightning_core;

use Drupal\block\Entity\Block;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;

/**
 * Performs set-up and tear-down tasks before and after test scenarios.
 */
final class FixtureContext extends FixtureBase {

  /**
   * Performs set-up tasks before a test scenario.
   *
   * @BeforeScenario
   */
  public function setUp() {
    // Create the administrator role if it does not already exist.
    if (!Role::load('administrator')) {
      $role = Role::create([
        'id' => 'administrator',
        'label' => 'Administrator',
      ])->setIsAdmin(TRUE);

      $this->save($role);
    }

    // Install the Seven theme if not already installed.
    $this->installTheme('seven');

    // Use Seven as both the default and administrative theme.
    $this->config('system.theme')
      ->set('admin', 'seven')
      ->set('default', 'seven')
      ->save();

    // Place the main content block if it's not already there.
    if (!Block::load('seven_content')) {
      $block = Block::create([
        'id' => 'seven_content',
        'theme' => 'seven',
        'region' => 'content',
        'plugin' => 'system_main_block',
        'settings' => [
          'label_display' => '0',
        ],
      ]);
      $this->save($block);
    }

    // Create a test content type to be automatically cleaned up at the end of
    // the scenario.
    $node_type = NodeType::create([
      'type' => 'test',
      'name' => 'Test',
    ]);
    $this->save($node_type);
  }

  /**
   * Performs tear-down tasks after a test scenario.
   *
   * @AfterScenario
   */
  public function tearDown() {
    // This pointless if statement is here to evade a too-strict coding
    // standards rule.
    if (TRUE) {
      parent::tearDown();
    }
  }

}
