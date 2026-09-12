<?php

namespace Drupal\tour\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Drupal\tour\TipPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the tour entity tips forms.
 */
class TourTipsListForm extends EntityForm {

  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('language_manager'),
      $container->get('plugin.manager.tour.tip')
    );
  }

  /**
   * Constructs a TourForm object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The Language Manager service.
   * @param \Drupal\tour\TipPluginManager $tipPluginManager
   *   The Tip Plugin Manager service.
   */
  public function __construct(
    protected LanguageManagerInterface $languageManager,
    protected TipPluginManager $tipPluginManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    if ($this->operation == 'edit_tips') {
      $form['#title'] = $this->t('Edit <em>%label</em> tour tips', ['%label' => $this->entity->label()]);
    }

    $tour = $this->entity;
    $form = parent::form($form, $form_state);

    // Start building the list of tips assigned to this tour.
    $form['tips'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Label'),
        $this->t('Type'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#caption' => [['#markup' => $this->t('Tips provided by this tour. By clicking on the operations buttons, every change that has not been saved will be lost.')]],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'tip-order-weight',
        ],
      ],
      '#weight' => 40,
    ];

    // Populate the table with the assigned tips.
    $tips = $tour->getTips();
    if (!empty($tips)) {
      foreach ($tips as $tip) {
        $tip_id = $tip->get('id');
        try {
          $form['#data'][$tip_id] = $tip->getConfiguration();
        }
        catch (\Error $e) {
          $this->messenger->addMessage($this->t('Tip %tip is not configurable. You cannot save this tour.', ['%tip' => $tip->getLabel()]), 'warning');
        }
        $form['tips'][$tip_id]['#attributes']['class'][] = 'draggable';
        $form['tips'][$tip_id]['label'] = [
          '#plain_text' => $tip->get('label'),
        ];

        $tip_definition = $this->tipPluginManager->getDefinition($tip->getPluginId());
        $form['tips'][$tip_id]['plugin_id'] = [
          '#plain_text' => $tip_definition['title'] ?? $tip->getPluginId(),
        ];

        $form['tips'][$tip_id]['weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $tip->get('label')]),
          '#delta' => 100,
          '#title_display' => 'invisible',
          '#default_value' => $tip->get('weight'),
          '#attributes' => [
            'class' => ['tip-order-weight'],
          ],
        ];

        // Provide operations links for the tip.
        $links = [];
        if (method_exists($tip, 'buildConfigurationForm')) {
          $links['edit'] = [
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('tour.tip.edit', [
              'tour' => $tour->id(),
              'tip' => $tip_id,
            ]),
          ];
        }
        $links['delete'] = [
          'title' => $this->t('Delete'),
          'url' => Url::fromRoute('tour.tip.delete', [
            'tour' => $tour->id(),
            'tip' => $tip_id,
          ]),
        ];
        $form['tips'][$tip_id]['operations'] = [
          '#type' => 'operations',
          '#links' => $links,
        ];
      }
    }

    // Build the new tour tip addition form and add it to the tips list.
    $tip_definitions = $this->tipPluginManager->getDefinitions();
    $tip_definition_options = [];
    foreach ($tip_definitions as $tip => $definition) {
      if (method_exists($definition['class'], 'buildConfigurationForm')) {
        $tip_definition_options[$tip] = $definition['title'];
      }
    }

    $user_input = $form_state->getUserInput();
    $form['tips']['new'] = [
      '#tree' => FALSE,
      '#weight' => $user_input['weight'] ?? 0,
      '#attributes' => [
        'class' => ['draggable'],
      ],
    ];
    $form['tips']['new']['new'] = [
      '#type' => 'select',
      '#title' => $this->t('Tip'),
      '#title_display' => 'invisible',
      '#options' => $tip_definition_options,
      '#empty_option' => $this->t('Select a new tip'),
    ];
    $form['tips']['new']['plugin_id'] = [];
    $form['tips']['new']['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight for new tip'),
      '#title_display' => 'invisible',
      '#default_value' => count($form['tips']) - 1,
      '#attributes' => [
        'class' => ['tip-order-weight'],
      ],
    ];
    $form['tips']['new']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#validate' => [[$this, 'tipValidate']],
      '#submit' => [[$this, 'tipAdd']],
    ];

    return $form;
  }

  /**
   * Validate handler.
   */
  public function tipValidate($form, FormStateInterface $form_state): void {
    if (!$form_state->getValue('new')) {
      $form_state->setError($form['tips']['new']['new'], $this->t('Select a new tip.'));
    }
  }

  /**
   * Submit handler.
   */
  public function tipAdd($form, FormStateInterface $form_state): void {
    $tour = $this->getEntity();

    $weight = 0;
    if (!$form_state->isValueEmpty('tips')) {
      // Get last weight.
      foreach ($form_state->getValue('tips') as $tip) {
        if ($tip['weight'] > $weight) {
          $weight = $tip['weight'] + 1;
        }
      }
    }

    $stub = $this->tipPluginManager->createInstance($form_state->getValue('new'));

    // If a form is available for this tip then redirect to an add page.
    $stub_form = $stub->buildConfigurationForm([], new FormState());
    if (isset($stub_form)) {
      // Redirect to the appropriate page to add this new tip.
      $form_state->setRedirect('tour.tip.add', [
        'tour' => $tour->id(),
        'type' => $form_state->getValue('new'),
      ], ['query' => ['weight' => $weight]]);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Form cannot be validated if a tip has no #data, so no way to export
    // configuration.
    if (!$form_state->isValueEmpty('tips')) {
      foreach ($form_state->getValue('tips') as $key => $values) {
        if (!isset($form['#data'][$key])) {
          $form_state->setError($form['tips'][$key], $this->t('You cannot save the tour while %tip tip cannot be exported.', ['%tip' => $this->getEntity()->getTip($key)->getLabel()]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Merge the form values in with the current configuration.
    if (!$form_state->isValueEmpty('tips')) {
      $tips = [];
      foreach ($form_state->getValue('tips') as $key => $values) {
        $data = $form['#data'][$key];
        $tips[$key] = array_merge($data, $values);
      }
      $form_state->setValue('tips', $tips);
    }
    else {
      $form_state->setValue('tips', []);
    }

    $this->messenger()->addMessage($this->t('Tip settings have been saved.'));

    parent::submitForm($form, $form_state);

    // Redirect to Entity edition.
    $form_state->setRedirect('entity.tour.edit_form_tips', ['tour' => $this->entity->id()]);
  }

}
