<?php

namespace Drupal\page_manager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\layout_builder\Form\PreviewToggleTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form containing the Layout Builder UI for Page Manager.
 *
 * @todo Refactor this after https://www.drupal.org/node/3035189 is resolved.
 */
class LayoutBuilderForm extends FormBase {

  use PreviewToggleTrait;

  /**
   * Layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The Section Storage Manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManager
   */
  protected $sectionStorageManager;

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * The tempstore directory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempstore;

  /**
   * Constructs a new LayoutBuilderForm.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManager $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempstore
   *   The tempstore factory.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, SectionStorageManager $section_storage_manager, SharedTempStoreFactory $tempstore) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->sectionStorageManager = $section_storage_manager;
    $this->tempstore = $tempstore;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('tempstore.shared')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'page_manager_layout_builder_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');
    /** @var \Drupal\page_manager\PageVariantInterface $page_variant */
    $page_variant = $cached_values['page_variant'];

    // If this is a new variant, put it in the tempstore so that we can
    // retrieve it by id later.
    // @see \Drupal\page_manager\Plugin\SectionStorage\PageManagerSectionStorage::deriveContextsFromRoute.
    if ($page_variant->isNew()) {
      $this->tempstore->get('page_manager.layout_builder')->set($page_variant->id(), $page_variant);
    }

    $section_storage = $this->sectionStorageManager->load('page_manager', [
      'entity' => EntityContext::fromEntity($page_variant),
    ]);

    $this->sectionStorage = $this->layoutTempstoreRepository->get($section_storage);

    $form['preview_toggle'] = $this->buildContentPreviewToggle();

    $form['layout_builder'] = [
      '#type' => 'layout_builder',
      '#section_storage' => $this->sectionStorage,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');

    /** @var \Drupal\page_manager\Entity\PageVariant $page_variant */
    $page_variant = $this->sectionStorage->getContextValue('entity');

    // Pass down the variant settings and let the plugin handle saving it.
    /** @var \Drupal\page_manager\Plugin\DisplayVariant\LayoutBuilderDisplayVariant $variant_plugin */
    $variant_plugin = $cached_values['plugin'];
    $variant_plugin->setConfiguration($page_variant->get('variant_settings'));

    // The form wizard takes care of saving the variant.
    // Clear the layout tempstore.
    $this->layoutTempstoreRepository->delete($this->sectionStorage);
    $this->messenger()->addMessage($this->t('The layout has been saved.'));

    // Clean up the tempstore.
    $this->tempstore->get('page_manager.layout_builder')->delete($page_variant->id());
    $this->tempstore->get('page_manager.page_variant')->delete($page_variant->id());
  }

}
