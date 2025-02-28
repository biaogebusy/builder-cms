<?php

namespace Drupal\commerce_order\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\profile\Form\ProfileForm;

class ProfileAddressBookForm extends ProfileForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    // Remove the details wrapper from the address widget.
    $form['address']['widget'][0]['#type'] = 'container';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = $this->entity;
    $profile->save();

    $this->messenger()->addMessage($this->t('Saved the %label address.', ['%label' => $profile->label()]));
    $form_state->setRedirect('commerce_order.address_book.overview', [
      'user' => $profile->getOwnerId(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    if (isset($actions['delete'])) {
      $route_info = Url::fromRoute('commerce_order.address_book.delete_form', [
        'user' => $this->entity->getOwnerId(),
        'profile' => $this->entity->id(),
      ]);
      if ($this->getRequest()->query->has('destination')) {
        $query = $route_info->getOption('query');
        $query['destination'] = $this->getRequest()->query->get('destination');
        $route_info->setOption('query', $query);
      }
      $actions['delete']['#url'] = $route_info;
    }

    return $actions;
  }

}
