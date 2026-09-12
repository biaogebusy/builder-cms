<?php

namespace Drupal\commerce_order\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_billing_information' formatter.
 */
#[FieldFormatter(
  id: "commerce_billing_information",
  label: new TranslatableMarkup("Billing information"),
  field_types: ["entity_reference_revisions"],
)]
class BillingInformationFormatter extends EntityReferenceRevisionsEntityFormatter {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'profile_view_mode' => 'admin',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['profile_view_mode'] = [
      '#type' => 'select',
      '#options' => $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type')),
      '#title' => $this->t('Billing profile view mode'),
      '#default_value' => $this->getSetting('profile_view_mode'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $view_modes = $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type'));
    $view_mode = $this->getSetting('profile_view_mode');
    $summary[] = $this->t('Billing profile view mode: @mode', ['@mode' => $view_modes[$view_mode] ?? $view_mode]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $items->getEntity();
    $content = [];

    $url = Url::fromRoute('commerce_order.entity_form.form_mode', [
      'commerce_order' => $items->getEntity()->id(),
      'form_mode' => 'billing-information',
    ]);

    if ($billing_profile = $order->getBillingProfile()) {
      $profile_view_mode = $this->getSetting('profile_view_mode');
      $view_builder = $this->entityTypeManager->getViewBuilder('profile');
      $content['billing_profile'] = $view_builder->view($billing_profile, $profile_view_mode);

      if ($url->access()) {
        $badge = [
          '#type' => 'link',
          '#title' => $this->t('Edit'),
          '#url' => $url,
          '#attributes' => [
            'class' => ['use-ajax', 'commerce-edit-link'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode([
              'width' => 880,
              'title' => $this->t('Edit billing information'),
            ]),
          ],
        ];
      }
    }
    elseif ($url->access()) {
      $content['add_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Add billing information'),
        '#url' => $url,
        '#attributes' => [
          'class' => ['use-ajax', 'button', 'button--primary'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 880,
            'title' => $this->t('Add billing information'),
          ]),
        ],
      ];
    }

    $element = [
      '#type' => 'component',
      '#component' => 'commerce:commerce-admin-card',
      '#props' => [
        'id' => 'billing-information-admin-card',
        'title' => $this->t('Billing information'),
        'badge' => $badge ?? '',
      ],
    ];

    // Add card content when it is possible.
    if (!empty($content)) {
      $element['#slots']['card_content'] = $content;
    }

    return [$element];
  }

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL) {
    $elements = parent::view($items, $langcode);
    // Be sure the field's label is hidden even if it's enabled on view mode.
    $elements['#label_display'] = 'hidden';
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getTargetEntityTypeId() === 'commerce_order' &&  $field_definition->getName() === 'billing_profile';
  }

}
