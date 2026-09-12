<?php

namespace Drupal\commerce_log\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for Commerce Log.
 */
class CommerceLogHooks {

  use StringTranslationTrait;

  /**
   * The extra field key used to expose the activity log in field displays.
   */
  const ACTIVITY_FIELD_KEY = 'commerce_activity_log';

  /**
   * Constructs a new CommerceLogHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info.
   */
  public function __construct(
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
  ) {
  }

  /**
   * Implements hook_entity_extra_field_info().
   *
   * Exposes the order activity log as a pseudo-field so it can be placed and
   * controlled via Manage Display, Display Suite, or Layout Builder, rather
   * than always being appended unconditionally via preprocess.
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $fields = [];
    // Expose for all order bundles.
    $order_bundle_info = $this->entityTypeBundleInfo->getBundleInfo('commerce_order');
    foreach (array_keys($order_bundle_info) as $bundle) {
      $fields['commerce_order'][$bundle]['display'][self::ACTIVITY_FIELD_KEY] = [
        'label' => $this->t('Activity log'),
        'description' => $this->t('The order activity log and admin comment form.'),
        'weight' => 100,
        'visible' => FALSE,
      ];
    }
    return $fields;
  }

  /**
   * Implements hook_ENTITY_TYPE_view() for commerce_order.
   *
   * Renders the activity log into the field pipeline when the pseudo-field is
   * enabled on the active display. This allows Display Suite, Layout Builder,
   * and standard Manage Display to control placement and visibility.
   */
  #[Hook('commerce_order_view')]
  public function commerceOrderView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($display->getComponent(self::ACTIVITY_FIELD_KEY)) {
      $build[self::ACTIVITY_FIELD_KEY] = [
        '#type' => 'view',
        '#name' => 'commerce_activity',
        '#display_id' => 'default',
        '#arguments' => [$entity->id(), 'commerce_order'],
        '#embed' => TRUE,
        '#title' => $this->t('Order activity'),
        '#weight' => $display->getComponent(self::ACTIVITY_FIELD_KEY)['weight'] ?? 100,
      ];
    }
  }

  /**
   * Implements hook_preprocess_commerce_order().
   *
   * Retained for backward compatibility with themes and templates that
   * reference {{ order.activity }} directly. Skipped when the activity log
   * pseudo-field is already being rendered via the field pipeline (i.e. when
   * it is enabled on the current display), to avoid double-rendering.
   */
  #[Hook('preprocess_commerce_order')]
  public function preprocessCommerceOrder(&$variables): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $variables['elements']['#commerce_order'];

    // Skip if the pseudo-field is handling rendering for this display.
    if (isset($variables['elements'][self::ACTIVITY_FIELD_KEY])) {
      return;
    }

    $variables['order']['activity'] = [
      '#type' => 'view',
      '#name' => 'commerce_activity',
      '#display_id' => 'default',
      '#arguments' => [$order->id(), 'commerce_order'],
      '#embed' => TRUE,
      '#title' => $this->t('Order activity'),
    ];
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'commerce_order_add_form'.
   */
  #[Hook('form_commerce_order_add_form_alter')]
  public function commerceOrderAddFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#submit'][] = [static::class, 'commerceOrderAddFormSubmit'];
  }

  /**
   * Submission handler for the "order add form".
   */
  public static function commerceOrderAddFormSubmit(array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\commerce_log\LogStorageInterface $log_storage */
    $log_storage = \Drupal::entityTypeManager()->getStorage('commerce_log');
    $order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $order_storage->load($form_state->getValue('order_id'));
    $log_storage->generate($order, 'order_created_admin')->save();
  }

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data['views']['commerce_log_admin_comment_form'] = [
      'title' => $this->t('Admin comment form'),
      'help' => $this->t('Displays a form that allows admins with the proper permission to add a log as comment. Requires an entity ID argument.'),
      'area' => [
        'id' => 'commerce_log_admin_comment_form',
      ],
    ];

    return $data;
  }

}
