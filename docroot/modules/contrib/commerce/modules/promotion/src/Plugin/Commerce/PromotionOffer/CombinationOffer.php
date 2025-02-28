<?php

namespace Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer;

use Drupal\commerce\ConditionGroup;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the 'combination_offer' offer plugin.
 *
 * This provides support for combining/stacking multiple promotion offers.
 *
 * @CommercePromotionOffer(
 *   id = "combination_offer",
 *   label = @Translation("Combination offer"),
 *   entity_type = "commerce_order",
 * )
 */
class CombinationOffer extends OrderPromotionOfferBase implements CombinationOfferInterface {

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * The promotion offer manager.
   *
   * @var \Drupal\commerce_promotion\PromotionOfferManager
   */
  protected $offerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    $instance->offerManager = $container->get('plugin.manager.commerce_promotion_offer');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'offers' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form += parent::buildConfigurationForm($form, $form_state);
    // Remove the main fieldset.
    $form['#type'] = 'container';
    $wrapper_id = Html::getUniqueId('combination-offer-ajax-wrapper');
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';
    // Remove the combination offer from the allowed plugins.
    $definitions = array_diff_key($this->offerManager->getDefinitions(), [$this->pluginId => '']);
    $plugins = array_map(static function ($definition) {
      return $definition['label'];
    }, $definitions);
    asort($plugins);

    $user_input = (array) NestedArray::getValue($form_state->getUserInput(), $form['#parents']);
    // Initialize the offers form.
    if (!$form_state->get('offers_form_initialized')) {
      $offers = $this->configuration['offers'] ?: [NULL];
      // Initialize the offers with the user input if present.
      if (isset($user_input['offers'])) {
        $offers = $user_input['offers'];
      }
      $form_state->set('offers', $offers);
      $form_state->set('offers_form_initialized', TRUE);
    }
    $class = get_class($this);

    foreach ($form_state->get('offers') as $index => $offer) {
      // Override the offer with the user input if present.
      if (!empty($user_input['offers'][$index])) {
        $offer = $user_input['offers'][$index];
      }
      $offer_form = &$form['offers'][$index];
      $offer_form['configuration'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Offer #@index', ['@index' => $index + 1]),
        '#tree' => FALSE,
      ];
      $offer_form['configuration']['target_plugin_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Type'),
        '#options' => $plugins,
        '#default_value' => $offer['target_plugin_id'] ?? '',
        // Force the user to select a value.
        '#empty_value' => '',
        // Only require at least an offer.
        '#required' => $index === 0,
        '#ajax' => [
          'callback' => [$class, 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
        '#parents' => array_merge($form['#parents'], ['offers', $index, 'target_plugin_id']),
      ];

      // When a target plugin ID is selected, embed the offer configuration
      // form.
      if (!empty($offer['target_plugin_id'])) {
        $inline_form = $this->inlineFormManager->createInstance('plugin_configuration', [
          'plugin_type' => 'commerce_promotion_offer',
          'plugin_id' => $offer['target_plugin_id'],
          'plugin_configuration' => $offer['target_plugin_configuration'] ?? [],
        ]);
        $offer_form['configuration']['target_plugin_configuration'] = [
          '#inline_form' => $inline_form,
          '#parents' => array_merge($form['#parents'], ['offers', $index, 'target_plugin_configuration']),
        ];
        $offer_form['configuration']['target_plugin_configuration'] = $inline_form->buildInlineForm($offer_form['configuration']['target_plugin_configuration'], $form_state);
      }

      // Don't allow removing the first offer.
      if ($index > 0) {
        $offer_form['configuration']['remove'] = [
          '#type' => 'submit',
          '#name' => 'remove_offer' . $index,
          '#value' => $this->t('Remove'),
          '#limit_validation_errors' => [],
          '#submit' => [[$class, 'removeOfferSubmit']],
          '#offer_index' => $index,
          '#parents' => array_merge($form['#parents'], ['offers', $index, 'remove']),
          '#ajax' => [
            'callback' => [$class, 'ajaxCallback'],
            'wrapper' => $wrapper_id,
          ],
        ];
      }
    }

    $form['offers'][] = [
      'add_offer' => [
        '#type' => 'submit',
        '#value' => $this->t('Add another offer'),
        '#submit' => [[$class, 'addOfferSubmit']],
        '#limit_validation_errors' => [array_merge($form['#parents'], ['offers'])],
        '#ajax' => [
          'callback' => [$class, 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);
    // Filter out the button rows.
    $values['offers'] = array_filter($values['offers'], function ($offer) {
      return !empty($offer['target_plugin_id']) &&
        !empty($offer['target_plugin_configuration']) &&
        !isset($offer['add_offer']);
    });
    $form_state->setValue($form['#parents'], $values);

    if (empty($values['offers'])) {
      $form_state->setError($form['offers'], $this->t('Please configure at least one offer.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $this->configuration['offers'] = [];
      $values = $form_state->getValue($form['#parents']);

      foreach (array_filter($values['offers']) as $offer) {
        $this->configuration['offers'][] = $offer;
      }
    }
  }

  /**
   * Ajax callback.
   */
  public static function ajaxCallback(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#array_parents'];
    $form_index = array_search('form', $parents, TRUE);
    $parents = array_splice($parents, 0, $form_index + 1);
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Submit callback for adding a new offer.
   */
  public static function addOfferSubmit(array $form, FormStateInterface $form_state) {
    $offers = $form_state->get('offers');
    $offers[] = [];
    $form_state->set('offers', $offers);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for removing an offer.
   */
  public static function removeOfferSubmit(array $form, FormStateInterface $form_state) {
    $offers = $form_state->get('offers');
    $index = $form_state->getTriggeringElement()['#offer_index'];
    unset($offers[$index]);
    $form_state->set('offers', $offers);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function apply(EntityInterface $entity, PromotionInterface $promotion) {
    assert($entity instanceof OrderInterface);
    // This is copied from Promotion::apply().
    foreach ($this->getOffers() as $offer) {
      if ($offer instanceof OrderItemPromotionOfferInterface) {
        $offer_conditions = new ConditionGroup($offer->getConditions(), $offer->getConditionOperator());
        // Apply the offer to order items that pass the conditions.
        foreach ($entity->getItems() as $order_item) {
          // Skip order items with a null unit price or with a quantity = 0.
          if (!$order_item->getUnitPrice() ||
            Calculator::compare($order_item->getQuantity(), '0') === 0) {
            continue;
          }
          if ($offer_conditions->evaluate($order_item)) {
            $offer->apply($order_item, $promotion);
          }
        }
      }
      else {
        $offer->apply($entity, $promotion);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOffers() {
    $offers = [];
    foreach ($this->configuration['offers'] as $offer) {
      $offers[] = $this->offerManager->createInstance($offer['target_plugin_id'], $offer['target_plugin_configuration']);
    }
    return $offers;
  }

}
