<?php

namespace Drupal\xinshi_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\xinshi_api\JsonAPIUtil;
use Drupal\xinshi_api\PageJsonResponseBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/** Returns page JSON using the shared page response and cache policy. */
class LandingPageController extends XinshiApiControllerBase {

  public function __construct(private readonly PageJsonResponseBuilder $pageJson) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_api.page_json_response'));
  }

  /** Returns the resolved entity's JSON without changing the caller's contract. */
  public function landingPage(Request $request): CacheableJsonResponse {
    return $this->pageJson->build(JsonAPIUtil::getEntityByQuery(), $request);
  }

}
