<?php

namespace Drupal\content_lock\Form;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form for configuring the Content Lock module.
 *
 * @package Drupal\content_lock\Form
 */
class ContentLockSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'content_lock.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'content_lock_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('content_lock.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('Verbose'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#process' => [[get_class($this), 'formProcessMergeParent']],
      '#weight' => 0,
    ];
    $form['general']['verbose'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this option to display a message to the user when they lock a content item by editing it.'),
      '#description' => $this->t('Users trying to edit a content locked still see the content lock message.'),
      '#default_value' => $config->get('verbose'),
      '#return_value' => 1,
      '#empty' => 0,
    ];

    $form['entities'] = [
      '#type' => 'details',
      '#title' => $this->t('Entity type protected'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#process' => [[get_class($this), 'formProcessMergeParent']],
      '#weight' => 1,
    ];

    $definitions = $this->entityTypeManager->getDefinitions();
    $entity_types = [];
    $selected_entity_types = [];
    foreach ($definitions as $definition) {
      if ($definition instanceof ContentEntityTypeInterface && $this->hasIntegerId($definition)) {
        $entity_types[$definition->id()] = $definition->getLabel();
        if (!empty($config->get('types.' . $definition->id()))) {
          $selected_entity_types[] = $definition->id();
        }
      }
    }
    $form['entities']['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => 'Protected entity types',
      '#options' => $entity_types,
      '#default_value' => $selected_entity_types,
      '#attributes' => [
        'class' => ['content-lock-entity-types'],
      ],
    ];

    foreach ($definitions as $definition) {
      if ($definition instanceof ContentEntityTypeInterface && $this->hasIntegerId($definition)) {
        $form['entities'][$definition->id()] = [
          '#type' => 'container',
          '#title' => $definition->getLabel(),
          '#theme' => 'content_lock_settings_entities',
          '#states' => [
            'visible' => [
              ':input[name="entity_types[' . $definition->id() . ']"]' => ['checked' => TRUE],
            ],
          ],
          '#attributes' => [
            'class' => [$definition->id()],
          ],
        ];

        $options = [
          '*' => $this->t('All'),
        ];

        if ($definition->getBundleEntityType()) {
          $bundles = $this->entityTypeManager
            ->getStorage($definition->getBundleEntityType())
            ->loadMultiple();

          foreach ($bundles as $bundle) {
            $options[$bundle->id()] = $bundle->label();
          }
        }
        else {
          $options[$definition->id()] = $definition->getLabel();
        }
        $form['entities'][$definition->id()]['bundles'] = [
          '#type' => 'checkboxes',
          '#title' => $definition->getBundleLabel() ?: $definition->getLabel(),
          '#description' => $this->t('Select the bundles on which enable content lock'),
          '#options' => $options,
          '#default_value' => $config->get('types.' . $definition->id()) ?: [],
          '#attributes' => ['class' => ['content-lock-entity-settings']],
        ];

        $form['entities'][$definition->id()]['settings']['translation_lock'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Lock only on entity translation level.'),
          '#default_value' => in_array($definition->id(), $config->get('types_translation_lock') ?: []),
          '#description' => $this->t('Activating this options allows users to edit multiple translations concurrently'),
        ];
        if (!$this->moduleHandler->moduleExists('conflict')) {
          $form['entities'][$definition->id()]['settings']['translation_lock'] = [
            '#disabled' => TRUE,
            '#default_value' => FALSE,
            '#description' => $this->t('To allow editing multiple translations concurrently you need to install %module',
              [
                '%module' => Link::fromTextAndUrl('Conflict', Url::fromUri('https://www.drupal.org/project/conflict'))->toString(),
              ]
            ),
          ] + $form['entities'][$definition->id()]['settings']['translation_lock'];
        }

        if (!empty($definition->getHandlerClasses()['form'])) {
          $form['entities'][$definition->id()]['settings']['form_op_lock'] = [
            '#tree' => 1,
          ];
          $form['entities'][$definition->id()]['settings']['form_op_lock']['mode'] = [
            '#type' => 'radios',
            '#title' => $this->t('Lock only on entity form operation level.'),
            '#options' => [
              ContentLockInterface::FORM_OP_MODE_DISABLED => $this->t('Disabled'),
              ContentLockInterface::FORM_OP_MODE_ALLOWLIST => $this->t('Enable lock for selected form operations'),
              ContentLockInterface::FORM_OP_MODE_DENYLIST => $this->t('Disable lock for selected form operations'),
            ],
            '#default_value' => $config->get('form_op_lock.' . $definition->id() . '.mode') ?: ContentLockInterface::FORM_OP_MODE_DISABLED,
            '#description' => $this->t('Activating this options allows users to edit different entity forms concurrently'),
          ];

          $form_ops = array_keys($definition->getHandlerClasses()['form']);
          $form_ops = array_combine($form_ops, $form_ops);
          $form['entities'][$definition->id()]['settings']['form_op_lock']['values'] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('Form operations'),
            '#options' => $form_ops,
            '#default_value' => (array) $config->get('form_op_lock.' . $definition->id() . '.values'),
            '#states' => [
              'invisible' => [
                ':input[name="' . $definition->id() . '[settings][form_op_lock][mode]"]' => ['value' => ContentLockInterface::FORM_OP_MODE_DISABLED],
              ],
            ],
          ];
        }
      }
    }

    $form['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Lock timeout'),
      '#description' => $this->t('The maximum time in minutes that each lock may be kept. To disable breaking locks after a timeout, clear the value. It is recommended for user experience to set this value.'),
      '#min' => 1,
      '#config_target' => new ConfigTarget('content_lock.settings', 'timeout', fn($value) => empty($value) ? '' : $value / 60, fn($value) => empty($value) ? NULL : $value * 60),
    ];

    $form['#attached']['library'][] = 'content_lock/drupal.content_lock.settings';
    return parent::buildForm($form, $form_state);
  }

  /**
   * Checks if this entity type has an integer ID.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return bool|null
   *   Returns:
   *     - TRUE if the entity type ID is defined as integer;
   *     - FALSE if the entity type ID is defined as string;
   *     - NULL if the entity type lacks an ID key.
   */
  private function hasIntegerId(EntityTypeInterface $entityType): ?bool {
    if (method_exists($entityType, 'hasIntegerId')) {
      return $entityType->hasIntegerId();
    }
    // Copy of code from Drupal core 11.4.x. Remove once the minimum supported
    // version is greater or equal to 11.4.0.
    if ($entityType->hasKey('id') && $entityType->entityClassImplements(FieldableEntityInterface::class)) {
      // @phpcs:ignore
      $definitions = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions($entityType->id());
      return $definitions[$entityType->getKey('id')]->getType() === 'integer';
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $content_lock = $this->config('content_lock.settings');
    // Clear the values before rebuilding the config from the form.
    $content_lock
      ->set('types', [])
      ->set('types_translation_lock', [])
      ->set('form_op_lock', []);

    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      if ($form_state->getValue($definition->id())) {
        $config_from_form = $this->removeEmptyValue($form_state->getValue([$definition->id(), 'bundles']));
        if (empty($config_from_form)) {
          continue;
        }
        $content_lock->set('types.' . $definition->id(), $config_from_form);

        $types_translation_lock = $content_lock->get('types_translation_lock');
        if ($form_state->getValue([$definition->id(), 'settings', 'translation_lock'])) {
          $types_translation_lock[] = $definition->id();
        }
        $content_lock->set('types_translation_lock', $types_translation_lock);

        $content_lock->set('form_op_lock.' . $definition->id() . '.mode', $form_state->getValue([$definition->id(), 'settings', 'form_op_lock', 'mode']));
        $content_lock->set('form_op_lock.' . $definition->id() . '.values', $this->removeEmptyValue((array) $form_state->getValue([$definition->id(), 'settings', 'form_op_lock', 'values'])));
      }
    }

    $content_lock
      ->set('verbose', $form_state->getValue('verbose'))
      ->save();
  }

  /**
   * Helper function to filter empty value in an array.
   *
   * @param array $array
   *   The array to check for empty values.
   *
   * @return array
   *   The array without empty values.
   */
  protected function removeEmptyValue(array $array): array {
    return array_filter($array, function ($value) {
      return !empty($value);
    });
  }

  /**
   * Merge elements to the level up.
   *
   * Render API callback: Moves entity_reference specific Form API elements
   * (i.e. 'handler_settings') up a level for easier processing values.
   *
   * @param array $element
   *   The array to filter.
   *
   * @return array
   *   The array filtered.
   *
   * @see _entity_reference_field_settings_process()
   */
  public static function formProcessMergeParent(array $element): array {
    $parents = $element['#parents'];
    array_pop($parents);
    $element['#parents'] = $parents;
    return $element;
  }

}
