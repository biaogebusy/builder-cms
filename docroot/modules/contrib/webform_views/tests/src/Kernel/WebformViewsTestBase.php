<?php

namespace Drupal\Tests\webform_views\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformInterface;

/**
 * Reasonable starting point for testing integration between webform and views.
 */
abstract class WebformViewsTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'views', 'webform', 'webform_views', 'webform_views_test'];

  /**
   * Webform on which tests will be conducted.
   *
   * @var \Drupal\webform\WebformInterface
   */
  protected $webform;

  /**
   * Array of webform elements to apply to the webform.
   *
   * @var array
   */
  protected $webform_elements = [];

  /**
   * A list of webform submissions data to submit into the webform.
   *
   * Each sub array represents a single webform submission and its content will
   * be used as 'data' property of the webform submission.
   *
   * @var array
   */
  protected $webform_submissions_data = [];

  /**
   * A list of webform submissions data for the case of multivalue elements.
   *
   * Each sub array represents a single webform submission and its content will
   * be used as 'data' property of the webform submission.
   *
   * @var array
   */
  protected $webform_submission_multivalue_data = [];

  /**
   * View on which the tests will be executed.
   *
   * @var \Drupal\views\Entity\View
   */
  protected $view;

  /**
   * Array of additional handlers to apply to the view.
   *
   * It should be keyed by handler type. See View::getHandlerTypes() for the
   * list of known handler types.
   *
   * @var array
   */
  protected $view_handlers = [];

  /**
   * Account with super privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $admin;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('webform', ['webform']);
    $this->installConfig(['user', 'views', 'webform', 'webform_views', 'webform_views_test']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('webform_submission');

    $account = User::create([
      'name' => $this->randomString(),
      'mail' => 'admin@example.com',
      'pass' => 1,
      'roles' => ['administrator'],
    ]);
    $account->save();
    \Drupal::currentUser()->setAccount($account);
  }

  /**
   * Create a new webform with provided elements.
   *
   * @param array $webform_elements
   *   Array of elements to place into the webform.
   *
   * @return \Drupal\webform\WebformInterface
   *   Created and saved webform.
   */
  protected function createWebform(array $webform_elements) {
    $webform_entity_type = \Drupal::entityTypeManager()->getDefinition('webform');

    $webform = Webform::create([
      $webform_entity_type->getKey('id') => 'webform',
      $webform_entity_type->getKey('label') => $this->randomString(),
    ]);
    $webform->setElements($webform_elements);
    $webform->save();

    return $webform;
  }

  /**
   * Create webform submissions per provided submissions data.
   *
   * @param array $webform_submissions_data
   *   Array of webform submissions data. Each sub array represents a single
   *   webform submission to create and contents of the subarray will be
   *   utilized for 'data' property of the created submission.
   * @param \Drupal\webform\WebformInterface $webform
   *   Webform within which to create submissions.
   */
  protected function createWebformSubmissions(array $webform_submissions_data, WebformInterface $webform) {
    foreach ($webform_submissions_data as $submissions_data) {
      $webform_submission = WebformSubmission::create([
        'webform_id' => $webform->id(),
      ]);
      $webform_submission->setData($submissions_data);
      $webform_submission->save();
    }
  }

  /**
   * Initialize and save the view.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   Webform to use as a filter condition in the view. View will contain
   *   submissions only from this webform.
   * @param array $handlers
   *   Array of additional handlers to apply to the view.
   * @param string $view_id
   *   ID of the view to initialize.
   *
   * @return \Drupal\views\Entity\View
   *   Initialized and saved into DB view.
   */
  protected function initView(WebformInterface $webform, array $handlers, $view_id = 'webform_views_test') {
    $view = View::load($view_id);

    /** @var \Drupal\views\ViewExecutable $executable */
    $executable = $view->getExecutable();

    foreach ($handlers as $handler_type => $v) {
      foreach ($v as $handler) {
        $executable->addHandler('default', $handler_type, $handler['table'], $handler['field'], $handler['options'], isset($handler['id']) ? $handler['id'] : NULL);
      }
    }

    $view->save();

    // Let's reload the view because some handlers might initialize differently
    // since we've changed views configuration.
    $view = View::load($view->id());

    return $view;
  }

  /**
   * Execute and render the view.
   *
   * @param \Drupal\views\Entity\View $view
   *   The view to execute and render.
   * @param array $args
   *   Optional array of argument to supply to the view during its execution.
   * @param array $exclude_fields
   *   Array of field handler IDs that should be excluded from returned array.
   *
   * @return array
   *   Array of rendered cells of this view. Each sub array will represent a
   *   single row of the results of the view. Such sub array will be keyed by
   *   field handlers and corresponding values will be rendered HTML markup that
   *   the view produced for that field.
   */
  protected function renderView(View $view, array $args = array(), $exclude_fields = ['sid']) {
    /** @var \Drupal\views\ViewExecutable $executable */
    $executable = $view->getExecutable();

    $executable->executeDisplay(NULL, $args);

    $rendered_cells = [];

    for ($i = 0; $i < $executable->total_rows; $i++) {
      foreach ($executable->getHandlers('field') as $handler) {
        if (!in_array($handler['id'], $exclude_fields)) {
          $rendered_cells[$i][$handler['id']] = (string) $executable->style_plugin->getField($i, $handler['id']);
        }
      }
    }

    return $rendered_cells;
  }

}
