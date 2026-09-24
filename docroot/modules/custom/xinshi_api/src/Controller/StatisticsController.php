<?php

namespace Drupal\xinshi_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Render\RenderContext;
use Drupal\xinshi_api\CommonUtil;
use Symfony\Component\HttpFoundation\Request;

/**
 * Published node and registered user statistics.
 */
class StatisticsController extends XinshiApiControllerBase {

  /**
   * @param Request $request
   * @return CacheableJsonResponse
   */
  public function nodePublished(Request $request) {
    $context = new RenderContext();
    $data = \Drupal::service('renderer')->executeInRenderContext($context, function () use ($request) {
      // triggers the code that we don't don't control that in turn triggers early rendering.
      return $this->getNodePublished($request);
    });
    $this->addCacheTags(['node_list']);
    return $this->getResponse($data);
  }

  /**
   * @param Request $request
   * @return CacheableJsonResponse
   */
  public function userRegister(Request $request) {
    $context = new RenderContext();
    $data = \Drupal::service('renderer')->executeInRenderContext($context, function () use ($request) {
      // triggers the code that we don't don't control that in turn triggers early rendering.
      return $this->getRegister($request);
    });
    $this->addCacheTags(['user_list']);
    return $this->getResponse($data);
  }

  /**
   * 返回发文统计
   * @return array
   */
  private function getNodePublished(Request $request) {
    $rang = CommonUtil::getYearRange();
    $date = $rang['date'];
    $statistics = $this->statisticsNode($request, $rang['date'], $rang['end_date']);
    $chart[] = ['date', 'total'];
    $count = 0;
    for ($i = 0; $i < 12; $i++) {
      $month = date('Ym', strtotime(date('Y-m-d', $date) . "+{$i} month"));
      $row = [];
      $row[] = $this->t(date('M', strtotime($month . '01')));
      $num = $statistics[$month] ?? 0;
      $row[] = $num;
      $count += $num;
      $chart[] = $row;
    }

    return [
      'total' => $this->statisticsNodeTotal($request),
      'count' => $count,
      'chart' => $chart,
    ];
  }

  /**
   * 返回发文统计
   * @param $date
   * @param $end_date
   * @return array
   */
  private function statisticsNode(Request $request, $date, $end_date) {
    $db = \Drupal::database();
    $query = $db->select('node_field_data', 'node_field_data');
    $query->addExpression("FROM_UNIXTIME(node_field_data.created, '%Y%m')", 'month');
    $query->addExpression('count(node_field_data.nid)', 'total');
    $query->condition('node_field_data.status', 1);
    if (\Drupal::moduleHandler()->moduleExists('multiversion')) {
      $query->condition('node_field_data._deleted', 0);
    }
    $type = $request->get('type') ? explode(' ', $request->get('type')) : [];
    if ($type) {
      $query->condition('node_field_data.type', $type, 'in');
    }
    $query->condition('node_field_data.created', [$date, $end_date], 'between');
    $query->groupBy('month');
    $query->orderBy('month');
    $data = [];
    foreach ($query->execute()->fetchAll() as $row) {
      $data[$row->month] = (int) $row->total;
    }
    return $data;
  }

  /**
   * 返回发文统计
   * @param Request $request
   * @return int
   */
  private function statisticsNodeTotal(Request $request) {
    $db = \Drupal::database();
    $query = $db->select('node_field_data', 'node_field_data');
    $query->addExpression('count(node_field_data.nid)', 'total');
    $query->condition('node_field_data.status', 1);
    if (\Drupal::moduleHandler()->moduleExists('multiversion')) {
      $query->condition('node_field_data._deleted', 0);
    }
    $type = $request->get('type') ? explode(' ', $request->get('type')) : [];
    if ($type) {
      $query->condition('node_field_data.type', $type, 'in');
    }
    return (int) $query->execute()->fetchField();
  }

  /**
   * 返回用户统计
   * @return array
   */
  private function getRegister(Request $request) {
    $rang = CommonUtil::getYearRange();
    $date = $rang['date'];
    $statistics = $this->statisticsUserRegister($request, $rang['date'], $rang['end_date']);
    $chart[] = ['date', 'total'];
    $count = 0;
    for ($i = 0; $i < 12; $i++) {
      $month = date('Ym', strtotime(date('Y-m-d', $date) . "+{$i} month"));
      $row = [];
      $row[] = $this->t(date('M', strtotime($month . '01')));
      $num = $statistics[$month] ?? 0;
      $row[] = $num;
      $count += $num;
      $chart[] = $row;
    }
    return [
      'total' => $this->statisticsUserRegisteTotal($request),
      'count' => $count,
      'chart' => $chart,
    ];
  }

  /**
   * @param Request $request
   * @param $date
   * @param $end_date
   * @return array
   */
  private function statisticsUserRegister(Request $request, $date, $end_date) {
    $db = \Drupal::database();
    $query = $db->select('users_field_data', 'users_field_data');
    $query->addExpression("FROM_UNIXTIME(users_field_data.created, '%Y%m')", 'month');
    $query->addExpression('count(users_field_data.uid)', 'total');
    $query->condition('users_field_data.status', 1);
    $role = $request->get('role') ? explode(' ', $request->get('role')) : [];
    $opposite = $request->get('opposite');
    if ($role || $opposite) {
      $sub_query = $db->select('user__roles', 'user__roles');
      $sub_query->addExpression('DISTINCT(user__roles.entity_id)', 'entity_id');
      if ($role) {
        $sub_query->condition('user__roles.roles_target_id', $role, 'in');
      }
      $sub_query->condition('user__roles.deleted', 0);
      $query->leftJoin($sub_query, 'user__roles', 'users_field_data.uid=user__roles.entity_id');
      if ($opposite) {
        $query->condition('user__roles.entity_id', '', 'is null');
      } else {
        $query->condition('user__roles.entity_id', '', 'is not null');
      }
    }

    $query->condition('users_field_data.created', [$date, $end_date], 'between');
    $query->groupBy('month');
    $query->orderBy('month');
    $data = [];
    foreach ($query->execute()->fetchAll() as $row) {
      $data[$row->month] = (int) $row->total;
    }
    return $data;
  }

  /**
   * 统计注册用户总数
   *
   * 根据请求参数统计符合条件的活跃用户总数，支持按角色筛选和反向筛选
   *
   * @param \Symfony\Component\HttpFoundation\Request $request 包含筛选条件的请求对象
   *   - role: 可选，以空格分隔的角色ID字符串，用于筛选具有指定角色的用户
   *   - opposite: 可选，布尔值，为true时筛选不包含指定角色的用户
   *
   * @return int 符合条件的用户总数
   */
  private function statisticsUserRegisteTotal(Request $request) {
    $db = \Drupal::database();
    $query = $db->select('users_field_data', 'users_field_data');
    $query->addExpression('count(users_field_data.uid)', 'total');
    $query->condition('users_field_data.status', 1);
    $role = $request->get('role') ? explode(' ', $request->get('role')) : [];
    $opposite = $request->get('opposite');
    if ($role || $opposite) {
      $sub_query = $db->select('user__roles', 'user__roles');
      $sub_query->addExpression('DISTINCT(user__roles.entity_id)', 'entity_id');
      if ($role) {
        $sub_query->condition('user__roles.roles_target_id', $role, 'in');
      }
      $sub_query->condition('user__roles.deleted', 0);
      $query->leftJoin($sub_query, 'user__roles', 'users_field_data.uid=user__roles.entity_id');
      if ($opposite) {
        $query->condition('user__roles.entity_id', '', 'is null');
      } else {
        $query->condition('user__roles.entity_id', '', 'is not null');
      }
    }
    return (int) $query->execute()->fetchField();
  }
}
