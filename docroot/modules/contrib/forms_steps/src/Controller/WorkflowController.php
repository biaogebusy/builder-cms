<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\forms_steps\Repository\WorkflowRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Main Workflow entities controller.
 *
 * @package Drupal\forms_steps\Controller
 */
class WorkflowController extends ControllerBase {

  /**
   * The repository for our specialized queries.
   *
   * @var \Drupal\forms_steps\Repository\WorkflowRepository
   */
  protected WorkflowRepository $repository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): WorkflowController {
    $controller = new static($container->get('forms_steps.workflow.repository'));
    $controller->setStringTranslation($container->get('string_translation'));
    return $controller;
  }

  /**
   * Construct a new controller.
   *
   * @param \Drupal\forms_steps\Repository\WorkflowRepository $repository
   *   The repository service.
   */
  public function __construct(WorkflowRepository $repository) {
    $this->repository = $repository;
  }

  /**
   * Render a list of entries in the database.
   */
  public function entryList(): array {
    $content = [];

    $content['message'] = [
      '#markup' => $this->t('List of all workflow instances.'),
    ];

    $rows = [];
    $headers = [
      $this->t('Id'),
      $this->t('instance_id'),
      $this->t('Entity type'),
      $this->t('Bundle'),
      $this->t('Form mode'),
    ];

    $entries = $this->repository->load();
    foreach ($entries as $entry) {
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
    }
    $content['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No entries available.'),
    ];
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

}
