<?php

namespace Drupal\bootstrap_layout_builder\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LayoutForm.
 */
class LayoutForm extends EntityForm implements ContainerInjectionInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a BootstrapLayoutBuilderBreakpointsForm object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\bootstrap_layout_builder\LayoutInterface $layout */
    $layout = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $layout->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $layout->id(),
      '#machine_name' => [
        'exists' => '\Drupal\bootstrap_layout_builder\Entity\Layout::load',
      ],
      '#disabled' => !$layout->isNew(),
    ];

    $form['number_of_columns'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number of columns'),
      '#description' => $this->t('The number of columns (Regions) at this layout.'),
      '#default_value' => $layout->getNumberOfColumns(),
      '#maxlength' => 3,
      '#required' => TRUE,
      '#disabled' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!is_numeric($form_state->getValue('number_of_columns'))) {
      $form_state->setErrorByName(
        'number_of_columns',
        $this->t('Number of columns must be a number!')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $layout = $this->entity;
    // $layout->setNumberOfColumns($form->getValue('number_of_columns'));
    $save_operation = $layout->save();

    switch ($save_operation) {
      case SAVED_NEW:
        $this->messenger->addStatus($this->t('Created the %label layout.', [
          '%label' => $layout->label(),
        ]));
        break;

      default:
        $this->messenger->addStatus($this->t('Saved the %label layout.', [
          '%label' => $layout->label(),
        ]));
    }
    $form_state->setRedirectUrl($layout->toUrl('collection'));
  }

}
