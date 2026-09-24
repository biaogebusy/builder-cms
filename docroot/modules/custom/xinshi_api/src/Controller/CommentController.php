<?php

namespace Drupal\xinshi_api\Controller;

use Drupal\comment\Entity\Comment;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Render\RenderContext;
use Drupal\xinshi_api\CommonUtil;

/**
 * Comment threads of a node.
 */
class CommentController extends XinshiApiControllerBase {

  /**
   * @param $comment_type
   * @param $entity_uuid
   * @return CacheableJsonResponse
   */
  public function nodeComment($comment_type, $entity_uuid) {
    $context = new RenderContext();
    $data = \Drupal::service('renderer')->executeInRenderContext($context, function () use ($comment_type, $entity_uuid) {
      // triggers the code that we don't don't control that in turn triggers early rendering.
      return $this->getNodeComment($comment_type, $entity_uuid);
    });
    return $this->getResponse($data);
  }

  /**
   * @param $comment_type
   * @param $entity_uuid
   * @return array
   */
  protected function getNodeComment($comment_type, $entity_uuid) {
    $entity_type_manager = $this->entityTypeManager();
    $node = $entity_type_manager->getStorage('node')->loadByProperties(['uuid' => $entity_uuid]);
    if (empty($node)) {
      return [];
    }

    /** @var Comment[] $entities */
    $entities = $entity_type_manager->getStorage('comment')
      ->loadByProperties([
        'comment_type' => $comment_type,
        'entity_type' => 'node',
        'status' => 1,
        'entity_id' => current($node)->id()]);
    $data = [];
    $this->setCacheTags(['comment_list']);
    foreach ($entities as $entity) {
      $this->addCacheTags($entity->getCacheTags());
      $author = CommonUtil::accountProfile($entity->getOwnerId());
      $created = (int) $entity->getCreatedTime();
      $data[] = [
        'cid' => (int) $entity->id(),
        'pid' => (int) ($entity->get('pid')->target_id ?? '0'),
        'id' => $entity->uuid(),
        'uuid' => $entity->uuid(),
        'created' => $created,
        'time' => date('Y-m-d\TH:i:sO', $created),
        'content' => $entity->hasField('comment_body') ? $entity->get('comment_body')->value : '',
        'author' => [
          'img' => [
            "src" => $author['avatar'] ?? '',
            "style" => [
              "width" => "40px",
              "height" => "40px",
              "borderRadius" => "50%",
            ],
            "alt" => $author['name'],
          ],
          "align" => "center start",
          "id" => $author['uuid'],
          "title" => $author['name'],
          "subTitle" => date('Y-m-d H:i:s', $created),
        ],
        'child' => [],
        'level' => empty($entity->get('thread')->value) ? 1 : (int) count(explode('.', trim($entity->get('thread')->value, '/'))),
      ];
    }
    $data = CommonUtil::listToTree($data, 'cid', 'pid', 'child');
    array_multisort(array_column($data, 'created'), SORT_DESC, SORT_NUMERIC, $data);

    return $data;
  }
}
