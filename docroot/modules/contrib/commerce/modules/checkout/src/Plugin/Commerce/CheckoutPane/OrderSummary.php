<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;

/**
 * Provides the Order summary pane.
 */
#[CommerceCheckoutPane(
  id: "order_summary",
  label: new TranslatableMarkup("Order summary"),
  default_step: "_sidebar",
  wrapper_element: "container",
)]
class OrderSummary extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $available_views = $this->getApplicableSummaryViews();
    return [
      'view' => $available_views ? key($available_views) : '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $parent_summary = parent::buildConfigurationSummary();
    if ($this->configuration['view']) {
      $view_storage = $this->entityTypeManager->getStorage('view');
      $view = $view_storage->load($this->configuration['view']);
      if ($view) {
        $summary = $this->t('View: @view', ['@view' => $view->label()]);
        return $parent_summary ? implode('<br>', [$parent_summary, $summary]) : $summary;
      }

      return $parent_summary;
    }
    else {
      $summary = $this->t('View: Not used');
      return $parent_summary ? implode('<br>', [$parent_summary, $summary]) : $summary;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['use_view'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use a View to display the order summary'),
      '#description' => $this->t('Overrides the checkout order summary template with the output of a View.'),
      '#default_value' => !empty($this->configuration['view']),
    ];

    $form['view'] = [
      '#type' => 'select',
      '#title' => $this->t('View'),
      '#options' => $this->getApplicableSummaryViews(),
      '#default_value' => $this->configuration['view'],
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="configuration[panes][order_summary][configuration][use_view]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['view'] = NULL;
      if ($values['use_view']) {
        $this->configuration['view'] = $values['view'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    if ($this->configuration['view']) {
      $pane_form['summary'] = [
        '#type' => 'view',
        '#name' => $this->configuration['view'],
        '#display_id' => 'default',
        '#arguments' => [$this->order->id()],
        '#embed' => TRUE,
      ];
    }
    else {
      $pane_form['summary'] = [
        '#theme' => 'commerce_checkout_order_summary',
        '#order_entity' => $this->order,
        '#checkout_step' => $complete_form['#step_id'],
      ];
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {}

  /**
   * Gets the applicable order summary views.
   *
   * @return array
   *   The applicable order summary views.
   */
  protected function getApplicableSummaryViews(): array {
    $view_storage = $this->entityTypeManager->getStorage('view');
    $available_summary_views = [];
    /** @var \Drupal\views\Entity\View $view */
    foreach ($view_storage->loadMultiple() as $view) {
      if (!str_contains($view->get('tag'), 'commerce_order_summary')) {
        continue;
      }
      $available_summary_views[$view->id()] = $view->label();
    }

    return $available_summary_views;
  }

}
