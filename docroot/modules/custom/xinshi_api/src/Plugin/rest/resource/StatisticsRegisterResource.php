<?php


namespace Drupal\xinshi_api\Plugin\rest\resource;


use Drupal\Core\Render\RenderContext;
use Drupal\rest\ResourceResponse;
use Drupal\xinshi_api\CommonUtil;
use Symfony\Component\HttpFoundation\Request;

/**
 * Creates a resource for statistics register.
 *
 * @RestResource(
 *   id = "xinshi_api_statistics_register_rest",
 *   label = @Translation("Statistics Register"),
 *   uri_paths = {
 *     "canonical" = "/api/v3/statistics/user/register"
 *   }
 * )
 */
class StatisticsRegisterResource extends XinshibResourceBase {

  /**
   * @param Request $request
   * @return ResourceResponse
   */
  public function get(Request $request) {
    $context = new RenderContext();
    $data = \Drupal::service('renderer')->executeInRenderContext($context, function () use ($request) {
      // triggers the code that we don't don't control that in turn triggers early rendering.
      return $this->getRegister($request);
    });
    return $this->getResponse($data);
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
    $role = \Drupal::request()->get('role') ? explode(' ', $request->get('role')) : [];
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


}
