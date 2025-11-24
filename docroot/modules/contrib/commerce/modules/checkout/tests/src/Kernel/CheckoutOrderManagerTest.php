<?php

namespace Drupal\Tests\commerce_checkout\Kernel;

use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Url;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_checkout\Entity\CheckoutFlow;
use Drupal\commerce_order\Entity\Order;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the checkout order manager.
 *
 * @group commerce
 */
class CheckoutOrderManagerTest extends OrderKernelTestBase {

  /**
   * A sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The checkout order manager.
   *
   * @var \Drupal\commerce_checkout\CheckoutOrderManagerInterface
   */
  protected $checkoutOrderManager;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_checkout',
    'commerce_checkout_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('commerce_checkout');

    $user = $this->createUser();
    $this->order = Order::create([
      'type' => 'default',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'store_id' => $this->store->id(),
    ]);
    $this->order->save();

    $this->checkoutOrderManager = $this->container->get('commerce_checkout.checkout_order_manager');
  }

  /**
   * Fakes a request so that the current_route_match works.
   *
   * @todo Remove this when CheckoutFlowBase stops using the route match.
   */
  protected function setupRequestWithOrderParameter() {
    $url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $this->order->id(),
    ]);
    $route_provider = $this->container->get('router.route_provider');
    $route = $route_provider->getRouteByName($url->getRouteName());
    $request = Request::create($url->toString());
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->attributes->add([
      RouteObjectInterface::ROUTE_OBJECT => $route,
      'commerce_order' => $this->order,
    ]);
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Tests getting the order's checkout flow.
   */
  public function testGetCheckoutFlow() {
    $this->setupRequestWithOrderParameter();

    $checkout_flow = $this->checkoutOrderManager->getCheckoutFlow($this->order);
    $this->assertInstanceOf(CheckoutFlow::class, $checkout_flow);
    $this->assertEquals('default', $checkout_flow->id());

    $this->order->checkout_flow->target_id = 'deleted';
    $checkout_flow = $this->checkoutOrderManager->getCheckoutFlow($this->order);
    $this->assertInstanceOf(CheckoutFlow::class, $checkout_flow);
    $this->assertEquals('default', $checkout_flow->id());
  }

  /**
   * Tests getting the order's checkout step ID.
   */
  public function testGetCheckoutStepId() {
    $this->setupRequestWithOrderParameter();

    // Empty requested step ID when no checkout step was set.
    $step_id = $this->checkoutOrderManager->getCheckoutStepId($this->order);
    $this->assertEquals('login', $step_id);

    $this->order->set('checkout_step', 'review');
    // Empty requested step ID.
    $step_id = $this->checkoutOrderManager->getCheckoutStepId($this->order);
    $this->assertEquals('review', $step_id);

    // Invalid requested step ID.
    $step_id = $this->checkoutOrderManager->getCheckoutStepId($this->order, 'fake_step');
    $this->assertEquals('review', $step_id);

    // Requested step ID matches the current step ID.
    $step_id = $this->checkoutOrderManager->getCheckoutStepId($this->order, 'review');
    $this->assertEquals('review', $step_id);

    // Requested step ID is before the current step ID.
    $step_id = $this->checkoutOrderManager->getCheckoutStepId($this->order, 'order_information');
    $this->assertEquals('order_information', $step_id);

    // Requested step ID is after the current step ID.
    $step_id = $this->checkoutOrderManager->getCheckoutStepId($this->order, 'payment');
    $this->assertEquals('review', $step_id);

    // Non-complete requested step ID for a placed order.
    $this->order->state = 'validation';
    $step_id = $this->checkoutOrderManager->getCheckoutStepId($this->order, 'payment');
    $this->assertEquals('complete', $step_id);
    $step_id = $this->checkoutOrderManager->getCheckoutStepId($this->order, 'review');
    $this->assertEquals('complete', $step_id);

    // Plugin may allow other steps on non-draft orders.
    $this->order->state = 'validation';
    $checkout_flow = $this->checkoutOrderManager->getCheckoutFlow($this->order);
    $checkout_flow->setPluginId('commerce_checkout_test_post_completion_steps');
    $step_id = $this->checkoutOrderManager->getCheckoutStepId($this->order, 'review');
    $this->assertEquals('review', $step_id);

  }

  /**
   * Tests getting checkout's visible steps.
   */
  public function testGetVisibleSteps() {
    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
    $checkout_flow = $this->checkoutOrderManager->getCheckoutFlow($this->order);

    /** @var \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow_plugin */
    $checkout_flow_plugin = $checkout_flow->getPlugin();

    /** @var array $steps */
    $steps = $checkout_flow_plugin->getVisibleSteps();

    $expected_steps = [
      'login',
      'order_information',
      'review',
      'complete',
    ];
    $this->assertEquals($expected_steps, array_keys($steps));
  }

  /**
   * Tests getting the order from the checkout flow plugin.
   */
  public function testGetOrderFromCheckoutPane() {
    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
    $checkout_flow = $this->checkoutOrderManager->getCheckoutFlow($this->order);

    /** @var \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow_plugin */
    $checkout_flow_plugin = $checkout_flow->getPlugin();

    // Assert that the checkout flow plugin contains the order.
    $this->assertSame($this->order, $checkout_flow_plugin->getOrder());
  }

}
