<?php

namespace Drupal\acquia_connector\Controller;

use Drupal\acquia_connector\Subscription;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Checks the current status of the Acquia Service.
 */
class StatusController extends ControllerBase {

  /**
   * Acquia Subscription Service.
   *
   * @var \Drupal\acquia_connector\Subscription
   */
  protected $subscription;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * WebhooksSettingsForm constructor.
   *
   * @param \Drupal\acquia_connector\Subscription $subscription
   *   The event dispatcher.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(Subscription $subscription, RequestStack $request_stack, ModuleHandlerInterface $module_handler) {
    $this->subscription = $subscription;
    $this->requestStack = $request_stack;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_connector.subscription'),
      $container->get('request_stack'),
      $container->get('module_handler')
    );
  }

  /**
   * Menu callback for 'admin/config/services/acquia-agent/refresh-status'.
   */
  public function refresh() {
    // Refresh subscription information, so we are sure about our update status.
    // We send a heartbeat here so that all of our status information gets
    // updated locally via the return data.
    $this->subscription->getSubscription(TRUE);

    // Return to the setting pages (or destination).
    return $this->redirect('system.status');
  }

  /**
   * Return JSON site status.
   *
   * Used by Acquia uptime monitoring.
   */
  public function json() {
    $data = [
      'version' => '1.0',
      'data' => [
        'maintenance_mode' => (bool) $this->state()->get('system.maintenance_mode'),
        'cache' => $this->moduleHandler->moduleExists('page_cache'),
        'block_cache' => FALSE,
      ],
    ];

    return new JsonResponse($data);
  }

  /**
   * Access callback for json() callback.
   */
  public function access() {
    $request = $this->requestStack->getCurrentRequest();
    assert($request !== NULL);
    $nonce = $request->get('nonce', FALSE);
    $connector_config = $this->config('acquia_connector.settings');

    // If we don't have all the query params, leave now.
    if (!$nonce) {
      return AccessResult::forbidden('Missing nonce.');
    }

    $sub_data = $this->subscription->getSubscription();
    if (empty($sub_data['uuid'])) {
      return AccessResult::forbidden('Missing application UUID.');
    }
    $sub_uuid = $sub_data['uuid'];

    $expected_hash = hash('sha1', "{$sub_uuid}:{$nonce}");
    // If the generated hash matches the hash from $_GET['key'], we're good.
    if ($request->get('key', FALSE) === $expected_hash) {
      return AccessResult::allowed();
    }

    // Log the request if validation failed and debug is enabled.
    if ($connector_config->get('debug')) {
      $info = [
        'sub_data' => $sub_data,
        'sub_uuid_from_data' => $sub_uuid,
        'expected_hash' => $expected_hash,
        'get' => $request->query->all(),
        'server' => $request->server->all(),
        'request' => $request->request->all(),
      ];

      $this->getLogger('acquia_agent')->notice('Site status request: @data', ['@data' => var_export($info, TRUE)]);
    }

    return AccessResult::forbidden('Could not validate key.');
  }

}
