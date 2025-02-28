<?php

namespace Drupal\page_manager_ui\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the form to delete a PageVariant.
 */
class PageVariantDeleteForm extends ConfirmFormBase {

  /**
   * Tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempstore;

  /**
   * Constructs a PageVariantDeleteForm.
   *
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempstore
   *   The tempstore factory.
   */
  public function __construct(SharedTempStoreFactory $tempstore) {
    $this->tempstore = $tempstore;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.shared')
    );
  }

  /**
   * Get the tempstore id.
   *
   * @return string
   *   The temp store id.
   */
  protected function getTempstoreId() {
    return 'page_manager.page';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'page_manager_variant_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete this variant?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $machine_name = $this->getRouteMatch()->getParameter('machine_name');
    return new Url('entity.page.edit_form', [
      'machine_name' => $machine_name,
      'step' => 'general',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $machine_name = $this->getRouteMatch()->getParameter('machine_name');
    $variant_machine_name = $this->getRouteMatch()->getParameter('variant_machine_name');
    $cached_values = $this->tempstore->get($this->getTempstoreId())->get($machine_name);
    /** @var \Drupal\page_manager\PageInterface $page */
    $page = $cached_values['page'];
    $page_variant = $page->getVariant($variant_machine_name);

    // Add to a list to remove for real later.
    $cached_values['deleted_variants'][$variant_machine_name] = $page_variant;
    $this->messenger()->addMessage($this->t('The variant %label has been removed.', [
      '%label' => $page_variant->label(),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());

    $this->tempstore->get($this->getTempstoreId())->set($page->id(), $cached_values);
  }

}
