<?php

namespace Drupal\commerce_promotion\Form;

use Drupal\commerce_promotion\Entity\PromotionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Url;

/**
 * Defines the promotion delete form.
 */
class PromotionDeleteForm extends ContentEntityDeleteForm {

  /**
   * The promotion usage.
   *
   * @var \Drupal\commerce_promotion\PromotionUsageInterface
   */
  protected $usage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->usage = $container->get('commerce_promotion.usage');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the promotion %label?', [
      '%label' => $this->getEntity()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.commerce_promotion.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    assert($this->entity instanceof PromotionInterface);
    $usage_count = $this->usage->load($this->entity);
    if ($usage_count === 0) {
      return parent::getDescription();
    }
    $orders_text = $this->formatPlural(
      $usage_count,
      'This promotion was applied to 1 order.',
      'This promotion was applied to @count orders.'
    );

    return $this->t(
      '@orders_text<br/>Deleting it is not recommended. Consider disabling it instead.',
      [
        '@orders_text' => $orders_text,
      ],
    );
  }

}
