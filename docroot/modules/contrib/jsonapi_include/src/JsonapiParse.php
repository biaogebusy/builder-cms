<?php

namespace Drupal\jsonapi_include;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * The class for parse Jsonapi content.
 *
 * @package Drupal\jsonapi_include
 */
class JsonapiParse implements JsonapiParseInterface {

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The variable store included data.
   *
   * @var array
   */
  protected $included;

  /**
   * Allowed includes.
   *
   * @var array
   */
  protected $allowed;

  /**
   * Constructs the JSON:API parse service.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * Standard create function.
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function parse($response) {
    // @todo Remove in the release 2.0 with removed the deprecated strings input.
    if (!$response instanceof Response) {
      @trigger_error('Parsing strings is deprecated in jsonapi_include:8.x-1.7 and is removed from jsonapi_include:8.x-2.0. Pass the full Response object instead. See https://www.drupal.org/project/jsonapi_include/issues/3374410', E_USER_DEPRECATED);
      $content = $this->parseJsonContent($response);
      return Json::encode($content);
    }
    $this->parseJsonContent($response);
    return $response;
  }

  /**
   * Check array is assoc.
   *
   * @param array $arr
   *   The array.
   *
   * @return bool
   *   Check result.
   */
  protected function isAssoc(array $arr) {
    if ([] === $arr) {
      return FALSE;
    }
    return array_keys($arr) !== range(0, count($arr) - 1);
  }

  /**
   * Group include.
   *
   * @param object|array $object
   *   The input data.
   *
   * @return array
   *   The includes data.
   */
  protected function groupIncludes($object) {
    $result = [];
    $included = !empty($object['included']) ? $object['included'] : [];
    array_walk($included, function ($resource, $index) use (&$result) {
      $result[$resource['type']][$resource['id']] = $resource;
    });
    return $result;
  }

  /**
   * Resolve attributes.
   *
   * @param array|mixed $item
   *   The input item.
   *
   * @return array
   *   The resolve output.
   */
  protected function resolveAttributes($item) {
    $resource = $item;
    if (!empty($resource['attributes'])) {
      foreach ($resource['attributes'] as $name => $value) {
        $resource[$name] = $value;
      }
      unset($resource['attributes']);
    }
    return $resource;
  }

  /**
   * Flatten included.
   *
   * @param array|mixed $resource
   *   The resource.
   * @param string $key
   *   The key.
   *
   * @return array
   *   The result.
   */
  protected function flattenIncluded($resource, $key) {
    if (isset($this->included[$resource['type']][$resource['id']])) {
      $object = $this->resolveAttributes($this->included[$resource['type']][$resource['id']]);
      if (isset($resource['meta'])) {
        $object['meta'] = $resource['meta'];
      }
    }
    else {
      $object = $resource;
    }
    $result = $this->resolveRelationships($object, $key);
    return $result;
  }

  /**
   * Check resource is include.
   *
   * @param array|mixed $resource
   *   The resource to verify.
   * @param string $key
   *   Relationship key.
   *
   * @return bool
   *   Check result.
   */
  protected function isIncluded($resource, $key) {
    if (!in_array($key, $this->allowed) && count(preg_grep('/^' . preg_quote($key) . '\..*/', $this->allowed)) === 0) {
      return FALSE;
    }

    return isset($resource['type']) && isset($this->included[$resource['type']]);
  }

  /**
   * Resolve data.
   *
   * @param array|mixed $data
   *   The data for resolve.
   * @param string $key
   *   Relationship key.
   *
   * @return array
   *   Result.
   */
  protected function resolveData($data, $key) {
    if ($this->isIncluded($data, $key)) {
      return $this->flattenIncluded($data, $key);
    }
    else {
      return $data;
    }
  }

  /**
   * Resolve data.
   *
   * @param array|mixed $links
   *   The data for resolve.
   * @param string $key
   *   Relationship key.
   *
   * @return array
   *   Result.
   */
  protected function resolveRelationshipData($links, $key) {
    if (empty($links['data'])) {
      return $links;
    }
    $output = [];
    if (!$this->isAssoc($links['data'])) {
      foreach ($links['data'] as $item) {
        $output[] = $this->resolveData($item, $key);
      }
    }
    else {
      $output = $this->resolveData($links['data'], $key);
    }
    return $output;
  }

  /**
   * Resolve relationships.
   *
   * @param array|mixed $resource
   *   The data for resolve.
   * @param string $parent_key
   *   The parent key for relationship.
   *
   * @return array
   *   Result.
   */
  protected function resolveRelationships($resource, $parent_key) {
    if (empty($resource['relationships'])) {
      return $resource;
    }

    foreach ($resource['relationships'] as $key => $value) {
      $resource[$key] = $this->resolveRelationshipData($value, trim("$parent_key.$key", '.'));
    }
    unset($resource['relationships']);
    return $resource;
  }

  /**
   * Parse Resource.
   *
   * @param array|mixed $item
   *   The data for resolve.
   *
   * @return array
   *   Result.
   */
  protected function parseResource($item) {
    $attributes = $this->resolveAttributes($item);
    return $this->resolveRelationships($attributes, '');
  }

  /**
   * Integrates includes into the content of a Response.
   *
   * @param \Symfony\Component\HttpFoundation\Response|string|array $response
   *   A Response object or string/array with a response content.
   *
   * @return \Symfony\Component\HttpFoundation\Response|array
   *   Returns
   *   Returns an array with the response content, if the input is string or
   *   array, or void if input is a Response.
   */
  protected function parseJsonContent($response) {
    if ($response instanceof Response) {
      $content = $response->getContent();
      if (is_string($content)) {
        $content = Json::decode($content);
      }
    }
    // @todo Remove in the release 2.0 with removed the deprecated string input.
    elseif (is_array($response)) {
      $content = $response;
    }
    elseif (is_string($response)) {
      $content = Json::decode($response);
    }
    if (NestedArray::getValue($content, ['jsonapi', 'parsed'])) {
      return $response;
    }
    if (isset($content['errors']) || empty($content['data'])) {
      return $response;
    }
    $this->included = $this->groupIncludes($content);
    $include_parameter = $this->requestStack->getCurrentRequest()->query->get('include');
    $this->allowed = array_map('trim', explode(',', $include_parameter ?? ''));
    $data = [];
    if (!$this->isAssoc($content['data'])) {
      foreach ($content['data'] as $item) {
        $data[] = $this->parseResource($item);
      }
    }
    else {
      $data = $this->parseResource($content['data']);
    }
    if (isset($content['included'])) {
      unset($content['included']);
    }
    $content['jsonapi']['parsed'] = TRUE;
    $content['data'] = $data;
    if ($response instanceof Response) {
      $response->setContent(Json::encode($content));
      return $response;
    }
    // @todo Remove in the release 2.0 with removed the deprecated strings input.
    else {
      return $content;
    }
  }

}
