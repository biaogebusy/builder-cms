<?php

namespace Drupal\commerce_store\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity\Form\EntityDuplicateFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StoreForm extends ContentEntityForm {

  use EntityDuplicateFormTrait;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\commerce_store\Entity\StoreInterface $store */
    $store = $this->entity;

    $form['#theme'] = ['commerce_store_form'];
    $form['#attached']['library'][] = 'commerce_store/form';
    $changed = $store->getChangedTime();
    $form['changed'] = [
      '#type' => 'hidden',
      '#default_value' => $changed,
    ];
    if ($changed) {
      $last_saved = $this->dateFormatter->format($changed, 'short');
    }
    else {
      $last_saved = $store->isNew() ? $this->t('Not saved yet') : $this->t('N/A');
    }

    $form['advanced'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-meta']],
      '#weight' => 99,
    ];
    $form['meta'] = [
      '#attributes' => ['class' => ['entity-meta__header']],
      '#type' => 'container',
      '#group' => 'advanced',
      '#title' => $this->t('Authoring information'),
      '#weight' => 90,
      'published' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $store->isDefault() ? $this->t('Default store') : '',
        '#access' => $store->isDefault(),
        '#attributes' => [
          'class' => ['entity-meta__title'],
        ],
      ],
      'changed' => [
        '#type' => 'item',
        '#wrapper_attributes' => [
          'class' => ['entity-meta__last-saved', 'container-inline'],
        ],
        '#markup' => '<h4 class="label inline">' . $this->t('Last saved') . '</h4> ' . $last_saved,
      ],
      'owner' => [
        '#type' => 'item',
        '#wrapper_attributes' => [
          'class' => ['author', 'container-inline'],
        ],
        '#markup' => '<h4 class="label inline">' . $this->t('Owner') . '</h4> ' . $store->getOwner()->getDisplayName(),
      ],
    ];
    if (isset($form['uid'])) {
      $form['uid']['#group'] = 'author';
    }
    if (isset($form['created'])) {
      $form['created']['#group'] = 'author';
    }
    $form['countries'] = [
      '#type' => 'details',
      '#title' => t('Supported countries'),
      '#weight' => 90,
      '#open' => TRUE,
      '#group' => 'advanced',
    ];
    if (isset($form['billing_countries'])) {
      $form['billing_countries']['widget']['#title'] = $this->t('Billing countries');
      $form['billing_countries']['#group'] = 'countries';
    }
    if (isset($form['shipping_countries'])) {
      $form['shipping_countries']['widget']['#title'] = $this->t('Shipping countries');
      $form['shipping_countries']['#group'] = 'countries';
    }

    $form['path_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('URL path settings'),
      '#open' => !empty($form['path']['widget'][0]['alias']['#default_value']),
      '#group' => 'advanced',
      '#access' => !empty($form['path']['#access']) && $store->get('path')->access('edit'),
      '#attributes' => [
        'class' => ['path-form'],
      ],
      '#attached' => [
        'library' => ['path/drupal.path'],
      ],
      '#weight' => 91,
    ];
    $form['path']['#group'] = 'path_settings';

    if (isset($form['is_default'])) {
      $form['is_default']['#group'] = 'footer';
      $form['is_default']['#disabled'] = $store->isDefault();
      if (!$store->isDefault()) {
        /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
        $store_storage = $this->entityTypeManager->getStorage('commerce_store');
        $default_store = $store_storage->loadDefault();
        if (!$default_store || $default_store->id() == $store->id()) {
          $form['is_default']['widget']['value']['#default_value'] = TRUE;
          $form['is_default']['widget']['value']['#title'] = $this->t('This is the default store.');
          $form['is_default']['#disabled'] = TRUE;
        }
        else {
          $form['is_default']['widget']['value']['#title'] = $this->t('Make this the default store.');
        }
      }
      else {
        $form['is_default']['widget']['value']['#title'] = $this->t('This is the default store.');
      }

      if ($this->moduleHandler->moduleExists('commerce_cart')) {
        $form['is_default']['widget']['value']['#description'] = $this->t('New carts will be assigned to this store unless a contributed module or custom code decides otherwise.');
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->postSave($this->entity, $this->operation);
    $this->messenger()->addMessage($this->t('Saved the %label store.', [
      '%label' => $this->entity->label(),
    ]));
    $form_state->setRedirect('entity.commerce_store.collection');
  }

}
