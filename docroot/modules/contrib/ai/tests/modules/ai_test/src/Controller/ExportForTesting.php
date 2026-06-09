<?php

namespace Drupal\ai_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_test\Entity\AIMockProviderResult;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

/**
 * Controller for exporting test data.
 */
class ExportForTesting extends ControllerBase {

  /**
   * Exports test data to a file.
   *
   * @return array
   *   Render array for the export page.
   */
  public function export(AIMockProviderResult $result) {
    $tags = [];
    foreach ($result->tags as $tag) {
      $tags[] = $tag->value;
    }
    // Build a YAML file with the result data.
    $data = [
      'request' => Yaml::parse($result->request->value),
      'response' => Yaml::parse($result->response->value),
      'wait' => (int) $result->sleep_time->value,
      'label' => $result->label->value,
      'tags' => $tags,
      'operation_type' => $result->operation_type->value,
    ];
    // Convert the label to a valid filename.
    $filename = preg_replace('/[^a-zA-Z0-9_]/', '_', $result->label->value);
    // Return a YAML file as a response.
    return new Response(
      Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
      Response::HTTP_OK,
      [
        'Content-Type' => 'application/x-yaml',
        'Content-Disposition' => 'attachment; filename="' . $filename . '.yml"',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0',
      ],
    );
  }

}
