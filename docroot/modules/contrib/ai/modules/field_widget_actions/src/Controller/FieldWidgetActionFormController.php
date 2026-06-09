<?php

namespace Drupal\field_widget_actions\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\field_widget_actions\FieldWidgetActionManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;

/**
 * Controller to load Field Widget Action plugins via ajax.
 */
class FieldWidgetActionFormController extends ControllerBase {

  /**
   * Constructs the controller.
   *
   * @param \Drupal\field_widget_actions\FieldWidgetActionManagerInterface $manager
   *   The field widget action manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The temp store factory.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   */
  public function __construct(
    protected FieldWidgetActionManagerInterface $manager,
    protected PrivateTempStoreFactory $tempStoreFactory,
    FormBuilderInterface $formBuilder,
  ) {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.field_widget_actions'),
      $container->get('tempstore.private'),
      $container->get('form_builder'),
    );
  }

  /**
   * Open the modal form for a specific plugin.
   *
   * @param string $plugin_id
   *   The plugin ID passed from the route defaults.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response from the plugin.
   */
  public function openModal(string $plugin_id): AjaxResponse {
    // This wrapper modal constructor is required so the Field Widget Action
    // form-based plugins can serve as the plugin, form, and controller all at
    // once.
    /** @var \Drupal\field_widget_actions\FieldWidgetFormActionInterface $plugin */
    $plugin = $this->manager->createInstance($plugin_id);
    return $plugin->openModalForm();
  }

  /**
   * Builds the modal form using data from TempStore.
   */
  public function submitModal($plugin_id, $tempstore_id) {
    $store = $this->tempStoreFactory->get('field_widget_actions_form_collection');
    $context_data = $store->get($tempstore_id) ?? [];

    return $this->formBuilder->getForm(
      '\Drupal\field_widget_actions\Form\FieldWidgetActionFormWrapper',
      $plugin_id,
      $context_data,
    );
  }

}
