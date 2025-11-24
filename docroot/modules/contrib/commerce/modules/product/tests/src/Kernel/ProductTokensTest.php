<?php

namespace Drupal\Tests\commerce_product\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the product tokens.
 *
 * @group commerce
 */
class ProductTokensTest extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'token',
    'path',
    'commerce_product',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    $this->installConfig(['commerce_product']);
    $this->installConfig(['system']);

    $user = $this->createUser(['administer commerce_product']);
    $this->container->get('current_user')->setAccount($user);
  }

  /**
   * Tests current and default variation tokens for products.
   */
  public function testTokens() {
    $token = $this->container->get('token');
    $variations = [];

    for ($i = 1; $i <= 3; $i++) {
      $variation = ProductVariation::create([
        'type' => 'default',
        'sku' => 'SKU-' . $i,
        'title' => $this->randomString(),
      ]);
      $variation->save();
      $variations[] = $variation;
    }

    $variations = array_reverse($variations);
    $product = Product::create([
      'type' => 'default',
      'variations' => $variations,
    ]);
    $product->save();
    $request = Request::create('');
    $request->query->add([
      'v' => end($variations)->id(),
    ]);

    $token_data = ['commerce_product' => $product];
    $bubbleable_metadata = new BubbleableMetadata();

    // Push the request to the request stack so `current_route_match` works.
    $this->container->get('request_stack')->push($request);
    // Ensure SKU-1 is returned for current variation when the v query string is present.
    $this->assertEquals('SKU-1', $token->replace('[commerce_product:current_variation:sku]', $token_data, [], $bubbleable_metadata));
    // Ensure SKU-3 is returned for default variation.
    $this->assertEquals('SKU-3', $token->replace('[commerce_product:default_variation:sku]', $token_data, [], $bubbleable_metadata));

    // Invalid variation ID returns default variation.
    $request = Request::create('');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->query->add([
      'v' => '1111111',
    ]);
    // Push the request to the request stack so `current_route_match` works.
    $this->container->get('request_stack')->push($request);
    // Ensure the SKU-3 is returned when an invalid v query string is present.
    $this->assertEquals('SKU-3', $token->replace('[commerce_product:current_variation:sku]', $token_data, [], $bubbleable_metadata));

    // Test loading context via sku.
    $request = Request::create('');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->query->add([
      'sku' => end($variations)->getSku(),
    ]);
    $this->container->get('request_stack')->push($request);
    // Ensure the SKU-3 is returned when the sku query string is present.
    $this->assertEquals('SKU-3', $token->replace('[commerce_product:current_variation:sku]', $token_data, [], $bubbleable_metadata));
  }

}
