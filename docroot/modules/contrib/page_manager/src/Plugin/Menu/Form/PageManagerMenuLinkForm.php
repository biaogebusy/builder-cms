<?php

namespace Drupal\page_manager\Plugin\Menu\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\Form\MenuLinkDefaultForm;

/**
 * Provides a form to edit Page menu links.
 *
 * This provides the feature to edit the title and description, in contrast to
 * the default menu link form.
 *
 * @see \Drupal\page_manager\Plugin\Menu\PageManagerMenuLink
 */
class PageManagerMenuLinkForm extends MenuLinkDefaultForm {

  /**
   * The edited page menu link.
   *
   * @var \Drupal\page_manager\Plugin\Menu\PageManagerMenuLink
   */
  protected $menuLink;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    // Put the title field first.
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->menuLink->getTitle(),
      '#weight' => -10,
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Shown when hovering over the menu link.'),
      '#default_value' => $this->menuLink->getDescription(),
      '#weight' => -5,
    ];

    $form += parent::buildConfigurationForm($form, $form_state);

    $form['info']['#weight'] = -8;
    $form['path']['#weight'] = -7;

    $page = $this->menuLink->loadPage();
    if ($page && $this->moduleHandler->moduleExists('page_manager_ui')) {
      $url = Url::fromRoute('entity.page.edit_form', ['machine_name' => $page->id()]);
      $message = $this->t('This link is provided by the Page Manager module. The path can be changed by editing the page <a href=":url">@label</a>', [
        ':url' => $url->toString(),
        '@label' => $page->label(),
      ]);
    }
    elseif ($page) {
      $message = $this->t('This link is provided by the Page Manager module from page %label.', ['%label' => $page->label()]);
    }
    else {
      $message = $this->t('This link is provided by the Page Manager module.');
    }
    $form['info']['#title'] = $message;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(array &$form, FormStateInterface $form_state) {
    $definition = parent::extractFormValues($form, $form_state);
    $definition['title'] = $form_state->getValue('title');
    $definition['description'] = $form_state->getValue('description');

    return $definition;
  }

}
