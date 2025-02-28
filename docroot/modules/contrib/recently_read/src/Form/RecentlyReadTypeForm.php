<?php

namespace Drupal\recently_read\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RecentlyReadTypeForm.
 */
class RecentlyReadTypeForm extends EntityForm {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity_type.bundle.info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * Constructs RecentlyReadTypeForm object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The entity_type.bundle.info service.
   */
  public function __construct(MessengerInterface $messenger, EntityTypeBundleInfoInterface $bundleInfo) {
    $this->messenger = $messenger;
    $this->bundleInfo = $bundleInfo;
  }

  /**
   * RecentlyReadTypeForm create function.
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $container->get('messenger'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $entity = $this->entity;
    $bundles = $this->bundleInfo->getBundleInfo($entity->id());

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity type'),
      '#default_value' => $entity->label(),
      '#disabled' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\recently_read\Entity\RecentlyReadType::load',
      ],
      '#disabled' => TRUE,
    ];

    $options = [];
    foreach ($bundles as $key => $value) {
      $options[$key] = ucfirst($key);
    }

    $form['types'] = [];
    if (count($bundles) > 1) {
      $form['types'] = [
        '#type' => 'checkboxes',
        '#options' => $options,
        '#default_value' => $entity->get('types'),
        '#title' => $this->t('Track'),
        '#required' => FALSE,
        '#prefix' => '<div id="types">',
        '#suffix' => '</div>',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $recently_read_type = $this->entity;
    $status = $recently_read_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger->addMessage($this->t('Created the %label recently read config.', [
          '%label' => $recently_read_type->label(),
        ]));
        break;

      default:
        $this->messenger->addMessage($this->t('Saved the recently read %label config.', [
          '%label' => $recently_read_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($recently_read_type->toUrl('collection'));
  }

}
