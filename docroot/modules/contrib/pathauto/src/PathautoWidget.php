<?php

namespace Drupal\pathauto;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\path\Plugin\Field\FieldWidget\PathWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends the core path widget.
 */
class PathautoWidget extends PathWidget {

  /**
   * The path auto generator service.
   *
   * @var \Drupal\pathauto\PathautoGeneratorInterface
   */
  protected PathautoGeneratorInterface $pathautoGenerator;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a new instance of the class.
   *
   * @param string $plugin_id
   *   The plugin ID for the field widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $settings
   *   An array of settings.
   * @param array $third_party_settings
   *   An array of third-party settings.
   * @param \Drupal\pathauto\PathautoGeneratorInterface|null $pathauto_generator
   *   The Pathauto generator service.
   * @param \Drupal\Core\Session\AccountProxyInterface|null $current_user
   *   The current user service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ?PathautoGeneratorInterface $pathauto_generator = NULL, ?AccountProxyInterface $current_user = NULL) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
    $this->pathautoGenerator = $pathauto_generator ?: \Drupal::service('pathauto.generator');
    // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
    $this->currentUser = $current_user ?: \Drupal::currentUser();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('pathauto.generator'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $entity = $items->getEntity();

    $pattern = $this->pathautoGenerator->getPatternByEntity($entity);
    if (empty($pattern)) {
      // Explicitly turn off pathauto here.
      $element['pathauto'] = [
        '#type' => 'value',
        '#value' => PathautoState::SKIP,
      ];
      return $element;
    }

    if ($this->currentUser->hasPermission('administer pathauto')) {
      $description = $this->t('Uncheck this to create a custom alias below. <a href="@admin_link">Configure URL alias patterns.</a>', ['@admin_link' => Url::fromRoute('entity.pathauto_pattern.collection')->toString()]);
    }
    else {
      $description = $this->t('Uncheck this to create a custom alias below.');
    }

    $element['pathauto'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate automatic URL alias'),
      '#default_value' => $entity->path->pathauto,
      '#description' => $description,
      '#weight' => -1,
    ];

    // Add JavaScript that will disable the path textfield when the automatic
    // alias checkbox is checked.
    $element['alias']['#states']['disabled']['input[name="path[' . $delta . '][pathauto]"]'] = ['checked' => TRUE];

    // Override path.module's vertical tabs summary.
    $element['alias']['#attached']['library'] = ['pathauto/widget'];

    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public static function validateFormElement(array &$element, FormStateInterface $form_state) {
    // Skip alias validation when pathauto will generate the alias on save.
    // The parent validation checks alias uniqueness against the current value,
    // but pathauto will regenerate and uniquify the alias during entity save,
    // making this validation both unnecessary and prone to false positives
    // (e.g. when translating content, the source language's alias and langcode
    // are carried over, triggering "alias already in use" for the wrong
    // language).
    // @see https://www.drupal.org/i/3267989
    if (!empty($element['pathauto']['#value'])) {
      return;
    }

    parent::validateFormElement($element, $form_state);
  }

}
