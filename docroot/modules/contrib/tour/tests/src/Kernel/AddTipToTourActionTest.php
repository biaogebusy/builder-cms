<?php

declare(strict_types=1);

namespace Drupal\Tests\tour\Kernel;

use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\tour\Entity\Tour;
use Drupal\tour\Plugin\ConfigAction\AddTipToTour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test Adding a tip to tour action.
 */
#[Group('tour')]
#[CoversClass(AddTipToTour::class)]
class AddTipToTourActionTest extends KernelTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tour', 'system'];

  /**
   * The configuration action manager.
   */
  private readonly ConfigActionManager $configActionManager;

  /**
   * Config data.
   */
  protected array $data;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->configActionManager = $this->container->get('plugin.manager.config_action');
    $this->data = [
      1 => [
        'id' => 'tip_recipe_example',
        'plugin' => 'text',
        'label' => 'My new Tip!',
        'weight' => 1,
        'position' => 'top',
        'selector' => '#main-content',
        'body' => 'Hello World!',
        'fail_if_exists' => FALSE,
      ],
      2 => [
        'id' => 'another_tip_recipe_example',
        'plugin' => 'text',
        'label' => 'My second new Tip!',
        'weight' => 2,
        'position' => 'top',
        'selector' => '#main-content',
        'body' => 'Second tip!',
        'fail_if_exists' => FALSE,
      ],
    ];
  }

  /**
   * Tests that the action fails if tour doesn't exist.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testFailIfTourNotExists(): void {
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('No tour found to apply tip to.');
    $this->configActionManager->applyAction('addTipToTour', 'tour.tour.tip_edit', $this->data);
  }

  /**
   * Tests that the action succeeds if tour already has the tip.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testSucceedsWhenTourAlreadyHasTipButAllowsOverride(): void {
    $this->installConfig('tour');
    // First time succeeds.
    $this->configActionManager->applyAction('addTipToTour', 'tour.tour.tip_edit', $this->data);
    // Second time it should still run because fail_if_exists is FALSE.
    $this->configActionManager->applyAction('addTipToTour', 'tour.tour.tip_edit', $this->data);
    $this->assertArrayHasKey('tip_recipe_example', Tour::load('tip_edit')?->getTips());
  }

  /**
   * Tests that the action fails if tour already has the tip.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testFailIfTourAlreadyHasTipOverride(): void {
    $this->installConfig('tour');
    // First time succeeds.
    $this->configActionManager->applyAction('addTipToTour', 'tour.tour.tip_edit', $this->data);
    // Second time should fail because fail_if_exists is TRUE.
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('Tour tip_edit already has a tip with the ID tip_recipe_example.');
    $this->configActionManager->applyAction('addTipToTour', 'tour.tour.tip_edit', [
      [
        'id' => 'tip_recipe_example',
        'plugin' => 'text',
        'label' => 'My new Tip!',
        'weight' => 1,
        'position' => 'top',
        'selector' => '#main-content',
        'body' => 'Hello World!',
        'fail_if_exists' => TRUE,
      ],
    ]);
  }

  /**
   * Tests that the action applies cleanly and works.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testActionWorks(): void {
    $this->installConfig('tour');

    $tour = Tour::load('tip_edit');
    $tips = $tour->getTips();
    $this->assertArrayNotHasKey('tip_recipe_example', $tips);
    $this->assertArrayNotHasKey('another_tip_recipe_example', $tips);

    $this->configActionManager->applyAction('addTipToTour', 'tour.tour.tip_edit', $this->data);

    $tour = Tour::load('tip_edit');
    $tips = $tour->getTips();
    $this->assertArrayHasKey('tip_recipe_example', $tips);
    $this->assertArrayHasKey('another_tip_recipe_example', $tips);
  }

}
