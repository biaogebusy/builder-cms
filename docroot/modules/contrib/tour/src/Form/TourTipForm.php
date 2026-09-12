<?php

namespace Drupal\tour\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\tour\TipPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the tour tip plugin edit forms.
 */
class TourTipForm extends FormBase {

  use MessengerTrait;

  /**
   * Constructs a new TourTipForm object.
   *
   * @param \Drupal\Tour\TipPluginManager $pluginManager
   *   The Tour tip plugin manager.
   */
  public function __construct(protected TipPluginManager $pluginManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.tour.tip')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'tour_tip_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $tip = $storage['#tip'];

    $form += $tip->buildConfigurationForm($form, $form_state);

    // Retrieve and add the form actions array.
    $actions = $this->actionsElement($form, $form_state);
    if (!empty($actions)) {
      $form['actions'] = $actions;
    }

    return $form;
  }

  /**
   * Returns the action form element for the current entity form.
   */
  protected function actionsElement(array $form, FormStateInterface $form_state): array {
    $element = $this->actions($form, $form_state);

    if (isset($element['delete'])) {
      // Move the delete action as last one, unless weights are explicitly
      // provided.
      $delete = $element['delete'];
      unset($element['delete']);
      $element['delete'] = $delete;
      $element['delete']['#button_type'] = 'danger';
    }

    if (isset($element['submit'])) {
      // Give the primary submit button a #button_type of primary.
      $element['submit']['#button_type'] = 'primary';
    }

    $count = 0;
    foreach (Element::children($element) as $action) {
      $element[$action] += [
        '#weight' => ++$count * 5,
      ];
    }

    if (!empty($element)) {
      $element['#type'] = 'actions';
    }

    return $element;
  }

  /**
   * Returns an array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => ['::submitForm'],
    ];

    $storage = $form_state->getStorage();
    if (($storage['#tour']->id() !== NULL && $storage['#tour']->id() !== "") && ($storage['#tip']->id() !== NULL && $storage['#tip']->id() !== "")) {
      $actions['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#url' => Url::fromRoute('tour.tip.delete', [
          'tour' => $storage['#tour']->id(),
          'tip' => $storage['#tip']->id(),
        ]),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
        ],
      ];
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Determine if one of our tips already exist.
    $storage = $form_state->getStorage();
    $tour = $storage['#tour'];
    $tips = $tour->getTips();
    // If there are no initial tips then we don't need to check.
    if (empty($tips)) {
      return;
    }

    $tip_ids = array_map(function ($data) {
      return $data->id();
    }, $tips);

    if (in_array($form_state->getValue('id'), $tip_ids) && isset($storage['#new'])) {
      $form_state->setError($form['label'], $this->t('A tip with the same identifier exists.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $tour = $storage['#tour'];
    $tip = $storage['#tip'];
    // Get available fields from current tip plugin.
    $configuration = $tip->getConfiguration();

    // Build a new tip.
    $new_tip = $tip->getConfiguration();
    foreach ($configuration as $name => $configuration_value) {
      $value = $form_state->getValue($name);
      $new_tip[$name] = is_array($value) ? array_filter($value) : $value;
    }

    // Rebuild the tips.
    $new_tip_list = $tour->getTips();
    $new_tips = [];
    if (!empty($new_tip_list)) {
      foreach ($new_tip_list as $tip) {
        $new_tips[$tip->id()] = $tip->getConfiguration();
      }
    }

    // Add our tip and save.
    $new_tips[$new_tip['id']] = $new_tip;
    $tour->set('tips', $new_tips);
    $tour->save();

    if (isset($storage['#new'])) {
      $this->messenger()->addMessage($this->t('The %tip tip has been created.', ['%tip' => $new_tip['label']]));
    }
    else {
      $this->messenger()->addMessage($this->t('Updated the %tip tip.', ['%tip' => $new_tip['label']]));
    }

    $form_state->setRedirect('entity.tour.edit_form_tips', ['tour' => $tour->id()]);
    return $tour;
  }

}
