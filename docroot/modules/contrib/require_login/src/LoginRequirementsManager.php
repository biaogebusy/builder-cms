<?php

namespace Drupal\require_login;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Condition\ConditionAccessResolverTrait;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides login requirements manager.
 */
class LoginRequirementsManager implements LoginRequirementsManagerInterface {

  use ConditionAccessResolverTrait;

  /**
   * Defines protected routes not subject to authentication.
   */
  const PROTECTED_ROUTES = [
    'user.login',
    'user.register',
    'user.pass',
    'user.reset',
    'user.reset.form',
    'user.reset.login',
    'image.style_public',
    'system.css_asset',
    'system.js_asset',
  ];

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected ContextRepositoryInterface $contextRepository;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected ContextHandlerInterface $contextHandler;

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Executable\ExecutableManagerInterface
   */
  protected ExecutableManagerInterface $conditionPluginManager;

  /**
   * LoginRequirementsManager constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $contextRepository
   *   The context repository.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $contextHandler
   *   The context handler.
   * @param \Drupal\Core\Executable\ExecutableManagerInterface $conditionPluginManager
   *   The condition plugin manager.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, MessengerInterface $messenger, ConfigFactoryInterface $configFactory, RequestStack $requestStack, AccountProxyInterface $currentUser, RouteMatchInterface $routeMatch, ContextRepositoryInterface $contextRepository, ContextHandlerInterface $contextHandler, ExecutableManagerInterface $conditionPluginManager) {
    $this->moduleHandler = $moduleHandler;
    $this->messenger = $messenger;
    $this->configFactory = $configFactory;
    $this->config = $this->configFactory->get('require_login.settings');
    $this->requestStack = $requestStack;
    $this->currentUser = $currentUser;
    $this->routeMatch = $routeMatch;
    $this->contextRepository = $contextRepository;
    $this->contextHandler = $contextHandler;
    $this->conditionPluginManager = $conditionPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(HttpExceptionInterface $exception = NULL) {
    if ($this->currentUser->isAuthenticated() || in_array($this->routeMatch->getRouteName(), static::PROTECTED_ROUTES, TRUE)) {
      return FALSE;
    }
    if ($exception instanceof AccessDeniedHttpException && !$this->config->get('extra.include_403')) {
      return FALSE;
    }
    elseif ($exception instanceof NotFoundHttpException && !$this->config->get('extra.include_404')) {
      return FALSE;
    }

    // Run requirements condition evaluations. All conditions must have a
    // positive evaluation to require authentication.
    $conditions = [];
    foreach (new ConditionPluginCollection($this->conditionPluginManager, $this->config->get('requirements')) as $id => $condition) {
      if ($condition instanceof ContextAwarePluginInterface) {
        try {
          $contexts = $this->contextRepository->getRuntimeContexts(
            array_values($condition->getContextMapping())
          );
          $this->contextHandler->applyContextMapping($condition, $contexts);
        }
        catch (\Exception $e) {
          // Always evaluate all conditions.
        }
      }
      $conditions[$id] = $condition;
    }
    $eval = $this->resolveConditions($conditions, 'and');

    // Allow other modules to alter the evaluation result.
    $this->moduleHandler->alter('require_login_evaluation', $eval);

    if ($eval) {
      return $this->redirect();
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function redirect(): Url {
    if ($message = trim($this->config->get('login_message'))) {
      $this->messenger->addWarning(Markup::create(Xss::filterAdmin($message)));
    }
    $uri = $this->config->get('login_path');
    if (!($destination = $this->config->get('login_destination'))) {
      $destination = $this->requestStack->getCurrentRequest()->getRequestUri();
    }
    return Url::fromUserInput($uri ?: '/user/login', ['query' => ['destination' => $destination]]);
  }

}
