<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\forms_steps\Entity\FormsSteps;
use Drupal\forms_steps\Step;

/**
 * Forms Steps manager service.
 *
 * @package Drupal\forms_steps\Service
 */
class FormsStepsManager {

  /**
   * EntityDisplayRepository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  private EntityDisplayRepository $entityDisplayRepository;

  /**
   * EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * FormsStepsManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepository $entity_display_repository
   *   Injected EntityDisplayRepository instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Injected ConfigFactoryInterface.
   */
  public function __construct(EntityDisplayRepository $entity_display_repository, ConfigFactoryInterface $config_factory) {
    $this->entityDisplayRepository = $entity_display_repository;
    $this->configFactory = $config_factory;
  }

  /**
   * Get the forms_steps next route step.
   *
   * @param mixed $route_name
   *   Current route name.
   *
   * @return null|string
   *   Returns the next route.
   */
  public function getNextStepRoute($route_name) {
    $matches = self::getRouteParameters($route_name);
    if ($matches) {

      /** @var \Drupal\forms_steps\Entity\FormsSteps $formsSteps */
      $formsSteps = FormsSteps::load($matches[1]);
      if (!$formsSteps) {
        return NULL;
      }

      $step = $formsSteps->getStep($matches[2]);

      if (!$step) {
        return NULL;
      }

      return $formsSteps->getNextStepRoute($step);
    }

    return FALSE;
  }

  /**
   * Get the forms_steps next step.
   *
   * @param mixed $route_name
   *   Current route.
   *
   * @return null|\Drupal\forms_steps\Step
   *   Next Step.
   */
  public function getNextStep($route_name): ?Step {
    $matches = self::getRouteParameters($route_name);
    if ($matches) {

      /** @var \Drupal\forms_steps\Entity\FormsSteps $formsSteps */
      $formsSteps = FormsSteps::load($matches[1]);
      if ($formsSteps) {
        /** @var \Drupal\forms_steps\Step $nextStep */
        return $formsSteps->getNextStep($formsSteps->getStep($matches[2]));
      }
    }

    return NULL;
  }

  /**
   * Get the forms_steps previous route step.
   *
   * @param mixed $route_name
   *   Current route name.
   *
   * @return null|string
   *   Returns the previous route.
   */
  public function getPreviousStepRoute($route_name): ?string {
    $matches = self::getRouteParameters($route_name);
    if ($matches) {

      /** @var \Drupal\forms_steps\Entity\FormsSteps $formsSteps */
      $formsSteps = FormsSteps::load($matches[1]);
      if (!$formsSteps) {
        return NULL;
      }

      $step = $formsSteps->getStep($matches[2]);

      if (!$step) {
        return NULL;
      }

      return $formsSteps->getPreviousStepRoute($step);
    }

    return NULL;
  }

  /**
   * Get the forms_steps previous step.
   *
   * @param mixed $route_name
   *   Current route.
   *
   * @return null|\Drupal\forms_steps\Step
   *   Previous Step.
   */
  public function getPreviousStep($route_name): ?Step {
    $matches = self::getRouteParameters($route_name);
    if ($matches) {

      /** @var \Drupal\forms_steps\Entity\FormsSteps $formsSteps */
      $formsSteps = FormsSteps::load($matches[1]);
      if (!$formsSteps) {
        /** @var \Drupal\forms_steps\Step $nextStep */
        return $formsSteps->getPreviousStep($formsSteps->getStep($matches[2]));
      }
    }

    return NULL;
  }

  /**
   * Get the forms_steps entity by route.
   *
   * @param mixed $route_name
   *   Current route.
   *
   * @return null|FormsSteps
   *   Returns the Forms Steps of the route.
   */
  public function getFormsStepsByRoute($route_name): ?FormsSteps {
    $matches = self::getRouteParameters($route_name);
    if ($matches) {

      /** @var \Drupal\forms_steps\Entity\FormsSteps $formsSteps */
      $formsSteps = FormsSteps::load($matches[1]);
      if (!$formsSteps) {
        return NULL;
      }

      return $formsSteps;
    }

    return NULL;
  }

  /**
   * Get the forms_steps step by route.
   *
   * @param mixed $route_name
   *   Current route.
   *
   * @return null|\Drupal\forms_steps\Step
   *   Returns the Step of the route.
   */
  public function getStepByRoute($route_name): ?Step {
    $forms_steps = self::getFormsStepsByRoute($route_name);

    if ($forms_steps) {

      $matches = self::getRouteParameters($route_name);
      if ($matches) {

        return $forms_steps->getStep($matches[2]);

      }
    }

    return NULL;
  }

  /**
   * Returns route parameters.
   *
   * @param string $route_name
   *   Route to get the parameters from.
   *
   * @return array|null
   *   Parameters of the route.
   */
  public function getRouteParameters(string $route_name): ?array {
    // forms_steps routes using the format: forms_steps.forms_steps_id.step_id.
    $route_pattern = '/^forms_steps\.([a-zA-Z0-9_]{1,})\.([a-zA-Z0-9_]{1,})/';

    if (preg_match($route_pattern, $route_name, $matches) == 1) {
      return $matches;
    }
    else {
      return NULL;
    }
  }

  /**
   * Get all form modes per entity type.
   *
   * @return array
   *   Returns a list of form modes defined for all entity types
   *   in forms_steps entities.
   */
  public function getAllFormModesDefinitions(): array {
    // Only managing node at this time. Improvment require.
    $all_form_modes = [];

    // Retrieving all entity types referenced in any forms_steps entity.
    $entityTypes = $this->getAllFormStepsEntityTypes();

    // Gather all form modes for each entity type.
    foreach ($entityTypes as $entityType) {
      $form_modes = $this->entityDisplayRepository->getFormModes($entityType);

      foreach ($form_modes as $key => $value) {
        if (!empty($key) && $value['targetEntityType'] === $entityType) {
          $all_form_modes[$entityType][] = $key;
        }
      }
    }

    return $all_form_modes;
  }

  /**
   * Retrieve all entity types referenced in any existing forms_steps entity.
   */
  public function getAllFormStepsEntityTypes(): array {
    $entityTypes = [];

    $formsStepsConfigs = $this->configFactory->listAll('forms_steps.forms_steps.');

    foreach ($formsStepsConfigs as $formsStepsConfig) {
      $steps = $this->configFactory->get($formsStepsConfig)->get('steps');
      foreach ($steps as $step) {
        $entityTypes[$step['entity_type']] = $step['entity_type'];
      }
    }

    return $entityTypes;
  }

  /**
   * Get the forms_steps entity by id.
   *
   * @param string $name
   *   Name of the forms steps.
   *
   * @return \Drupal\forms_steps\Entity\FormsSteps|null
   *   Returns the Forms Steps.
   */
  public function getFormsStepsById(string $name): ?FormsSteps {
    /** @var \Drupal\forms_steps\Entity\FormsSteps $formsSteps */
    $formsSteps = FormsSteps::load($name);
    if (!$formsSteps) {
      return NULL;
    }

    return $formsSteps;
  }

}
