<?php

namespace Drupal\views_bulk_operations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\views_bulk_operations\Form\ViewsBulkOperationsFormTrait;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessorInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Ajax\AjaxResponse;

/**
 * Defines VBO controller class.
 */
class ViewsBulkOperationsController extends ControllerBase implements ContainerInjectionInterface {

  use ViewsBulkOperationsFormTrait;

  /**
   * The tempstore service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Views Bulk Operations action processor.
   *
   * @var \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessorInterface
   */
  protected $actionProcessor;

  /**
   * The Renderer service object.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new controller object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   Private temporary storage factory.
   * @param \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessorInterface $actionProcessor
   *   Views Bulk Operations action processor.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Renderer service object.
   */
  public function __construct(
    PrivateTempStoreFactory $tempStoreFactory,
    ViewsBulkOperationsActionProcessorInterface $actionProcessor,
    RendererInterface $renderer
  ) {
    $this->tempStoreFactory = $tempStoreFactory;
    $this->actionProcessor = $actionProcessor;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('views_bulk_operations.processor'),
      $container->get('renderer')
    );
  }

  /**
   * The actual page callback.
   *
   * @param string $view_id
   *   The current view ID.
   * @param string $display_id
   *   The display ID of the current view.
   */
  public function execute($view_id, $display_id) {
    $view_data = $this->getTempstoreData($view_id, $display_id);
    if (empty($view_data)) {
      throw new NotFoundHttpException();
    }
    $this->deleteTempstoreData();

    $this->actionProcessor->executeProcessing($view_data);
    if ($view_data['batch']) {
      return batch_process($view_data['redirect_url']);
    }
    else {
      return new RedirectResponse($view_data['redirect_url']->setAbsolute()->toString());
    }
  }

  /**
   * AJAX callback to update selection (multipage).
   *
   * @param string $view_id
   *   The current view ID.
   * @param string $display_id
   *   The display ID of the current view.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   */
  public function updateSelection($view_id, $display_id, Request $request) {
    $response = [];
    $tempstore_data = $this->getTempstoreData($view_id, $display_id);
    if (empty($tempstore_data)) {
      throw new NotFoundHttpException();
    }

    $list = $request->request->get('list');

    $op = $request->request->get('op', 'check');
    // Reverse operation when in exclude mode.
    if (!empty($tempstore_data['exclude_mode'])) {
      if ($op === 'add') {
        $op = 'remove';
      }
      elseif ($op === 'remove') {
        $op = 'add';
      }
    }

    switch ($op) {
      case 'add':
        foreach ($list as $bulkFormKey) {
          if (!isset($tempstore_data['list'][$bulkFormKey])) {
            $tempstore_data['list'][$bulkFormKey] = $this->getListItem($bulkFormKey);
          }
        }
        break;

      case 'remove':
        foreach ($list as $bulkFormKey) {
          if (isset($tempstore_data['list'][$bulkFormKey])) {
            unset($tempstore_data['list'][$bulkFormKey]);
          }
        }
        break;

      case 'method_include':
        unset($tempstore_data['exclude_mode']);
        $tempstore_data['list'] = [];
        break;

      case 'method_exclude':
        $tempstore_data['exclude_mode'] = TRUE;
        $tempstore_data['list'] = [];
        break;
    }

    $this->setTempstoreData($tempstore_data);

    $count = empty($tempstore_data['exclude_mode']) ? count($tempstore_data['list']) : $tempstore_data['total_results'] - count($tempstore_data['list']);

    $selection_info_renderable = $this->getMultipageList($tempstore_data);
    $response_data = [
      'count' => $count,
      'selection_info' => $this->renderer->renderRoot($selection_info_renderable),
    ];

    $response = new AjaxResponse();
    $response->setData($response_data);
    return $response;
  }

}
