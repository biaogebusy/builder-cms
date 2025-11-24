<?php

namespace Drupal\jsonapi_defaults\Controller;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\jsonapi\Controller\EntityResource as JsonApiEntityResource;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\Query\Sort;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi_defaults\JsonapiDefaultsInterface;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Symfony\Component\HttpFoundation\Request;

/**
 * Overrides jsonapi module EntityResource controller.
 */
class EntityResource extends JsonApiEntityResource {

  /**
   * The jsonapi defaults service.
   *
   * @var \Drupal\jsonapi_defaults\JsonapiDefaultsInterface
   */
  protected JsonapiDefaultsInterface $jsonapiDefaults;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Sets the jsonapi defaults service.
   *
   * @param \Drupal\jsonapi_defaults\JsonapiDefaultsInterface $jsonapiDefaults
   *   The JsonapiDefaults object.
   *
   * @return $this
   */
  public function setJsonapiDefaults(JsonapiDefaultsInterface $jsonapiDefaults): static {
    $this->jsonapiDefaults = $jsonapiDefaults;
    return $this;
  }

  /**
   * Sets the logger.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   *
   * @return $this
   */
  public function setLogger(LoggerChannelFactoryInterface $loggerFactory): static {
    $this->logger = $loggerFactory->get('jsonapi_defaults');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function getJsonApiParams(Request $request, ResourceType $resource_type) {
    try {
      $resourceConfig = $this->jsonapiDefaults->getResourceConfigFromRequest($request);
    }
    catch (\LengthException $e) {
      $this->logger->error($e);
      $resourceConfig = NULL;
    }

    if (!$resourceConfig instanceof JsonapiResourceConfig) {
      return parent::getJsonApiParams($request, $resource_type);
    }

    $default_filter_input = $resourceConfig->getThirdPartySetting(
      'jsonapi_defaults',
      'default_filter',
      []
    );

    $default_sorting_input = $resourceConfig->getThirdPartySetting(
      'jsonapi_defaults',
      'default_sorting',
      []
    );

    $default_filter = [];
    $default_sorting = [];

    foreach ($default_filter_input as $key => $value) {
      if (substr($key, 0, 6) === 'filter') {
        $key = str_replace('filter:', '', $key);
        // @todo Replace this with use of the NestedArray utility.
        $this->setFilterValue($default_filter, $key, $value);
      }
    }

    foreach ($default_sorting_input as $key => $value) {
      if (substr($key, 0, 4) === 'sort') {
        $key = str_replace('sort:', '', $key);
        // @todo Replace this with use of the NestedArray utility.
        $this->setFilterValue($default_sorting, $key, $value);
      }
    }

    $filters = array_merge(
      $default_filter,
      $request->query->all('filter')
    );

    $sort = [];
    if ($request->query->has('sort')) {
      $sort = Sort::createFromQueryParameter($request->query->all()['sort'])->fields();
    }
    $sorting = array_merge($default_sorting, $sort);

    if (!empty($filters)) {
      $request->query->set('filter', $filters);
    }

    if (!empty($sorting)) {
      $request->query->set('sort', $sorting);
    }

    // Implements overridden page limits.
    $params = parent::getJsonApiParams($request, $resource_type);
    $this->setPageLimit($request, $resourceConfig, $params);
    return $params;
  }

  /**
   * {@inheritdoc}
   */
  public function getIncludes(Request $request, $data) {
    if (!$request->get('_on_relationship')) {
      try {
        $resourceConfig = $this->jsonapiDefaults->getResourceConfigFromRequest($request);
      }
      catch (\LengthException $e) {
        $this->logger->error($e);
        $resourceConfig = NULL;
      }

      if (!$resourceConfig) {
        return parent::getIncludes($request, $data);
      }

      $defaultIncludes = $resourceConfig->getThirdPartySetting(
        'jsonapi_defaults',
        'default_include',
        []
      );

      if (!empty($defaultIncludes) && $request->query->get('include') === NULL) {
        $includes = array_unique(array_filter(array_merge($defaultIncludes)));
        $request->query->set('include', implode(',', $includes));
      }
    }

    return parent::getIncludes($request, $data);
  }

  /**
   * Set filter into nested array.
   *
   * @param array $arr
   *   The default filter.
   * @param string $path
   *   The filter path.
   * @param mixed $value
   *   The filter value.
   */
  private function setFilterValue(array &$arr, $path, $value) {
    $keys = explode('#', $path);

    foreach ($keys as $key) {
      $arr = &$arr[$key];
    }

    $arr = $value;
  }

  /**
   * Get amount of items displayed per page considering the request query.
   *
   * Fall back to the JSON:API standard value of 50 if not customized.
   *
   * @param array $page_params
   *   The values of the page query parameter of the request.
   * @param \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig $resource_config
   *   This resource's config entity.
   *
   * @return int
   *   Max number of items.
   */
  protected function determinePageLimit(array $page_params, JsonapiResourceConfig $resource_config) {
    $query_limit = !empty($page_params['limit']) ? (int) $page_params['limit'] : OffsetPage::SIZE_MAX;
    $page_limit = $resource_config->getThirdPartySetting(
      'jsonapi_defaults',
      'page_limit'
    ) ?: OffsetPage::SIZE_MAX;
    return min($query_limit, (int) $page_limit);
  }

  /**
   * Sets a jsonapi parameter for the page limit if applicable.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig $resource_config
   *   The resource config entity to check for an override of the page limit.
   * @param array $params
   *   The parameters passed to jsonapi, passed by reference.
   */
  protected function setPageLimit(Request $request, JsonapiResourceConfig $resource_config, array &$params) {
    if ($request->query->has('page')) {
      $page_params = $request->query->all('page');
      $offset = array_key_exists(OffsetPage::OFFSET_KEY, $page_params) ? (int) $page_params[OffsetPage::OFFSET_KEY] : OffsetPage::DEFAULT_OFFSET;
      $params[OffsetPage::KEY_NAME] = new OffsetPage(
        $offset,
        $this->determinePageLimit($page_params, $resource_config)
      );
    }
  }

}
