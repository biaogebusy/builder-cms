<?php

namespace Drupal\bootstrap_layout_builder\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BreakpointForm.
 */
class BreakpointForm extends EntityForm implements ContainerInjectionInterface {

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

    /** @var \Drupal\bootstrap_layout_builder\BreakpointInterface $breakpoint */
    $breakpoint = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $breakpoint->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $breakpoint->id(),
      '#machine_name' => [
        'exists' => '\Drupal\bootstrap_layout_builder\Entity\Breakpoint::load',
      ],
      '#disabled' => !$breakpoint->isNew(),
    ];

    $form['base_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base class'),
      '#maxlength' => 255,
      '#default_value' => $breakpoint->getBaseClass(),
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#title' => $this->t('Enabled'),
      '#type' => 'checkbox',
      '#default_value' => $breakpoint->getStatus(),
      '#description' => $this->t('Determines if this breakpoint enabled.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $breakpoint = $this->entity;
    $save_operation = $breakpoint->save();

    switch ($save_operation) {
      case SAVED_NEW:
        $this->messenger->addStatus($this->t('Created the %label breakpoint.', [
          '%label' => $breakpoint->label(),
        ]));
        break;

      default:
        $this->messenger->addStatus($this->t('Saved the %label breakpoint.', [
          '%label' => $breakpoint->label(),
        ]));
    }
    $form_state->setRedirectUrl($breakpoint->toUrl('collection'));
  }

}
