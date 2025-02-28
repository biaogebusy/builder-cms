<?php

namespace Drupal\commerce_product\Controller;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides title callbacks for product variation routes.
 */
class ProductVariationController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new ProductVariationController.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, TranslationInterface $string_translation, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, RendererInterface $renderer) {
    $this->entityRepository = $entity_repository;
    $this->stringTranslation = $string_translation;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('string_translation'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('renderer')
    );
  }

  /**
   * Provides the add title callback for product variations.
   *
   * @return string
   *   The title for the product variation add page.
   */
  public function addTitle() {
    return $this->t('Add variation');
  }

  /**
   * Provides the callback for adding a product.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $commerce_product
   *   The product.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   A renderable array, or a redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function addPage(ProductInterface $commerce_product) {
    $entity_type_id = 'commerce_product_variation';

    /** @var \Drupal\commerce_product\Entity\ProductTypeInterface $product_type */
    $product_type = $this->entityTypeManager->getStorage('commerce_product_type')->load($commerce_product->bundle());
    $product_variation_type_ids = $product_type->getVariationTypeIds();

    $form_route_name = 'entity.' . $entity_type_id . '.add_form';

    // Redirect if there's only one bundle available.
    if (count($product_variation_type_ids) == 1) {
      $product_variation_type_id = reset($product_variation_type_ids);
      return $this->redirect($form_route_name, [
        'commerce_product' => $commerce_product->id(),
        'commerce_product_variation_type' => $product_variation_type_id,
      ]);
    }

    $build = [
      '#theme' => 'entity_add_list',
      '#bundles' => [],
    ];
    $bundles = $this->entityTypeManager->getStorage('commerce_product_variation_type')->loadMultiple($product_variation_type_ids);
    $bundle_entity_type = $this->entityTypeManager->getDefinition('commerce_product_variation_type');

    $build['#cache']['tags'] = $bundle_entity_type->getListCacheTags();

    // Filter out the bundles the user doesn't have access to.
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler($entity_type_id);
    foreach ($bundles as $bundle_name => $bundle) {
      $access = $access_control_handler->createAccess($bundle_name, NULL, [], TRUE);
      if (!$access->isAllowed()) {
        unset($bundles[$bundle_name]);
      }
      $this->renderer->addCacheableDependency($build, $access);
    }

    $form_route_name = 'entity.' . $entity_type_id . '.add_form';
    // Redirect if there's only one bundle available.
    if (count($bundles) === 1) {
      $bundle_names = array_keys($bundles);
      $bundle_name = reset($bundle_names);
      return $this->redirect($form_route_name, [
        'commerce_product' => $commerce_product->id(),
        'commerce_product_variation_type' => $bundle_name,
      ]);
    }

    // Prepare the #bundles array for the template.
    /** @var \Drupal\commerce_product\Entity\ProductVariationTypeInterface $bundle */
    foreach ($bundles as $bundle_name => $bundle) {
      $build['#bundles'][$bundle_name] = [
        'label' => $bundle->label(),
        'description' => $bundle->get('description') ? $bundle->get('description') : '',
        'add_link' => Link::createFromRoute($bundle->label(), $form_route_name, [
          'commerce_product' => $commerce_product->id(),
          'commerce_product_variation_type' => $bundle_name,
        ]),
      ];
    }

    return $build;
  }

  /**
   * Provides the edit title callback for product variations.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return string
   *   The title for the product variation edit page.
   */
  public function editTitle(RouteMatchInterface $route_match) {
    $product_variation = $route_match->getParameter('commerce_product_variation');
    $product_variation = $this->entityRepository->getTranslationFromContext($product_variation);

    return $this->t('Edit %label', ['%label' => $product_variation->label()]);
  }

  /**
   * Provides the delete title callback for product variations.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return string
   *   The title for the product variation delete page.
   */
  public function deleteTitle(RouteMatchInterface $route_match) {
    $product_variation = $route_match->getParameter('commerce_product_variation');
    $product_variation = $this->entityRepository->getTranslationFromContext($product_variation);

    return $this->t('Delete %label', ['%label' => $product_variation->label()]);
  }

  /**
   * Provides the collection title callback for product variations.
   *
   * @return string
   *   The title for the product variation collection.
   */
  public function collectionTitle() {
    // Note that ProductVariationListBuilder::getForm() overrides the page
    // title. The title defined here is used only for the breadcrumb.
    return $this->t('Variations');
  }

  /**
   * Returns a redirect response object for the specified route.
   *
   * @param string $route_name
   *   The name of the route to which to redirect.
   * @param array $route_parameters
   *   (optional) Parameters for the route.
   * @param array $options
   *   (optional) An associative array of additional options.
   * @param int $status
   *   (optional) The HTTP redirect status code for the redirect. The default is
   *   302 Found.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object that may be returned by the controller.
   */
  protected function redirect(string $route_name, array $route_parameters = [], array $options = [], $status = 302) {
    $options['absolute'] = TRUE;
    return new RedirectResponse(Url::fromRoute($route_name, $route_parameters, $options)->toString(), $status);
  }

}
