<?php

namespace Drupal\xinshi_api;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\wechat\Entity\WechatUser;

class CommonUtil {

  /**
   * Remove url's base root.
   * @param $url
   * @return bool|string
   */
  public static function removeBaseUrl($url) {
    if (empty($url)) {
      return $url;
    }
    $base_root = $GLOBALS['base_root'];
    if (strpos($url, $base_root) === 0) {
      $url = substr($url, strlen($base_root));
    }
    return $url;
  }

  /**
   * Return image style url.
   * @param $fid
   * @param string $style
   * @param bool $remove_base_url
   * @return \Drupal\Core\GeneratedUrl|string
   */
  public static function getImageStyle($fid, $style = 'crop', $remove_base_url = TRUE) {
    if ($fid && $file = File::load($fid)) {
      $image_style = ImageStyle::load($style ? str_replace('-', '_', $style) : 'crop') ?? ImageStyle::load('crop');

      if ($remove_base_url) {
        return self::removeBaseUrl($image_style->buildUrl($file->getFileUri()));
      } else {
        return $image_style->buildUrl($file->getFileUri());
      }
    } else {
      return '';
    }
  }


  /**
   * Change list to tree.
   * @param $list
   * @param string $pk
   * @param string $pid
   * @param string $child
   * @param int $root
   * @return array
   */
  public static function listToTree($list, $pk = 'id', $pid = 'pid', $child = 'below', $root = 0) {

    $tree = [];
    if (is_array($list)) {
      $refer = [];
      foreach ($list as $key => $data) {
        $refer[$data[$pk]] = &$list[$key];
      }
      foreach ($list as $key => $data) {
        $parent_id = $data[$pid];
        if ($root == $parent_id) {
          $tree[] = &$list[$key];
        } else {
          if (isset($refer[$parent_id])) {
            $parent = &$refer[$parent_id];
            $parent[$child][] = &$list[$key];
          }
        }
      }
    }
    return $tree;
  }

  /**
   * Tree to list.
   * @param $tree
   * @param string $child
   * @param array $list
   * @return array|mixed
   */
  public static function treeToList($tree, $child = 'below', &$list = []) {
    if (is_array($tree)) {
      foreach ($tree as $key => $value) {
        $reffer = $value;
        if (isset($reffer[$child]) && is_array($reffer[$child])) {
          unset($reffer[$child]);
          $list[] = $reffer;
          self::treeToList($value[$child], $child, $list);
        } else {
          $list[] = $reffer;
        }
      }
    }
    return $list;
  }

  /**
   * Load tree by tid.
   * @param $tid
   * @param null $max_depth
   * @param null $custom_item
   * @return array
   */
  public static function termTree($tid, $max_depth = NULL, $custom_item = NULL) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadTree($tid, 0, $max_depth);
    $option = [];
    if ($custom_item) {
      $option[] = $custom_item;
    }
    foreach ($terms as $term) {
      $option[] = [
        'value' => $term->tid,
        'label' => $term->name,
        'weight' => $term->weight,
        'pid' => current($term->parents),
        'url' => Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $term->tid])->toString(),
      ];
    }
    return self::listToTree($option, 'value', 'pid', 'elements');
  }

  public static function getJsonAPIData($uri) {
    $result = [];
    try {
      if (!UrlHelper::isExternal($uri)) {
        $uri = $GLOBALS['base_url'] . $uri;
      }
      $body = \Drupal::httpClient()->get($uri)->getBody()->getContents();
      return json_decode($body, TRUE);
    } catch (\Exception $exception) {
      \Drupal::logger('xinshi')->error($exception->getMessage());
    }
    return $result;
  }

  /**
   * Return user profile
   * @param $uid
   * @return array
   */
  public static function accountProfile($uid, $roles = TRUE) {
    $res = [];
    /** @var User $entity */
    $entity = User::load($uid);
    if ($entity) {
      $res['name'] = $entity->getDisplayName();
      $res['uuid'] = $entity->uuid();
      $res['uid'] = $entity->id();
      if ($file = $entity->get('user_picture')->entity) {
        $res['avatar'] = CommonUtil::removeBaseUrl(file_create_url($file->getFileUri()));
      } elseif (\Drupal::moduleHandler()->moduleExists('wechat')) {
        /** @var WechatUser[] $entity */
        $wechat_user = \Drupal::entityTypeManager()->getStorage('wechat_user')->loadByProperties(['uid' => $uid]);
        if ($wechat_user) {
          $res['avatar'] = current($wechat_user)->get('headimgurl')->value;
        }
      }
      if ($roles && \Drupal::currentUser()->isAuthenticated()) {
        foreach (Role::loadMultiple($entity->getRoles()) as $key => $entity) {
          $res['roles'][$key] = $entity->label();
        }
      }
    }
    return $res;
  }

  /**
   * 返回时间期间
   * @return array
   */
  public static function getYearRange() {
    $year = \Drupal::request()->get('year');
    if (!(is_numeric($year) && strlen($year) == 4 && $year > 1000)) {
      $year = date('Y');
    }
    $date = strtotime("{$year}-01-01");
    $end_date = strtotime("{$year}-01-01" . ' +1 year');
    return [
      'date' => $date,
      'end_date' => $end_date,
    ];
  }

}
