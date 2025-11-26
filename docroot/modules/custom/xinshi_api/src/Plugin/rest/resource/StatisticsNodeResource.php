<?php


namespace Drupal\xinshi_api\Plugin\rest\resource;


use Drupal\Core\Render\RenderContext;
use Drupal\rest\ResourceResponse;
use Drupal\xinshi_api\CommonUtil;
use Symfony\Component\HttpFoundation\Request;

/**
 * Creates a resource for statistics node.
 *
 * @RestResource(
 *   id = "xinshi_api_statistics_node_rest",
 *   label = @Translation("Statistics Node"),
 *   uri_paths = {
 *     "canonical" = "/api/v3/statistics/node/published"
 *   }
 * )
 */
class StatisticsNodeResource extends XinshibResourceBase {

  /**
   * @param Request $request
   * @return ResourceResponse
   */
  public function get(Request $request) {
    $context = new RenderContext();
    $data = \Drupal::service('renderer')->executeInRenderContext($context, function () use ($request) {
      // triggers the code that we don't don't control that in turn triggers early rendering.
      return $this->getNodePublished($request);
    });
    $this->addCacheTags(['node_list']);
    $this->setCacheMaxAge(0);
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
   * @param $date
   * @param $end_date
   * @return array
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
}
