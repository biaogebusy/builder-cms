<?php

/**
 * @file
 * Contains \Drupal\wechat\Form\WechatResponseMessageTypeDeleteForm.
 */

namespace Drupal\wechat\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for deleting a response message type entity.
 */
class WechatResponseMessageTypeDeleteForm extends EntityDeleteForm {

  /**
   * The query factory to create entity queries.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  public $queryFactory;

  /**
   * Constructs a query factory object.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The entity query object.
   */
  public function __construct(QueryFactory $query_factory) {
    $this->queryFactory = $query_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $response_messages = $this->queryFactory->get('wechat_response_message')->condition('msg_type', $this->entity->id())->execute();
    if (!empty($response_messages)) {
      $caption = '<p>' . $this->formatPlural(count($response_messages), '%label is used by 1 response message on your site. You can not remove this response message type until you have removed all of the %label response messages.', '%label is used by @count response messages on your site. You may not remove %label until you have removed all of the %label response messages.', array('%label' => $this->entity->label())) . '</p>';
      $form['description'] = array('#markup' => $caption);
      return $form;
    }
    else {
      return parent::buildForm($form, $form_state);
    }
  }

}
