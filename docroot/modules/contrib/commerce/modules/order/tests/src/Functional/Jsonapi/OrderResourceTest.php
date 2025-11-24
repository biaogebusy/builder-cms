<?php

namespace Drupal\Tests\commerce_order\Functional\Jsonapi;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\Comparator\NumberComparator;
use Drupal\commerce_price\Comparator\PriceComparator;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\jsonapi\CacheableResourceResponse;
use Drupal\jsonapi\JsonApiSpec;
use Drupal\Tests\jsonapi\Functional\ResourceTestBase;
use Drupal\Tests\jsonapi\Traits\CommonCollectionFilterAccessTestPatternsTrait;
use SebastianBergmann\Comparator\Factory as PhpUnitComparatorFactory;

/**
 * JSON:API resource test for orders.
 *
 * @group commerce
 */
class OrderResourceTest extends ResourceTestBase {

  use CommonCollectionFilterAccessTestPatternsTrait;
  use StoreCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [
    'changed' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'commerce',
    'commerce_store',
    'commerce_price',
    'commerce_product',
    'commerce_order',
    'serialization',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'commerce_order';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'commerce_order--default';

  /**
   * The default store for test.
   */
  protected StoreInterface $store;

  /**
   * The test entity.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $factory = PhpUnitComparatorFactory::getInstance();
    $factory->register(new NumberComparator());
    $factory->register(new PriceComparator());
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $this->store = $this->createStore();

    $product = Product::create([
      'type' => 'default',
      'title' => $this->randomMachineName(),
      'stores' => [$this->store],
    ]);
    $product->save();

    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => '2N2NUM',
      'product_id' => $product->id(),
      'price' => new Price('4.50', 'USD'),
    ]);
    $variation->save();

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => '1',
      'unit_price' => $variation->getPrice(),
      'purchased_entity' => $variation->id(),
    ]);

    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'state' => 'draft',
      'uid' => $this->account->id(),
    ]);
    $order->addAdjustment(new Adjustment([
      'type' => 'custom',
      'label' => 'Custom adjustment for order',
      'amount' => new Price('5.00', 'USD'),
      'source_id' => $this->randomMachineName(),
    ]));
    $order->addItem($order_item);
    $order->save();
    return $order;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $base_url = Url::fromUri('base:/jsonapi/commerce_order/default/' . $this->entity->uuid())
      ->setAbsolute();
    $customer = $this->entity->getCustomer();

    // Generate created and changed times.
    $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $created = DrupalDateTime::createFromTimestamp($this->entity->getCreatedTime())
      ->setTimezone($timezone)->format(\DateTime::RFC3339);
    $changed = DrupalDateTime::createFromTimestamp($this->entity->getChangedTime())
      ->setTimezone($timezone)->format(\DateTime::RFC3339);

    // Generate order items data.
    $order_items_data = [];
    foreach ($this->entity->getItems() as $item) {
      $order_items_data[] = [
        'type' => 'commerce_order_item--default',
        'id' => $item->uuid(),
        'meta' => [
          'drupal_internal__target_id' => $item->id(),
        ],
      ];
    }

    $options = ['currency_display' => 'symbol'];
    $currency_formatter = $this->container->get('commerce_price.currency_formatter');

    // Generate adjustments data.
    $adjustments = [];
    foreach ($this->entity->getAdjustments() as $adjustment) {
      $amount_price = $adjustment->getAmount();
      $amount = $amount_price->toArray();
      $amount['formatted'] = $currency_formatter->format($amount_price->getNumber(), $amount_price->getCurrencyCode(), $options);
      $adjustments[] = [
        'amount' => $amount,
        'included' => $adjustment->isIncluded(),
        'label' => $adjustment->getLabel(),
        'locked' => $adjustment->isLocked(),
        'percentage' => $adjustment->getPercentage(),
        'source_id' => $adjustment->getSourceId(),
        'type' => $adjustment->getType(),
      ];
    }

    // Generate prices data.
    $total_price = $this->entity->getTotalPrice();
    $total_price_data = [
      'number' => Calculator::trim($total_price->getNumber()),
      'currency_code' => $total_price->getCurrencyCode(),
      'formatted' => $currency_formatter->format($total_price->getNumber(), $total_price->getCurrencyCode(), $options),
    ];

    $balance = $this->entity->getBalance();
    $balance_data = [
      'number' => Calculator::trim($balance->getNumber()),
      'currency_code' => $balance->getCurrencyCode(),
      'formatted' => $currency_formatter->format($balance->getNumber(), $balance->getCurrencyCode(), $options),
    ];

    return [
      'jsonapi' => [
        'version' => JsonApiSpec::SUPPORTED_SPECIFICATION_VERSION,
        'meta' => [
          'links' => [
            'self' => ['href' => JsonApiSpec::SUPPORTED_SPECIFICATION_PERMALINK],
          ],
        ],
      ],
      'data' => [
        'type' => 'commerce_order--default',
        'id' => $this->entity->uuid(),
        'links' => [
          'self' => ['href' => $base_url->toString()],
        ],
        'attributes' => [
          'drupal_internal__order_id' => $this->entity->id(),
          'order_number' => NULL,
          'version' => $this->entity->getVersion(),
          'mail' => $customer->getEmail(),
          'ip_address' => '127.0.0.1',
          'adjustments' => $adjustments,
          'total_price' => $total_price_data,
          'total_paid' => NULL,
          'balance' => $balance_data,
          'state' => 'draft',
          'data' => [
            'paid_event_dispatched' => FALSE,
          ],
          'locked' => FALSE,
          'created' => $created,
          'changed' => $changed,
          'placed' => NULL,
          'completed' => NULL,
          'customer_comments' => $this->entity->getCustomerComments(),
        ],
        'relationships' => [
          'commerce_order_type' => [
            'data' => [
              'type' => 'commerce_order_type--commerce_order_type',
              'id' => OrderType::load('default')->uuid(),
              'meta' => [
                'drupal_internal__target_id' => 'default',
              ],
            ],
            'links' => [
              'related' => ['href' => $base_url->toString() . '/commerce_order_type'],
              'self' => ['href' => $base_url->toString() . '/relationships/commerce_order_type'],
            ],
          ],
          'store_id' => [
            'data' => [
              'type' => 'commerce_store--online',
              'id' => $this->store->uuid(),
              'meta' => [
                'drupal_internal__target_id' => (int) $this->store->id(),
              ],
            ],
            'links' => [
              'related' => ['href' => $base_url->toString() . '/store_id'],
              'self' => ['href' => $base_url->toString() . '/relationships/store_id'],
            ],
          ],
          'uid' => [
            'data' => [
              'type' => 'user--user',
              'id' => $customer->uuid(),
              'meta' => [
                'drupal_internal__target_id' => $customer->id(),
              ],
            ],
            'links' => [
              'related' => ['href' => $base_url->toString() . '/uid'],
              'self' => ['href' => $base_url->toString() . '/relationships/uid'],
            ],
          ],
          'billing_profile' => [
            'data' => NULL,
            'links' => [
              'related' => ['href' => $base_url->toString() . '/billing_profile'],
              'self' => ['href' => $base_url->toString() . '/relationships/billing_profile'],
            ],
          ],
          'order_items' => [
            'data' => !empty($order_items_data) ? $order_items_data : NULL,
            'links' => [
              'related' => ['href' => $base_url->toString() . '/order_items'],
              'self' => ['href' => $base_url->toString() . '/relationships/order_items'],
            ],
          ],
        ],
      ],
      'links' => [
        'self' => ['href' => $base_url->toString()],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument() {
    return [
      'data' => [
        'type' => 'commerce_order--default',
        'attributes' => [
          'order_number' => '#1',
        ],
        'relationships' => [
          'store_id' => [
            'data' => [
              'type' => 'commerce_store--online',
              'id' => $this->store->uuid(),
              'meta' => [
                'drupal_internal__target_id' => (int) $this->store->id(),
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['view default commerce_order']);
        break;

      case 'DELETE':
        $this->grantPermissionsToTestedRole(['delete default commerce_order']);
        break;

      case 'POST':
        $this->grantPermissionsToTestedRole([
          'view commerce_store',
          'create default commerce_order',
        ]);
        break;

      case 'PATCH':
        $this->grantPermissionsToTestedRole(['update default commerce_order']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    return match ($method) {
      'GET' => "The 'view own commerce_order' permission is required.",
      'DELETE' => "The following permissions are required: 'delete commerce_order' OR 'delete default commerce_order'.",
      'POST' => "The following permissions are required: 'administer commerce_order' OR 'create commerce_order' OR 'create default commerce_order'.",
      'PATCH' => "The following permissions are required: 'update commerce_order' OR 'update default commerce_order'.",
      default => parent::getExpectedUnauthorizedAccessMessage($method),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessCacheability() {
    $cacheability = parent::getExpectedUnauthorizedAccessCacheability();
    $cacheability->addCacheableDependency($this->entity);
    $contexts = array_map(function ($context) {
      if ($context === 'user.permissions') {
        $context = 'user';
      }
      return $context;
    }, $cacheability->getCacheContexts());
    $cacheability->setCacheContexts($contexts);
    return $cacheability;
  }

  /**
   * {@inheritdoc}
   */
  protected static function getExpectedCollectionCacheability(AccountInterface $account, array $collection, ?array $sparse_fieldset = NULL, $filtered = FALSE) {
    $cacheability = parent::getExpectedCollectionCacheability($account, $collection, $sparse_fieldset, $filtered);

    // Modify cache tags for collection request.
    $tags = $cacheability->getCacheTags();
    foreach ($collection as $entity) {
      if (!$entity->access('view', $account, TRUE)->isAllowed()) {
        $tag = "{$entity->getEntityTypeId()}:{$entity->id()}";
        $key = array_search($tag, $tags, TRUE);
        unset($tags[$key]);
      }
    }
    $cacheability->setCacheTags($tags);

    // Modify cache contexts for collection request.
    $contexts = array_map(function ($context) {
      if ($context === 'user') {
        $context = 'user.permissions';
      }
      return $context;
    }, $cacheability->getCacheContexts());
    $contexts = array_unique($contexts);
    $cacheability->setCacheContexts($contexts);

    return $cacheability;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCollectionResponse(array $collection, $self_link, array $request_options, ?array $included_paths = NULL, $filtered = FALSE) {
    $response = parent::getExpectedCollectionResponse($collection, $self_link, $request_options, $included_paths, $filtered);
    $document = $response->getResponseData();

    // Actual response does not have omitted message except when "included"
    // presented in query.
    if (!isset($document['included'])) {
      unset($document['meta']['omitted']);
      if (empty($document['meta'])) {
        unset($document['meta']);
      }
    }
    $cacheability = $response->getCacheableMetadata();
    return (new CacheableResourceResponse($document, 200))->addCacheableDependency($cacheability);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRelationshipFieldNames(?EntityInterface $entity = NULL) {
    $entity = $entity ?: $this->entity;

    // Remove non-existing fields.
    $field_names = array_map(function ($field_name) {
      $replacement = 'drupal_internal__';
      if (str_starts_with($field_name, $replacement)) {
        $field_name = str_replace($replacement, '', $field_name);
      }
      return $field_name;
    }, parent::getRelationshipFieldNames($entity));

    return array_filter($field_names, function ($field_name) use ($entity) {
      return $entity->hasField($field_name);
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function entityLoadUnchanged($id) {
    $entity = parent::entityLoadUnchanged($id);
    if ($entity instanceof OrderInterface) {
      $entity->recalculateTotalPrice();
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function normalize(EntityInterface $entity, Url $url) {
    $normalization = parent::normalize($entity, $url);

    foreach (['total_price', 'balance'] as $field_name) {
      if (!empty($normalization['data']['attributes'][$field_name])) {
        $number = $normalization['data']['attributes'][$field_name]['number'];
        $trimmed_price = Calculator::trim($number);
        if ($trimmed_price !== $number) {
          $normalization['data']['attributes'][$field_name]['number'] = str_pad($trimmed_price, strlen($trimmed_price) + 1, '0', \STR_PAD_RIGHT);
        }
      }
    }

    return $normalization;
  }

}
