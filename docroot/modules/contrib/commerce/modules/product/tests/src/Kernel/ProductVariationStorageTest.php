<?php

namespace Drupal\Tests\commerce_product\Kernel;

use Drupal\commerce_product\ProductVariationStorageInterface;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the product variation storage.
 *
 * @group commerce
 */
class ProductVariationStorageTest extends CommerceKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'path',
    'commerce_product',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('commerce_product');
    $this->installConfig(['commerce_product']);

    $user = $this->createUser(['administer commerce_product']);
    $this->container->get('current_user')->setAccount($user);
  }

  /**
   * Tests loading variations by SKU.
   */
  public function testLoadBySku() {
    $sku = strtolower($this->randomMachineName());
    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => $sku,
      'title' => $this->randomString(),
    ]);
    $variation->save();
    $product = Product::create([
      'type' => 'default',
      'variations' => [$variation],
    ]);
    $product->save();

    $variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');
    assert($variation_storage instanceof ProductVariationStorageInterface);
    $result = $variation_storage->loadBySku('FAKE');
    $this->assertNull($result);

    $result = $variation_storage->loadBySku($sku);
    $this->assertEquals($result->id(), $variation->id());
  }

  /**
   * Tests loadEnabled() function.
   */
  public function testLoadEnabled() {
    $variations = [];
    for ($i = 1; $i <= 3; $i++) {
      $variation = ProductVariation::create([
        'type' => 'default',
        'sku' => strtolower($this->randomMachineName()),
        'title' => $this->randomString(),
        'status' => $i % 2,
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
    $variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');
    assert($variation_storage instanceof ProductVariationStorageInterface);
    $variationsFiltered = $variation_storage->loadEnabled($product);
    $this->assertEquals(3, count($variationsFiltered), 'for the admin user, 3 out of 3 variations are enabled');

    $product = Product::create([
      'type' => 'default',
      'variations' => $variations,
    ]);
    $product->save();
    $user = $this->createUser(['view commerce_product']);
    $this->container->get('current_user')->setAccount($user);
    $variationsFiltered = $variation_storage->loadEnabled($product);
    $this->assertEquals(2, count($variationsFiltered), 'for a normal user, 2 out of 3 variations are enabled');
    $this->assertEquals(reset($variations)->getSku(), reset($variationsFiltered)->getSku(), 'The sort order of the variations remains the same');
  }

  /**
   * Tests loadFromContext() method.
   */
  public function testLoadFromContext() {
    $variations = [];
    for ($i = 1; $i <= 3; $i++) {
      $variation = ProductVariation::create([
        'type' => 'default',
        'sku' => strtolower($this->randomMachineName()),
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
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->query->add([
      'v' => end($variations)->id(),
    ]);
    // Push the request to the request stack so `current_route_match` works.
    $this->container->get('request_stack')->push($request);
    $variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');
    assert($variation_storage instanceof ProductVariationStorageInterface);
    $this->assertNotEquals($request->query->get('v'), $product->getDefaultVariation()->id());
    $context_variation = $variation_storage->loadFromContext($product);
    $this->assertEquals($request->query->get('v'), $context_variation->id());

    // Invalid variation ID returns default variation.
    $request = Request::create('');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->query->add([
      'v' => '1111111',
    ]);
    // Push the request to the request stack so `current_route_match` works.
    $this->container->get('request_stack')->push($request);
    $this->assertNotEquals($request->query->get('v'), $product->getDefaultVariation()->id());
    $context_variation = $variation_storage->loadFromContext($product);
    $this->assertEquals($product->getDefaultVariation()->id(), $context_variation->id());
  }

}
