<?php

namespace Drupal\Tests\page_manager\Unit;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\Context\LazyContextRepository;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\page_manager\EventSubscriber\CurrentUserContext;
use Prophecy\Argument;

/**
 * Tests the current user context.
 *
 * @coversDefaultClass \Drupal\page_manager\EventSubscriber\CurrentUserContext
 *
 * @group PageManager
 */
class CurrentUserContextTest extends PageContextTestBase {

  /**
   * @covers ::onPageContext
   */
  public function testOnPageContext() {
    $currentUser = $this->createMock(AccountProxyInterface::class);

    $contextRepository = $this->createMock(LazyContextRepository::class);
    $currentUserContext = new EntityContext(new EntityContextDefinition('user', 'current_user_context'), $currentUser->getAccount());
    $contextRepository->expects($this->once())
      ->method('getRunTimeContexts')
      ->willReturn(['@user.current_user_context:current_user' => $currentUserContext]);

    $this->page->addContext('@user.current_user_context:current_user', Argument::type(Context::class))
      ->shouldBeCalled()
      ->willReturn($this->page);
    $this->page->addContext('current_user', Argument::type(Context::class))
      ->shouldBeCalled()
      ->willReturn($this->page);
    $route_param_context = new CurrentUserContext($contextRepository);
    $route_param_context->onPageContext($this->event);
  }

}
