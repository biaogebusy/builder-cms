<?php

declare(strict_types=1);

namespace Drupal\commerce_price\Plugin\Commerce\Condition;

use Drupal\commerce\Attribute\CommerceCondition;
use Drupal\commerce\EntityHelper;
use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\commerce_price\CurrentCurrencyInterface;
use Drupal\commerce_price\Entity\Currency;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the current currency condition (for the current request).
 */
#[CommerceCondition(
  id: "current_currency",
  label: new TranslatableMarkup("Current currency"),
  entity_type: "commerce_order",
  category: new TranslatableMarkup("Current request"),
)]
class CurrentCurrency extends ConditionBase implements ContainerFactoryPluginInterface {

  /**
   * The current currency.
   *
   * @var \Drupal\commerce_price\CurrentCurrencyInterface
   */
  protected CurrentCurrencyInterface $currentCurrency;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->currentCurrency = $container->get('commerce_price.current_currency');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'currencies' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $currencies = EntityHelper::extractLabels(Currency::loadMultiple());
    $form['currencies'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Currencies'),
      '#default_value' => $this->configuration['currencies'],
      '#options' => $currencies,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    $this->configuration['currencies'] = array_filter($values['currencies']);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    return in_array($this->currentCurrency->getCurrency()?->getCurrencyCode(), $this->configuration['currencies'], TRUE);
  }

}
