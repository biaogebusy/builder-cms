<?php

namespace Drupal\xinshi_api\TwigExtension;

use Drupal\comment\Entity\Comment;
use Drupal\Core\Entity\EntityInterface;
use Drupal\file\Entity\File;
use Drupal\filter\Render\FilteredMarkup;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\xinshi_api\CommonUtil;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Class DefaultTwigFunction.
 */
class DefaultTwigExtension extends AbstractExtension {
  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'xinshi_api.twig.extension';
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction(
        'jsonSpecialChars',
        [$this, 'jsonSpecialChars']
      ),
      new TwigFunction(
        'getAccessControl',
        [$this, 'getAccessControl']
      ),
      new TwigFunction(
        'accountProfile',
        [$this, 'accountProfile']
      ),
      new TwigFunction(
        'entityComment',
        [$this, 'entityComment']
      ),
      new TwigFunction(
        'LoadTermData',
        [$this, 'LoadTermData']
      ),
      new TwigFunction(
        'fileInfoMini',
        [$this, 'fileInfo']
      ),
      new TwigFunction(
        'oneself',
        [$this, 'oneself']
      ),
      new TwigFunction(
        'loadCommentTree',
        [$this, 'loadCommentTree']
      ),
      new TwigFunction(
        'entityMate',
        [$this, 'entityMate']
      ),
      new TwigFunction(
        'stateTransitionAccess',
        [$this, 'stateTransitionAccess']
      ),
      new TwigFunction(
        'nodeMate',
        [$this, 'nodeMate']
      ),
    ];
  }

  /**
   * @param $str
   * @return mixed
   */
  public function jsonSpecialChars($str) {
    $str = str_replace(["\n"], "\\\\n", $str);
    $str = str_replace(["\r"], "", $str);
    $str = str_replace(["\""], "\\\"", $str);
    $str = str_replace(["\'"], "\\'", $str);
    return $str;
  }

  /**
   * Entity access control
   * @param $id
   * @return false|string
   */
  public function getAccessControl($id) {
    /** @var Node $entity */
    $entity = Node::load($id);
    $access = [];
    if ($entity && $entity->hasField('access_control') && $access_controls = $entity->get('access_control')->referencedEntities()) {
      foreach ($access_controls as $access_control) {
        $types = array_column($access_control->get('access_type')->getValue(), 'value');
        if (empty($types)) {
          continue;
        }
        if (in_array('roles', $types) && $roles = array_column($access_control->get('roles')->getValue(), 'target_id')) {
          $access['require_rule'] = $roles;
        }
        if (in_array('payment', $types) && $access_control->get('price')->value) {
          $access['pay']['money'] = $access_control->get('price')->value;
        }
      }
      if ($access) {
        $access['entityId'] = $id;
      }
    }
    return json_encode($access);
  }

  /**
   * Account profile.
   * @param $uid
   * @return array
   */
  public function accountProfile($uid) {
    return CommonUtil::accountProfile($uid);
  }

  public function entityComment($comment_type, $entity_id, $entity_type = 'node', $fields = ['content'], $json = TRUE, $limit = 0, $uid = 0) {
    $query = \Drupal::entityTypeManager()->getStorage('comment')
      ->getQuery()
      ->condition('comment_type', $comment_type)
      ->condition('status', 1)
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->sort('created', 'DESC');
    if ($limit) {
      $query->range(0, $limit);
    }
    if ($uid) {
      $query->condition('uid', $uid);
    }

    /** @var Comment[] $comments */
    $comments = Comment::loadMultiple($query->execute());
    $res = [];
    foreach ($comments as $comment) {
      $data = [
        'cid' => $comment->id(),
        'uuid' => $comment->uuid(),
        'created' => date('y-m-d H:i', $comment->getCreatedTime()),
        'user' => CommonUtil::accountProfile($comment->getOwnerId()),
      ];
      foreach ($fields as $field) {
        if ($comment->hasField($field)) {
          $data[$field] = $comment->get($field)->value;
        }
      }
      $res[] = $data;
    }
    return $json ? json_encode($res) : $res;
  }

  public function LoadTermData($tid, $depth = 0, $pid = 0) {
    /** @var Term[] $terms */
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadTree($tid, empty($pid) ? 0 : $pid, empty($depth) ? NULL : $depth, TRUE);
    $option = [];
    foreach ($terms as $term) {
      $option[$term->id()] = [
        'label' => $term->label(),
        'id' => $term->id(),
        'uuid' => $term->uuid(),
      ];
    }
    return $option;
  }

  public function fileInfo($fid) {
    /** @var File $file */
    $file = File::load($fid);
    if ($file) {
      return [
        'uuid' => $file->uuid(),
        'href' => $file->createFileUrl(),
        'name' => $file->label(),
      ];
    }
    return [];
  }

  /**
   * @param $uid
   * @return bool
   */
  public function oneself($uid) {
    return $uid == \Drupal::currentUser()->id() ? 1 : 0;
  }

  /**
   * Load comment tree.
   * @param $cid
   * @param $type
   * @param array $fields
   * @param int $level
   * @param int $depth
   * @return array
   */
  public function loadCommentTree($cid, $type, $fields = [], $level = 0, $depth = 0) {
    $storage = \Drupal::entityTypeManager()->getStorage('comment');
    $comments = $storage
      ->getQuery()
      ->condition('comment_type', $type)
      ->condition('pid', $cid)
      ->sort('created')
      ->execute();

    /** @var Comment[] $comments */
    $comments = $storage->loadMultiple($comments);
    $data = [];
    foreach ($comments as $entity) {
      $item = [
        "type" => "comment--" . $entity->bundle(),
        "uuid" => $entity->uuid(),
        "cid" => $entity->id(),
        "created" => date('Y-m-d H:i', $entity->getCreatedTime()),
        "user" => CommonUtil::accountProfile($entity->getOwnerId()),
        'reply' => [],
      ];
      foreach ($fields as $key => $name) {
        if ($entity->hasField($key)) {
          $item[$name] = $entity->get($key)->value;
        }
      }
      if (!($depth && $level > $depth)) {
        $item['reply'] = $this->loadCommentTree($entity->id(), $entity->bundle(), $fields, $level);
      }
      $data[] = $item;
    }
    $level += 1;
    return $data;
  }

  public function entityMate($entity_type_id, $entity_id) {
    $data = [];
    /** @var EntityInterface $entity */
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
    if (empty($entity)) {
      return $data;
    }
    switch ($entity_type_id) {
      case 'user':
        $title = "[user:display-name] | [site:name]";
        break;
      default:
        $title = "[{$entity_type_id}:title] | [site:name]";
        break;
    }
    $data['title'] = \Drupal::token()->replace($title, [$entity_type_id => $entity]);
    $data['meta'] = [];
    if ($entity->hasField('meta_tags') && $meta = $entity->get('meta_tags')->value) {
      $meta = unserialize($meta);
      foreach ($meta as $key => $value) {
        $content = \Drupal::token()->replace($value, [$entity_type_id => $entity]);
        if (empty($content)) {
          continue;
        }
        $data['meta'][] = [
          'name' => $key,
          'content' => htmlspecialchars_decode($content),
        ];
      }
    }
    return $data;
  }

  public function stateTransitionAccess($nid) {
    if (($entity = Node::load($nid)) && $entity->moderation_state && \Drupal::service('content_moderation.moderation_information')->isModeratedEntity($entity)) {
      $transition_validation = \Drupal::service('content_moderation.state_transition_validation');
      $valid_transition_targets = $transition_validation->getValidTransitions($entity, \Drupal::currentUser());
      return $valid_transition_targets ? TRUE : FALSE;
    }
    return FALSE;
  }

  /**
   * @param $nid
   * @param $vid
   * @return array
   */
  public function nodeMate($nid, $vid) {
    $data = [];
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $storage->loadRevision($vid);
    if (empty($node) || $node->id() != $nid) {
      $node = Node::load($nid);
    }
    if (empty($node)) {
      return $data;
    }
    if (\Drupal::moduleHandler()->moduleExists('content_translation') &&
      \Drupal::service('content_translation.manager')->isEnabled($node->getEntityTypeId(), $node->bundle()) &&
      $node->hasTranslation(\Drupal::languageManager()->getCurrentLanguage()->getId())) {
      $node = $node->getTranslation(\Drupal::languageManager()->getCurrentLanguage()->getId());
    }
    $data['title'] = \Drupal::token()->replace('[node:title] | [site:name]', ['node' => $node]);
    $data['meta'] = [];
    if ($node->hasField('meta_tags') && $meta = $node->get('meta_tags')->value) {
      $meta = unserialize($meta);
      foreach ($meta as $key => $value) {
        $content = \Drupal::token()->replace($value, ['node' => $node]);
        if (empty($content)) {
          continue;
        }
        if ($key == 'title') {
          $data['title'] = \Drupal::token()->replace($value, ['node' => $node]);
          continue;
        }
        $data['meta'][] = [
          'name' => $key,
          'content' => htmlspecialchars_decode($content),
        ];
      }
    }
    if (!in_array('description', array_column($data['meta'], 'name'))
      && $node->hasField('body')
      && $content = $node->body->summary_processed ?: $node->body->processed
    ) {
      /** @var $content FilteredMarkup */
      $data['meta'][] = [
        'name' => 'description',
        'content' => htmlspecialchars_decode(strip_tags($content->jsonSerialize())),
      ];
    }
    return $data;
  }
}
