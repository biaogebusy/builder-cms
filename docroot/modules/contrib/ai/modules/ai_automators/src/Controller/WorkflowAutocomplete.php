<?php

namespace Drupal\ai_automators\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_automators\Service\Automate;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a route controller for watches autocomplete form elements.
 */
class WorkflowAutocomplete extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected Automate $automate,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('ai_automator.automate'),
    );
  }

  /**
   * Handler for autocomplete workflows.
   */
  public function workflows(Request $request) {
    $results = [];
    $input = $request->query->get('q');

    // Get the typed string from the URL, if it exists and longer than 3 chars.
    if (!$input || strlen($input) < 3) {
      return new JsonResponse($results);
    }

    $input = Xss::filter($input);

    $allWorkflows = $this->automate->getWorkflows();
    $found = 0;
    foreach ($allWorkflows as $key => $workflow) {
      if (strpos(strtolower($workflow), strtolower($input)) !== FALSE) {
        $this->entityTypeManager()->getStorage('automator_chain_type')->load($key);

        $found++;
        $results[] = [
          'value' => 'automator_chain--' . $key,
          'label' => $workflow,
        ];
      }
      if ($found >= 10) {
        break;
      }
    }

    return new JsonResponse($results);
  }

}
