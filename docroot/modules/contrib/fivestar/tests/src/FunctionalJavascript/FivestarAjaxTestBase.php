<?php

namespace Drupal\Tests\fivestar\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\fivestar\Traits\FivestarTestTrait;

/**
 * Test base for the Fivestar module.
 *
 * @group Fivestar
 */
abstract class FivestarAjaxTestBase extends WebDriverTestBase {
  use FivestarTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'fivestar',
    'votingapi',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $displayRepository;

  /**
   * A user with permission to administer Fivestar.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A user with permission to vote.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $voterUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create content type for testing.
    $this->drupalCreateContentType([
      'type' => 'test_node_type',
      'name' => 'Rated content type',
    ]);

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displayRepository */
    $this->displayRepository = \Drupal::service('entity_display.repository');

    // Create users with different permissions.
    $this->adminUser = $this->createUser([
      'create test_node_type content',
      'edit any test_node_type content',
      'rate content',
    ]);
    $this->voterUser = $this->createUser([
      'rate content',
    ]);
  }

}
