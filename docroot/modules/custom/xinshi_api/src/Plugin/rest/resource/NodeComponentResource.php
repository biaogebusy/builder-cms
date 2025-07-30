<?php

namespace Drupal\xinshi_api\Plugin\rest\resource;

use Drupal\Core\Render\RenderContext;
use Drupal\node\Entity\Node;
use Drupal\xinshi_api\CommonUtil;
use Symfony\Component\HttpFoundation\Request;


/**
 * Creates a resource for Group component list.
 *
 * @RestResource(
 *   id = "xinshi_api_node_component_rest",
 *   label = @Translation("Component"),
 *   uri_paths = {
 *     "canonical" = "/api/v3/node/component"
 *   }
 * )
 */
class NodeComponentResource extends XinshibResourceBase {

  public function get(Request $request) {

    $this->addCacheTags(['taxonomy_term_list', 'node_list']);
    $context = new RenderContext();
    $data  = \Drupal::service('renderer')->executeInRenderContext($context, function () use ($request) {
      $tree = $this->getComponentTypeTree();
      $this->setComponentData($tree);
      return CommonUtil::listToTree($tree, 'id', 'pid', 'elements');
    });
    $response = $this->getResponse($data);
    $response->getCacheableMetadata()->setCacheMaxAge(0);
    return $response;
  }

  /**
   * Get the tree structure of component_type taxonomy.
   *
   * @return array
   *   An array containing the taxonomy tree with IDs and titles.
   */
  protected function getComponentTypeTree() {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('component_type', 0, NULL, TRUE);
    $flatTerms = [];
    /** @var Term */
    foreach ($terms as $term) {
      $this->addCacheTags($term->getCacheTags());
      $flatTerms[$term->id()] = [
        'id' => $term->id(),
        'uuid' => $term->uuid(),
        'label' => $term->label(),
        'pid' => $term->get('parent')->target_id ?? '0',
        'icon' => $term->get('icon')->value,
      ];
    }
    return $flatTerms;
  }

  protected function setComponentData(array &$types) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck()
      ->condition('type', 'component')
      ->condition('status', 1)
      ->condition('category', '', 'IS NOT NULL');
    foreach ($query->execute() as $id) {
      /** @var Node $entity */
      $entity = $this->entityTypeManager->getStorage('node')->load($id);
      $this->addCacheTags($entity->getCacheTags());
      foreach ($entity->get('category')->getValue() as $val) {
        $cid = $val['target_id'];
        if (!array_key_exists($cid, $types)) {
          continue;
        }
        if ($content = json_decode($entity->get('body')->value, 1)) {
          $types[$cid]['child'][] = [
            'id' => $entity->id(),
            'uuid' => $entity->uuid(),
            'label' => $entity->label(),
            'icon' => $entity->get('icon')->value,
            'mark' => $entity->get('mark')->value,
            'content' => $content
          ];
        }
      }
    }
  }
}
