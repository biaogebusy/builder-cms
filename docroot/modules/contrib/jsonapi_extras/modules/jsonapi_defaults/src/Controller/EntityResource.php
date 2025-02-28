<?php

namespace Drupal\jsonapi_defaults\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\jsonapi\Controller\EntityResource as JsonApiEntityResourse;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\Query\Sort;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Routing\Routes;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Overrides jsonapi module EntityResource controller.
 */
class EntityResource extends JsonApiEntityResourse {

  /**
   * {@inheritdoc}
   */
  protected function getJsonApiParams(Request $request, ResourceType $resource_type) {
    // If this is a related resource, then we need to swap to the new resource
    // type.
    $related_field = $request->attributes->get('_on_relationship')
      ? NULL
      : $request->attributes->get('related');
    try {
      $resource_type = static::correctResourceTypeOnRelated($related_field, $resource_type);
    }
    catch (\LengthException $e) {
      watchdog_exception('jsonapi_defaults', $e);
      $resource_type = NULL;
    }

    if (!$resource_type instanceof ConfigurableResourceType) {
      return parent::getJsonApiParams($request, $resource_type);
    }
    $resource_config = $resource_type->getJsonapiResourceConfig();
    if (!$resource_config instanceof JsonapiResourceConfig) {
      return parent::getJsonApiParams($request, $resource_type);
    }
    $default_filter_input = $resource_config->getThirdPartySetting(
      'jsonapi_defaults',
      'default_filter',
      []
    );

    $default_sorting_input = $resource_config->getThirdPartySetting(
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
    $this->setPageLimit($request, $resource_config, $params);
    return $params;
  }

  /**
   * {@inheritdoc}
   */
  public function getIncludes(Request $request, $data) {
    if (
      ($resource_type = $request->get(Routes::RESOURCE_TYPE_KEY))
      && $resource_type instanceof ConfigurableResourceType
      && !$request->get('_on_relationship')
    ) {
      try {
        $resource_type = static::correctResourceTypeOnRelated($request->get('related'), $resource_type);
      }
      catch (\LengthException $e) {
        watchdog_exception('jsonapi_defaults', $e);
        return parent::getIncludes($request, $data);
      }
      if (!$resource_type instanceof ConfigurableResourceType) {
        return parent::getIncludes($request, $data);
      }
      $resource_config = $resource_type->getJsonapiResourceConfig();
      if (!$resource_config instanceof JsonapiResourceConfig) {
        return parent::getIncludes($request, $data);
      }
      $default_includes = $resource_config->getThirdPartySetting(
        'jsonapi_defaults',
        'default_include',
        []
      );
      if (!empty($default_includes) && $request->query->get('include') === NULL) {
        $includes = array_unique(array_filter(array_merge(
          $default_includes,
          explode(',', $request->query->get('include', ''))
        )));
        $request->query->set('include', implode(',', $includes));
      }
    }

    return parent::getIncludes($request, $data);
  }

  /**
   * Returns the correct resource type when operating on related fields.
   *
   * @param string $related_field
   *   The name of the related field to use. NULL if not using a related field.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type straight from the request.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType
   *   The resource type to use to load the includes.
   *
   * @throws \LengthException
   *   If there is more than one relatable resource type.
   */
  public static function correctResourceTypeOnRelated($related_field, ResourceType $resource_type) {
    if (!$related_field) {
      return $resource_type;
    }
    $relatable_resource_types = $resource_type
      ->getRelatableResourceTypesByField($related_field);
    if (count($relatable_resource_types) > 1) {
      $message = sprintf(
        '%s -- %s',
        'Impossible to apply defaults on a related resource with heterogeneous resource types.',
        Json::encode([
          'related_field' => $related_field,
          'host_resource_type' => $resource_type->getPath(),
          'target_resource_types' => array_map(function (ResourceType $resource_type) {
            return $resource_type->getPath();
          }, $relatable_resource_types),
        ])
      );
      throw new \LengthException($message);
    }
    return $relatable_resource_types[0] ?? NULL;
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
