<?php

namespace Drupal\commerce_order\Plugin\Commerce\InlineForm;

use Drupal\commerce\Attribute\CommerceInlineForm;
use Drupal\commerce\Plugin\Commerce\InlineForm\ContentEntity;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an inline form for managing an order item.
 */
#[CommerceInlineForm(
  id: 'order_item',
  label: new TranslatableMarkup('Order item'),
)]
class OrderItem extends ContentEntity {

  /**
   * The customer profile.
   *
   * @var \Drupal\commerce_order\Entity\OrderItemInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function buildInlineForm(array $inline_form, FormStateInterface $form_state) {
    $inline_form = parent::buildInlineForm($inline_form, $form_state);
    assert($this->entity instanceof OrderItemInterface);
    $inline_form['#op'] = $this->configuration['operation'] ?? 'add';
    return $inline_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInlineForm(array &$inline_form, FormStateInterface $form_state) {
    parent::validateInlineForm($inline_form, $form_state);

    if (!isset($inline_form['rendered'])) {
      $form_display = $this->loadFormDisplay();
      $form_display->extractFormValues($this->entity, $inline_form, $form_state);
      $form_display->validateFormValues($this->entity, $inline_form, $form_state);
    }
  }

  /**
   * Loads the form display used to build the order item form.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The form display.
   */
  protected function loadFormDisplay() {
    $form_mode = $this->configuration['form_mode'] ?? 'default';
    $form_display = EntityFormDisplay::collectRenderDisplay($this->entity, $form_mode);

    return $form_display;
  }

}
