<?php

/**
 * @file
 * Contains \Drupal\wechat\Form\WechatRequestMessageTypeDeleteForm.
 */

namespace Drupal\wechat\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for deleting a request message type entity.
 */
class WechatRequestMessageTypeDeleteForm extends EntityDeleteForm {

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
    $request_messages = $this->queryFactory->get('wechat_request_message')->condition('msg_type', $this->entity->id())->execute();
    if (!empty($request_messages)) {
      $caption = '<p>' . $this->formatPlural(count($request_messages), '%label is used by 1 request message on your site. You can not remove this request message type until you have removed all of the %label request messages.', '%label is used by @count request messages on your site. You may not remove %label until you have removed all of the %label request messages.', array('%label' => $this->entity->label())) . '</p>';
      $form['description'] = array('#markup' => $caption);
      return $form;
    }
    else {
      return parent::buildForm($form, $form_state);
    }
  }

}
