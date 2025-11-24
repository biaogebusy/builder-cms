<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the completion message pane.
 */
#[CommerceCheckoutPane(
  id: "completion_message",
  label: new TranslatableMarkup("Completion message"),
  admin_description: new TranslatableMarkup("Outputs a configurable message once the checkout process is complete."),
  default_step: "complete",
)]
class CompletionMessage extends CheckoutPaneBase {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager')
    );
    $instance->setToken($container->get('token'));
    return $instance;
  }

  /**
   * Sets the token service.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function setToken(Token $token) {
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'message' => [
        'value' => "Your order number is [commerce_order:order_number].\r\nYou can view your order on your account page when logged in.",
        'format' => 'plain_text',
      ],
      'display_pane_summaries' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $parent_summary = parent::buildConfigurationSummary();

    if (!empty($this->configuration['display_pane_summaries'])) {
      $summary = $this->t('Displays checkout pane summaries: Yes');
    }
    else {
      $summary = $this->t('Displays checkout pane summaries: No');
    }

    return $parent_summary ? implode('<br>', [$parent_summary, $summary]) : $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Message'),
      '#description' => $this->t('Shown the end of checkout, after the customer has placed their order.'),
      '#default_value' => $this->configuration['message']['value'],
      '#format' => $this->configuration['message']['format'],
      '#element_validate' => ['token_element_validate'],
      '#token_types' => ['commerce_order'],
      '#required' => TRUE,
    ];
    $form['token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['commerce_order'],
    ];
    $form['display_pane_summaries'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display checkout pane summaries'),
      '#description' => $this->t('If checked, display the summaries of all checkout panes after the completion message configured above.'),
      '#default_value' => $this->configuration['display_pane_summaries'],
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
      $this->configuration['message'] = $values['message'];
      $this->configuration['display_pane_summaries'] = !empty($values['display_pane_summaries']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $message = $this->token->replace($this->configuration['message']['value'], [
      'commerce_order' => $this->order,
    ]);
    $pane_form['message'] = [
      '#theme' => 'commerce_checkout_completion_message',
      '#order_entity' => $this->order,
      '#message' => [
        '#type' => 'processed_text',
        '#text' => $message,
        '#format' => $this->configuration['message']['format'],
      ],
    ];
    if (!empty($this->configuration['display_pane_summaries'])) {
      $enabled_panes = array_filter($this->checkoutFlow->getPanes(), function ($pane) {
        return !in_array($pane->getStepId(), ['_sidebar', '_disabled']);
      });
      foreach ($enabled_panes as $pane_id => $pane) {
        if ($summary = $pane->buildPaneSummary()) {
          // BC layer for panes which still return rendered strings.
          if (!is_array($summary)) {
            $summary = [
              '#markup' => $summary,
            ];
          }

          $label = $summary['#title'] ?? $pane->getDisplayLabel();
          $pane_form[$pane_id] = [
            '#type' => 'fieldset',
            '#title' => $label,
          ];
          $pane_form[$pane_id]['summary'] = $summary;
        }
      }
    }

    return $pane_form;
  }

}
