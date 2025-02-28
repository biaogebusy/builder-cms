<?php

namespace Drupal\xinshi_api\Plugin\rest\resource;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\RenderContext;
use Drupal\rest\ResourceResponse;
use Drupal\xinshi_api\EntityJsonBase;
use Drupal\xinshi_api\JsonAPIUtil;
use Drupal\xinshi_api\NodeJson;
use Drupal\xinshi_api\TermJson;
use Drupal\xinshi_api\UserJson;
use Symfony\Component\HttpFoundation\Request;

/**
 * Creates a resource for landing page.
 *
 * @RestResource(
 *   id = "xinshi_api_landing_page_rest",
 *   label = @Translation("Landing Page"),
 *   uri_paths = {
 *     "canonical" = "/api/v3/landingPage"
 *   }
 * )
 */
class LandingPageResource extends XinshibResourceBase {

  /**
   * @param Request $request
   * @return ResourceResponse
   */
  public function get(Request $request) {
    $context = new RenderContext();
    $data = \Drupal::service('renderer')->executeInRenderContext($context, function () use ($request) {
      // triggers the code that we don't don't control that in turn triggers early rendering.
      return $this->getLandingPageJson($request);
    });
    return $this->getResponse($data);
  }

  /**
   * Return landing page json.
   * @return array
   */
  protected function getLandingPageJson(Request $request) {
    $data = [];
    $entity = JsonAPIUtil::getEntityByQuery();
    $access = $entity && $entity->access('view');
    if (empty($entity)) {
      $data = JsonAPIUtil::notFound();
    }
    if ($entity && !$access) {
      $data = JsonAPIUtil::accessDenied();
      $access = FALSE;
    }
    $mode = $request->get('mode') ?? 'json';
    if ($access) {
      try {
        $cache_enable = $this->config->get('cache_enable');
        $cache_config = $this->config->get($entity->getEntityTypeId() . '_cache') ?? [];
        $context = $cache_config[$entity->bundle()]['context'] ?? [];
        $cid = empty($cache_enable) ? '' : "xinshi:jsonapi:{$mode}" . $entity->getEntityTypeId() . ':' . $entity->id();
        if ($cache_enable && ($context['user'] ?? 0)) {
          $cid .= ':user:' . \Drupal::currentUser()->id();
        }

        if ($cid && $cache_data = \Drupal::cache('rest')->get($cid)) {
          $data = $cache_data->data;
          $this->setCacheTags($cache_data->tags);
          return $data;
        }
        switch ($entity->getEntityTypeId()) {
          case 'node':
            $json = new NodeJson($entity, $mode);
            break;
          case 'taxonomy_term':
            $json = new TermJson($entity, $mode);
            break;
          case 'user':
            $json = new UserJson($entity, $mode);
            break;
          default:
            $json = new EntityJsonBase($entity, $mode);
            break;
        }
        if ($json) {
          $data = $json->getContent();
          $this->setCacheTags($json->getCacheTags());
        }
        if ($cid) {
          \Drupal::cache('rest')->set($cid, $data, Cache::PERMANENT, $this->getCacheTags());
        }
        \Drupal::moduleHandler()->alter('xinshi_api_data', $data, $entity);
      } catch (\Exception $e) {
      }
    }
    return $data;
  }

}
