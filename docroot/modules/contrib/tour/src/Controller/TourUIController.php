<?php

namespace Drupal\tour\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\tour\TourInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tour UI Controller.
 */
class TourUIController extends ControllerBase {

  /**
   * Constructs a new TourUIController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(protected Connection $database) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Returns list of modules included as part of the URL string.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request Service.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Return list in JSON format.
   */
  public function getModules(Request $request): JsonResponse {
    $matches = [];

    $part = $request->query->get('q');
    if ($part) {
      $matches[] = $part;

      // Escape user input.
      $part = preg_quote($part);

      $modules = $this->moduleHandler()->getModuleList();
      foreach ($modules as $module => $data) {
        if (preg_match("/$part/", $module)) {
          $matches[] = $module;
        }
      }
    }

    return new JsonResponse($matches);
  }

  /**
   * Build list of route and path pattern.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request Service.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Return list in JSON format.
   */
  public function getRoutes(Request $request): JsonResponse {
    $matches = [];

    $part = $request->query->get('q');
    if ($part && strlen($part) > 3) {
      $list = [];
      $result = $this->database->query('SELECT * from {router}');
      foreach ($result as $row) {
        $list[$row->name] = $row->name . ' (' . $row->pattern_outline . ')';
      }
      asort($list);

      $matches[] = $part;
      $part = preg_quote($part, '/');
      foreach ($list as $data) {
        if (preg_match("/$part/", $data)) {
          $matches[] = $data;
        }
      }
    }

    return new JsonResponse($matches);
  }

  /**
   * Calls a method on a view and reloads the listing page.
   *
   * @param \Drupal\tour\TourInterface $tour
   *   The view being acted upon.
   * @param string $op
   *   The operation to perform, e.g., 'enable' or 'disable'.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Either returns a rebuilt listing page as an AJAX response, or redirects
   *   back to the listing page.
   */
  public function ajaxOperation(TourInterface $tour, string $op, Request $request): AjaxResponse|RedirectResponse {
    // Perform the operation.
    $tour->setStatus($op === 'enable')->save();

    // If the request is via AJAX, return the rendered list as JSON.
    if ($request->request->get('js')) {
      $list = $this->entityTypeManager()->getListBuilder('tour')->render();
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('#tours-list', $list));
      return $response;
    }

    // Otherwise, redirect back to the page.
    return $this->redirect('entity.tour.collection');
  }

}
