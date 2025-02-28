<?php

namespace Drupal\entity_print\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_print\Plugin\ExportTypeManagerInterface;
use Drupal\entity_print\PrintBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Print controller.
 */
class EntityPrintController extends ControllerBase {

  /**
   * The plugin manager for our Print engines.
   *
   * @var \Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface
   */
  protected $pluginManager;

  /**
   * The export type manager.
   *
   * @var \Drupal\entity_print\Plugin\ExportTypeManagerInterface
   */
  protected $exportTypeManager;

  /**
   * The Print builder.
   *
   * @var \Drupal\entity_print\PrintBuilderInterface
   */
  protected $printBuilder;

  /**
   * The Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityPrintPluginManagerInterface $plugin_manager, ExportTypeManagerInterface $export_type_manager, PrintBuilderInterface $print_builder, EntityTypeManagerInterface $entity_type_manager) {
    $this->pluginManager = $plugin_manager;
    $this->exportTypeManager = $export_type_manager;
    $this->printBuilder = $print_builder;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.entity_print.print_engine'),
      $container->get('plugin.manager.entity_print.export_type'),
      $container->get('entity_print.print_builder'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Print an entity to the selected format.
   *
   * @param string $export_type
   *   The export type.
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object on error otherwise the Print is sent.
   */
  public function viewPrint($export_type, $entity_type, $entity_id) {
    // Create the Print engine plugin.
    $config = $this->config('entity_print.settings');
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);

    $print_engine = $this->pluginManager->createSelectedInstance($export_type);
    return (new StreamedResponse(function () use ($entity, $print_engine, $config) {
      // The Print is sent straight to the browser.
      $this->printBuilder->deliverPrintable([$entity], $print_engine, $config->get('force_download'), $config->get('default_css'));
    }))->send();
  }

  /**
   * A debug callback for styling up the Print.
   *
   * @param string $export_type
   *   The export type.
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   *
   * @todo improve permissions in https://www.drupal.org/node/2759553
   */
  public function viewPrintDebug($export_type, $entity_type, $entity_id) {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    $use_default_css = $this->config('entity_print.settings')->get('default_css');
    return new Response($this->printBuilder->printHtml($entity, $use_default_css, FALSE));
  }

  /**
   * Validate that the current user has access.
   *
   * We need to validate that the user is allowed to access this entity also the
   * print version.
   *
   * @param string $export_type
   *   The export type.
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result object.
   */
  public function checkAccess($export_type, $entity_type, $entity_id) {
    if (empty($entity_id)) {
      return AccessResult::forbidden();
    }

    $account = $this->currentUser();

    // Invalid storage type.
    if (!$this->entityTypeManager->hasHandler($entity_type, 'storage')) {
      return AccessResult::forbidden();
    }

    // Unable to find the entity requested.
    if (!$entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id)) {
      return AccessResult::forbidden();
    }

    // Ensure it's a valid export type.
    if (!in_array($export_type, array_keys($this->exportTypeManager->getDefinitions()))) {
      return AccessResult::forbidden();
    }

    // Check if the user has the permission "bypass entity print access".
    $access_result = AccessResult::allowedIfHasPermission($account, 'bypass entity print access');
    if ($access_result->isAllowed()) {
      return $access_result->andIf($entity->access('view', $account, TRUE));
    }

    // Check if the user is allowed to view all bundles of the entity type.
    $access_result = AccessResult::allowedIfHasPermission($account, 'entity print access type ' . $entity_type);
    if ($access_result->isAllowed()) {
      return $access_result->andIf($entity->access('view', $account, TRUE));
    }

    // Check if the user is allowed to view that bundle type.
    $access_result = AccessResult::allowedIfHasPermission($account, 'entity print access bundle ' . $entity->bundle());
    if ($access_result->isAllowed()) {
      return $access_result->andIf($entity->access('view', $account, TRUE));
    }

    return AccessResult::forbidden();
  }

  /**
   * Provides a redirect BC layer for the old routes.
   *
   * @param string $export_type
   *   The export type.
   * @param string $entity_type
   *   The entity type.
   * @param string|int $entity_id
   *   The entity type id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function viewRedirect($export_type, $entity_type, $entity_id) {
    return $this->redirect('entity_print.view', [
      'export_type' => $export_type,
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
    ]);
  }

  /**
   * Provides a redirect BC layer for the old routes.
   *
   * @param string $export_type
   *   The export type.
   * @param string $entity_type
   *   The entity type.
   * @param string|int $entity_id
   *   The entity type id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function viewRedirectDebug($export_type, $entity_type, $entity_id) {
    return $this->redirect('entity_print.view.debug', [
      'export_type' => $export_type,
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
    ]);
  }

}
