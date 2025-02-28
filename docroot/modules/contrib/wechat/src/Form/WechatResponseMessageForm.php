<?php

namespace Drupal\wechat\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the wechat response message add/edit form.
 *
 */
class WechatResponseMessageForm extends ContentEntityForm {


  /**
   * Constructs a new ProductForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    parent::__construct($entity_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /* @var \Drupal\commerce_product\Entity\Product $product */
    $message = $this->entity;
    $form = parent::form($form, $form_state);
    if (isset($_GET['rm_id'])) {
      $message->rm_id->value = $_GET['rm_id'];
    }
    $request_message = \Drupal::entityManager()
        ->getStorage('wechat_request_message')
        ->load($message->rm_id->value);

	if(!empty($request_message)){
        $message->to_user_name->value = $request_message->from_user_name->value;
        $message->from_user_name->value = $request_message->to_user_name->value;
    }
    if (empty($message->to_user_name->value)) {
      $form['to_user_name'] = array(
        '#type' => 'textfield',
        '#title' => t('To user name'),
        '#maxlength' => 255,
        '#required' => TRUE,
        '#weight' => -50,
      );
    }



    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $message = $this->getEntity();
	$message->sendCustomMessage();
    $message->save();
	//drupal_set_message($message->to_user_name->value);
	//drupal_set_message($message->rm_id->value);
    drupal_set_message($this->t('The response message has been successfully saved.'));

	//$url = Url::fromRoute('view.request_messages.page_1');
	$form_state->setRedirect('view.request_messages.page_1');
    //$form_state->setRedirect('entity.wechat_response_message.canonical', ['wechat_response_message' => $message->id()]);
  }

}
