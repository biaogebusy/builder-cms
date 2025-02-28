<?php

namespace Drupal\Tests\entity_print_views\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\entity_print_views\Controller\ViewPrintController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Views access test.
 *
 * @group entity_print_views
 */
class ViewsAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'node',
    'user',
    'entity_print',
    'views',
    'entity_print_views',
    'entity_print_views_test_views',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
    $this->installConfig('entity_print_views_test_views');

    // Discard user 1 which causes havoc with access tests.
    $this->createUser();
  }

  /**
   * Test the access works for viewing the PDF's.
   */
  public function testEntityPrintAccess() {
    // By default we don't have the Entity Print permissions and cannot see the
    // printable version of the view.
    $this->assertFalse($this->checkAccess(new AnonymousUserSession(), 'my_test_view', 'page_1'), 'Access not allowed because we do not have permission');

    // Only entity print access.
    $account = $this->createUser(['entity print views access']);
    $this->assertFalse($this->checkAccess($account, 'my_test_view', 'page_1'), 'Access not allowed because user does not have permission set on view');

    // Only views access.
    $account = $this->createUser(['administer nodes']);
    $this->assertFalse($this->checkAccess($account, 'my_test_view', 'page_1'), 'Access not allowed because user does not have entity print permission.');

    // Both entity print access and views access.
    $account = $this->createUser([
      'entity print views access',
      'administer nodes',
    ]);
    $this->assertTrue($this->checkAccess($account, 'my_test_view', 'page_1'), 'Access allowed for user with "entity print views access" and "administer nodes"');
  }

  /**
   * Checks access for the user to print the view.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account we're checking against.
   * @param string $view_name
   *   The view name.
   * @param string $display_id
   *   The view display.
   *
   * @return bool
   *   TRUE if the user has access otherwise FALSE.
   */
  protected function checkAccess(AccountInterface $account, $view_name, $display_id) {
    $this->container->get('current_user')->setAccount($account);
    $controller = ViewPrintController::create($this->container);
    return $controller->checkAccess('pdf', $view_name, $display_id)->isAllowed();
  }

}
