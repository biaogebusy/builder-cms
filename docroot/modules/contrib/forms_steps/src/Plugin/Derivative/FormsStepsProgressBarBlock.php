<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for progress bars.
 *
 * @see \Drupal\forms_steps\Plugin\Block\FormsStepsProgressBarBlock
 */
class FormsStepsProgressBarBlock extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The forms steps storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $formStepsStorage;

  /**
   * Constructs new forms steps progress bar block.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $forms_steps_storage
   *   The forms steps storage.
   */
  public function __construct(EntityStorageInterface $forms_steps_storage) {
    $this->formStepsStorage = $forms_steps_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')->getStorage('forms_steps')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    // Load all available forms steps.
    $forms_steps_entities = $this->formStepsStorage->loadMultiple();

    /** @var \Drupal\forms_steps\Entity\FormsSteps $forms_steps */
    foreach ($forms_steps_entities as $forms_steps) {
      $progress_steps = $forms_steps->getProgressSteps();
      if (count($progress_steps)) {
        $this->derivatives[$forms_steps->id()] = $base_plugin_definition;
        $this->derivatives[$forms_steps->id()]['admin_label'] = t('Forms Steps - :title (Progress bar)', [':title' => $forms_steps->label()]);
      }
    }

    return $this->derivatives;
  }

}
