<?php

namespace Drupal\menu_item_extras\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\menu_item_extras\Service\MenuLinkContentServiceInterface;

/**
 * Class ConfirmClearMenuForm.
 *
 * Defines a confirmation form to confirm clearing data of some menu by name.
 */
class ConfirmClearMenuForm extends EntityConfirmFormBase {

  /**
   * The menu link content service helper.
   *
   * @var \Drupal\menu_item_extras\Service\MenuLinkContentServiceInterface
   */
  protected $menuLinkContentHelper;

  /**
   * Constructs a new \Drupal\menu_item_extras\Form\ConfirmClearMenuForm object.
   *
   * @param \Drupal\menu_item_extras\Service\MenuLinkContentServiceInterface $menuLinkContentHelper
   *   The menu link content service helper.
   */
  public function __construct(MenuLinkContentServiceInterface $menuLinkContentHelper) {
    $this->menuLinkContentHelper = $menuLinkContentHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('menu_item_extras.menu_link_content_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->menuLinkContentHelper->clearMenuData($this->entity->id());
    $this->messenger()->addStatus($this->t('Extra data for %label was deleted.', [
      '%label' => $this->entity->label(),
    ]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return "confirm_clear_menu_data";
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.menu.edit_form', [
      'menu' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to clear extra data in %menu_name?', ['%menu_name' => $this->entity->label()]);
  }

}
