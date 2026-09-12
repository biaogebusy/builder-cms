<?php

namespace Drupal\commerce_product\Form;

use Drupal\config_translation\Form\ConfigTranslationFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the form for adding product attribute translations.
 */
class ProductAttributeTranslationAddForm extends ConfigTranslationFormBase {

  use ProductAttributeTranslationFormTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_product_attribute_translation_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RouteMatchInterface $route_match = NULL, $plugin_id = NULL, $langcode = NULL) {
    $form = parent::buildForm($form, $form_state, $route_match, $plugin_id, $langcode);
    $form = $this->buildValuesForm($form, $form_state, $this->mapper->getEntity());

    $form['#title'] = $this->t('Add @language translation for %label', [
      '%label' => $this->mapper->getTitle(),
      '@language' => $this->language->getName(),
    ]);

    return $form;
  }

}
