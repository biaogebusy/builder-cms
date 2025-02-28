<?php

namespace Drupal\commerce_promotion\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\entity\Form\EntityDuplicateFormTrait;

/**
 * Defines the promotion add/edit form.
 */
class PromotionForm extends ContentEntityForm {

  use EntityDuplicateFormTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Skip building the form if there are no available stores.
    $store_query = $this->entityTypeManager->getStorage('commerce_store')->getQuery()->accessCheck(TRUE);
    if ($store_query->count()->execute() == 0) {
      $link = Link::createFromRoute('Add a new store.', 'entity.commerce_store.add_page');
      $form['warning'] = [
        '#markup' => $this->t("Promotions can't be created until a store has been added. @link", ['@link' => $link->toString()]),
      ];
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    $promotion = $this->entity;
    $form['#tree'] = TRUE;
    // By default an offer is preselected on the add form because the field
    // is required. Select an empty value instead, to force the user to choose.
    $user_input = $form_state->getUserInput();
    if ($this->operation == 'add' &&
      $this->entity->get('offer')->isEmpty()) {
      if (!empty($form['offer']['widget'][0]['target_plugin_id'])) {
        $form['offer']['widget'][0]['target_plugin_id']['#empty_value'] = '';
        if (empty($user_input['offer'][0]['target_plugin_id'])) {
          $form['offer']['widget'][0]['target_plugin_id']['#default_value'] = '';
          unset($form['offer']['widget'][0]['target_plugin_configuration']);
        }
      }
    }

    $translating = !$this->isDefaultFormLangcode($form_state);
    $hide_non_translatable_fields = $this->entity->isDefaultTranslationAffectedOnly();
    // The second column is empty when translating with non-translatable
    // fields hidden, so there's no reason to add it.
    if ($translating && $hide_non_translatable_fields) {
      return $form;
    }
    if (isset($form['require_coupon'])) {
      if (!$promotion->hasCoupons()) {
        $description = $this->t('There are no coupons defined for this promotion yet.');
      }
      else {
        $coupons_count = $promotion->get('coupons')->count();
        $coupon_code = '';
        if ($coupons_count === 1) {
          $coupons = $promotion->getCoupons();
          $coupon_code = $coupons[0]->getCode();
        }
        $description = $this->formatPlural($coupons_count, 'There is one coupon defined for this promotion: @coupon_code.', 'There are @count coupons defined for this promotion.', ['@coupon_code' => $coupon_code]);
        // When the promotion references coupons, regardless of the setting
        // value, a coupon is required to apply the promotion.
        $form['require_coupon']['widget']['value']['#default_value'] = TRUE;
        $form['require_coupon']['widget']['value']['#disabled'] = TRUE;
      }
      $form['require_coupon']['widget']['value']['#description'] = $description;
    }

    $form['#theme'] = ['commerce_promotion_form'];
    $form['#attached']['library'][] = 'commerce_promotion/form';
    $form['advanced'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-meta']],
      '#weight' => 99,
    ];
    $form['option_details'] = [
      '#type' => 'container',
      '#title' => $this->t('Options'),
      '#group' => 'advanced',
      '#attributes' => ['class' => ['entity-meta__header']],
      '#weight' => -100,
    ];
    $form['date_details'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Dates'),
      '#group' => 'advanced',
    ];
    $form['coupon_details'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Coupons'),
      '#group' => 'advanced',
    ];
    $form['usage_details'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Usage limits'),
      '#group' => 'advanced',
    ];
    $form['compatibility_details'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Compatibility'),
      '#group' => 'advanced',
    ];

    $field_details_mapping = [
      'status' => 'option_details',
      'weight' => 'option_details',
      'order_types' => 'option_details',
      'stores' => 'option_details',
      'require_coupon' => 'coupon_details',
      'start_date' => 'date_details',
      'end_date' => 'date_details',
      'usage_limit' => 'usage_details',
      'usage_limit_customer' => 'usage_details',
      'compatibility' => 'compatibility_details',
    ];
    foreach ($field_details_mapping as $field => $group) {
      if (isset($form[$field])) {
        $form[$field]['#group'] = $group;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    if ($this->entity->isNew()) {
      $actions['submit_continue'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save and add coupons'),
        '#button_type' => 'secondary',
        '#continue' => TRUE,
        '#submit' => ['::submitForm', '::save'],
      ];
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->postSave($this->entity, $this->operation);
    $this->messenger()->addMessage($this->t('Saved the %label promotion.', ['%label' => $this->entity->label()]));

    if (!empty($form_state->getTriggeringElement()['#continue'])) {
      $form_state->setRedirect('entity.commerce_promotion_coupon.collection', ['commerce_promotion' => $this->entity->id()]);
    }
    else {
      $form_state->setRedirect('entity.commerce_promotion.collection');
    }
  }

}
