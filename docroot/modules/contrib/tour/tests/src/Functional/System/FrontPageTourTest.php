<?php

namespace Drupal\Tests\tour\Functional\System;

use Drupal\Tests\tour\Functional\TourTestBase;
use Drupal\tour\Entity\Tour;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests tour functionality when using front page rout.
 */
#[Group('tour')]
class FrontPageTourTest extends TourTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'node', 'tour_test'];

  /**
   * {@inheritdoc}
   */
  protected array $permissions = ['administer blocks', 'access tour'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page']);
    $id = $this->drupalCreateNode(['promote' => 1])->id();

    // Configure 'node' as front page.
    $this->config('system.site')->set('page.front', '/node/' . $id)->save();
  }

  /**
   * Test tips appear for frontpage.
   */
  public function testFrontPageTour(): void {
    $this->drupalGet('<front>');
    $this->assertTourTips();
  }

  /**
   * Test tours targeting actual route name also appear on front page.
   *
   * When the front page uses a node (entity.node.canonical route),
   * tours targeting either '<front>' or 'entity.node.canonical' should appear.
   * This supports page manager variants where different layouts may have
   * different route names.
   */
  public function testFrontPageTourWithActualRouteName(): void {
    // Create a tour that targets the actual route (entity.node.canonical)
    // instead of <front>.
    Tour::create([
      'id' => 'tour-actual-route',
      'label' => 'Tour for actual route',
      'status' => TRUE,
      'routes' => [
        ['route_name' => 'entity.node.canonical'],
      ],
      'tips' => [
        'tip1' => [
          'id' => 'tip1',
          'plugin' => 'text',
          'label' => 'Actual route tip',
          'body' => 'This tour targets the actual route name',
          'weight' => 1,
        ],
      ],
    ])->save();

    // Visit front page - should see tours for both <front> and actual route.
    $this->drupalGet('<front>');

    // Check that tours are present.
    $drupalSettings = $this->getDrupalSettings();
    $this->assertArrayHasKey('_tour_internal', $drupalSettings);

    // Should find tips from both the <front> tour and the actual route tour.
    $tipLabels = array_column($drupalSettings['_tour_internal'], 'title');
    $this->assertContains('The front page tip', $tipLabels);
    $this->assertContains('Actual route tip', $tipLabels);
  }

}
