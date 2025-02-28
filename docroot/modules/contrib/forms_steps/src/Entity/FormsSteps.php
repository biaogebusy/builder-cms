<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\forms_steps\FormsStepsInterface;
use Drupal\forms_steps\ProgressStepInterface;
use Drupal\forms_steps\Step;
use Drupal\forms_steps\ProgressStep;
use Drupal\Core\Url;
use Drupal\forms_steps\StepInterface;

/**
 * FormsSteps configuration entity to persistently store configuration.
 *
 * @ConfigEntityType(
 *   id = "forms_steps",
 *   label = @Translation("FormsSteps"),
 *   handlers = {
 *     "list_builder" = "Drupal\forms_steps\Builder\FormsStepsListBuilder",
 *     "form" = {
 *        "add" = "\Drupal\forms_steps\Form\FormsStepsAddForm",
 *        "edit" = "\Drupal\forms_steps\Form\FormsStepsEditForm",
 *        "delete" = "\Drupal\Core\Entity\EntityDeleteForm",
 *        "add-step" = "\Drupal\forms_steps\Form\FormsStepsStepAddForm",
 *        "edit-step" = "\Drupal\forms_steps\Form\FormsStepsStepEditForm",
 *        "delete-step" = "\Drupal\Core\Entity\EntityDeleteForm",
 *        "add-progress-step" = "\Drupal\forms_steps\Form\FormsStepsProgressStepAddForm",
 *        "edit-progress-step" = "\Drupal\forms_steps\Form\FormsStepsProgressStepEditForm",
 *        "delete-progress-step" = "\Drupal\Core\Entity\EntityDeleteForm",
 *      }
 *   },
 *   admin_permission = "administer forms_steps",
 *   config_prefix = "forms_steps",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "progress_steps_links_saved_only",
 *     "progress_steps_links_saved_only_next",
 *     "redirection_policy",
 *     "redirection_target",
 *     "steps",
 *     "progress_steps",
 *   },
 *   links = {
 *     "collection" = "/admin/config/workflow/forms_steps",
 *     "edit-form" = "/admin/config/workflow/forms_steps/edit/{forms_steps}",
 *     "delete-form" =
 *   "/admin/config/workflow/forms_steps/delete/{forms_steps}",
 *     "add-form" = "/admin/config/workflow/forms_steps/add",
 *     "add-step-form" =
 *   "/admin/config/workflow/forms_steps/{forms_steps}/add_step",
 *     "add-progress-step-form" =
 *   "/admin/config/workflow/forms_steps/{forms_steps}/add_progress_step",
 *   }
 * )
 */
class FormsSteps extends ConfigEntityBase implements FormsStepsInterface {

  /**
 * Entity type id. */
  const ENTITY_TYPE = 'forms_steps';

  /**
   * The unique ID of the Forms Steps.
   *
   * @var string
   */
  public string $id = '';

  /**
   * The label of the FormsSteps.
   *
   * @var string
   */
  protected string $label;

  /**
   * The description of the FormsSteps, which is used only in the interface.
   *
   * @var string
   */
  protected string $description = '';

  /**
   * The progress_steps_links_saved_only setting of the FormsSteps.
   *
   * @var string
   */
  protected string $progress_steps_links_saved_only = '';


  /**
   * The progress_steps_links_saved_only_next setting of the FormsSteps.
   *
   * @var string
   */
  protected string $progress_steps_links_saved_only_next = '';

  /**
   * The redirection policy of the FormsSteps.
   *
   * @var string
   */
  protected string $redirection_policy = '';

  /**
   * The redirection target of the FormsSteps.
   *
   * @var string
   */
  protected string $redirection_target = '';

  /**
   * The ordered FormsSteps steps.
   *
   * Steps array. The array is numerically indexed by the step id and contains
   * arrays with the following structure:
   *   - weight: weight of the step
   *   - label: label of the step
   *   - form_id: form id of the step
   *   - form_mode: form mode of the form of the step
   *   - url: url of the step.
   *
   * @var array
   */
  protected array $steps = [];

  /**
   * The ordered FormsSteps progress steps.
   *
   * Progress steps array. The array is numerically indexed by the progress step
   * id and contains arrays with the following structure:
   *   - weight: weight of the progress step
   *   - label: label of the progress step
   *   - form_id: form id of the progress step
   *   - routes: an array of the routes for which the progress step is active
   *   - link: the link of the progress step.
   *
   * @var array
   */
  protected array $progress_steps = [];

  /**
   * Returns the description.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Returns the Progress steps links saved only setting.
   */
  public function getProgressStepsLinksSavedOnly(): string {
    return $this->progress_steps_links_saved_only;
  }

  /**
   * Returns the Progress steps links saved only next setting.
   */
  public function getProgressStepsLinksSavedOnlyNext(): string {
    return $this->progress_steps_links_saved_only_next;
  }

  /**
   * Returns the redirection policy.
   */
  public function getRedirectionPolicy(): string {
    return $this->redirection_policy;
  }

  /**
   * Returns the redirection target.
   */
  public function getRedirectionTarget(): string {
    return $this->redirection_target;
  }

  /**
   * {@inheritdoc}
   */
  public function addStep(string $step_id, string $label, string $entityType, string $entityBundle, string $formMode, string $url): FormsStepsInterface {
    if (isset($this->Steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' already exists in the forms steps '{$this->id()}'"
      );
    }
    if (preg_match('/[^a-z0-9_]+/', $step_id)) {
      throw new \InvalidArgumentException(
        "The Step ID '$step_id' must contain only lowercase letters, numbers, and underscores"
      );
    }
    $this->steps[$step_id] = [
      'label' => $label,
      'weight' => $this->getNextWeight($this->steps),
      'entity_type' => $entityType,
      'entity_bundle' => $entityBundle,
      'form_mode' => $formMode,
      'url' => $url,
    ];
    ksort($this->steps);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addProgressStep(string $progress_step_id, string $label, array $routes, string $link, array $link_visibility):FormsStepsInterface {
    if (isset($this->progress_steps[$progress_step_id])) {
      throw new \InvalidArgumentException(
        "The Progress Step '$progress_step_id' already exists in the forms steps '{$this->id()}'"
      );
    }
    if (preg_match('/[^a-z0-9_]+/', $progress_step_id)) {
      throw new \InvalidArgumentException(
        "The Progress Step ID '$progress_step_id' must contain only lowercase letters, numbers, and underscores"
      );
    }
    $this->progress_steps[$progress_step_id] = [
      'label' => $label,
      'weight' => $this->getNextWeight($this->progress_steps),
      'routes' => $routes,
      'link' => $link,
      'link_visibility' => $link_visibility,
    ];
    ksort($this->progress_steps);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasStep(string $step_id): bool {
    return isset($this->steps[$step_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function hasProgressStep(string $progress_step_id): bool {
    return isset($this->progress_steps[$progress_step_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getNextStep(Step $step): ?Step {
    $nextStep = NULL;

    foreach ($this->getSteps() as $current_step) {
      if (is_null($nextStep)) {
        $nextStep = $current_step;
      }
      else {
        if ($nextStep->weight() < $current_step->weight()) {
          $nextStep = $current_step;

          if ($nextStep->weight() > $step->weight()) {
            break;
          }
        }
      }
    }

    if (is_null($nextStep)) {
      return NULL;
    }
    else {
      return $nextStep;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousStep(Step $step): ?Step {
    $previousStep = NULL;

    // Reverse the order of the array.
    $stepsReversed = array_reverse($this->getSteps());
    $stepsIterator = new \ArrayIterator($stepsReversed);

    while ($stepsIterator->valid()) {
      if (strcmp($stepsIterator->current()->id(), $step->id()) == 0) {
        $stepsIterator->next();
        $previousStep = $stepsIterator->current();

        break;
      }
      else {
        $stepsIterator->next();
      }
    }

    return $previousStep;
  }

  /**
   * {@inheritdoc}
   */
  public function getStepRoute(Step $step): ?string {
    return 'forms_steps.' . $this->id . '.' . $step->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getNextStepRoute(Step $step): ?string {
    $nextRoute = NULL;

    $nextStep = $this->getNextStep($step);

    if ($nextStep) {
      $nextRoute = 'forms_steps.' . $this->id . '.' . $nextStep->id();
    }

    return $nextRoute;
  }

  /**
   * Returns the previous step route.
   *
   * @param \Drupal\forms_steps\Step $step
   *   Current Step.
   *
   * @return null|string
   *   Returns the previous route.
   */
  public function getPreviousStepRoute(Step $step): ?string {
    $previousRoute = NULL;

    $previousStep = $this->getPreviousStep($step);

    if ($previousStep) {
      $previousRoute = 'forms_steps.' . $this->id . '.' . $previousStep->id();
    }

    return $previousRoute;
  }

  /**
   * {@inheritdoc}
   */
  public function getSteps(array $step_ids = NULL): array {
    if ($step_ids === NULL) {
      $step_ids = array_keys($this->steps);
    }
    /** @var \Drupal\forms_steps\StepInterface[] $steps */
    $steps = array_combine($step_ids, array_map([$this, 'getStep'], $step_ids));
    $this->sortElements($steps);
    return $steps;
  }

  /**
   * {@inheritdoc}
   */
  public function getProgressSteps(array $progress_step_ids = NULL): array {
    if ($progress_step_ids === NULL) {
      $progress_step_ids = array_keys($this->progress_steps);
    }

    /** @var \Drupal\forms_steps\ProgressStepInterface[] $progress_steps */
    $progress_steps = array_combine(
      $progress_step_ids,
      array_map([$this, 'getProgressStep'],
        $progress_step_ids)
    );
    $this->sortElements($progress_steps);
    return $progress_steps;
  }

  /**
   * Sort steps or progress steps elements by weight and label.
   *
   * @param array $elements
   *   The steps or progress steps elements.
   */
  protected function sortElements(array &$elements) {
    if (count($elements) > 1) {
      // Sort Steps by weight and then label.
      $weights = $labels = [];
      foreach ($elements as $id => $element) {
        $weights[$id] = $element->weight();
        $labels[$id] = $element->label();
      }
      array_multisort(
        $weights, SORT_NUMERIC, SORT_ASC,
        $labels, SORT_NATURAL, SORT_ASC
      );
      $elements = array_replace($weights, $elements);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstStep(string $steps = NULL): StepInterface {
    if ($steps === NULL) {
      $steps = $this->getSteps();
    }
    return reset($steps);

  }

  /**
   * {@inheritdoc}
   */
  public function getLastStep(string $steps = NULL): StepInterface {
    if ($steps === NULL) {
      $steps = $this->getSteps();
    }
    return end($steps);

  }

  /**
   * {@inheritdoc}
   */
  public function getStep(string $step_id): StepInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $step = new Step(
      $this,
      $step_id,
      $this->steps[$step_id]['label'],
      $this->steps[$step_id]['weight'],
      $this->steps[$step_id]['entity_type'],
      $this->steps[$step_id]['entity_bundle'],
      $this->steps[$step_id]['form_mode'],
      $this->steps[$step_id]['url']
    );

    if (isset($this->steps[$step_id]['cancelStepMode'])) {
      $step->setCancelStepMode($this->steps[$step_id]['cancelStepMode']);
    }
    if (isset($this->steps[$step_id]['cancelRoute'])) {
      $step->setCancelRoute($this->steps[$step_id]['cancelRoute']);
    }
    if (isset($this->steps[$step_id]['submitLabel'])) {
      $step->setSubmitLabel($this->steps[$step_id]['submitLabel']);
    }
    if (isset($this->steps[$step_id]['cancelLabel'])) {
      $step->setCancelLabel($this->steps[$step_id]['cancelLabel']);
    }
    if (isset($this->steps[$step_id]['cancelStep'])) {
      $step->setCancelStep($this->getStep($this->steps[$step_id]['cancelStep']));
    }
    if (isset($this->steps[$step_id]['hideDelete'])) {
      $step->setHideDelete($this->steps[$step_id]['hideDelete']);
    }
    if (isset($this->steps[$step_id]['deleteLabel']) &&
      (!isset($this->steps[$step_id]['hideDelete']) || !$this->steps[$step_id]['hideDelete'])
    ) {
      $step->setDeleteLabel($this->steps[$step_id]['deleteLabel']);
    }
    if (isset($this->steps[$step_id]['displayPrevious'])) {
      $step->setDisplayPrevious($this->steps[$step_id]['displayPrevious']);
    }
    if (isset($this->steps[$step_id]['previousLabel'])) {
      $step->setPreviousLabel($this->steps[$step_id]['previousLabel']);
    }

    return $step;
  }

  /**
   * {@inheritdoc}
   */
  public function getProgressStep(string $progress_step_id): ProgressStepInterface {
    if (!isset($this->progress_steps[$progress_step_id])) {
      throw new \InvalidArgumentException(
        "The progress step '$progress_step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    return new ProgressStep(
      $this,
      $progress_step_id,
      $this->progress_steps[$progress_step_id]['label'],
      $this->progress_steps[$progress_step_id]['weight'],
      $this->progress_steps[$progress_step_id]['routes'] ?? [],
      $this->progress_steps[$progress_step_id]['link'] ?? '',
      $this->progress_steps[$progress_step_id]['link_visibility'] ?? []
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setStepLabel(string $step_id, string $label): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['label'] = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setProgressStepLabel(string $progress_step_id, string $label): FormsStepsInterface {
    if (!isset($this->progress_steps[$progress_step_id])) {
      throw new \InvalidArgumentException(
        "The progress step '$progress_step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->progress_steps[$progress_step_id]['label'] = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepWeight(string $step_id, int $weight): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['weight'] = $weight;
    return $this;
  }

  /**
   * Set the weight for a progress step.
   */
  public function setProgressStepWeight($progress_step_id, $weight): FormsSteps {
    if (!isset($this->progress_steps[$progress_step_id])) {
      throw new \InvalidArgumentException(
        "The progress step '$progress_step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->progress_steps[$progress_step_id]['weight'] = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepEntityBundle(string $step_id, string $entityBundle): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['entity_bundle'] = $entityBundle;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepUrl(string $step_id, string $url): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['url'] = '';
    if ('/' != $url[0]) {
      $url = '/' . $url;
    }
    if (!empty(Url::fromUri("internal:$url"))) {
      $this->steps[$step_id]['url'] = $url;
    }
    else {
      throw new \InvalidArgumentException(
        "The Url Step '$url' is not accessible"
      );
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepFormMode(string $step_id, string $formMode): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['form_mode'] = $formMode;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepEntityType(string $step_id, string $entity_type): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['entity_type'] = $entity_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepSubmitLabel(string $step_id, string $label): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['submitLabel'] = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepCancelLabel(string $step_id, string $label): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['cancelLabel'] = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepCancelRoute(string $step_id, string $route): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['cancelRoute'] = $route;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepCancelStep(string $step_id, Step $step): FormsStepsInterface {
    if (!$step) {
      return $this;
    }
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['cancelStep'] = $step;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepCancelStepMode(string $step_id, string $mode): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['cancelStepMode'] = $mode;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepDeleteLabel(string $step_id, $label): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['deleteLabel'] = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepDeleteState(string $step_id, bool $state): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['hideDelete'] = $state;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setProgressStepActiveRoutes(string $progress_step_id, array $routes): FormsStepsInterface {
    if (!isset($this->progress_steps[$progress_step_id])) {
      throw new \InvalidArgumentException(
        "The progress step '$progress_step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->progress_steps[$progress_step_id]['routes'] = $routes;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setProgressStepLink(string $progress_step_id, string $link): FormsStepsInterface {
    if (!isset($this->progress_steps[$progress_step_id])) {
      throw new \InvalidArgumentException(
        "The progress step '$progress_step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->progress_steps[$progress_step_id]['link'] = $link;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setProgressStepLinkVisibility(string $progress_step_id, array $steps): FormsStepsInterface {
    if (!isset($this->progress_steps[$progress_step_id])) {
      throw new \InvalidArgumentException(
        "The progress step '$progress_step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->progress_steps[$progress_step_id]['link_visibility'] = $steps;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteStep(string $step_id): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    if (count($this->steps) === 1) {
      throw new \InvalidArgumentException(
        "The step '$step_id' can not be deleted from forms steps '{$this->id()}' as it is the only Step"
      );
    }

    unset($this->steps[$step_id]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteProgressStep(string $progress_step_id): FormsStepsInterface {
    if (!isset($this->progress_steps[$progress_step_id])) {
      throw new \InvalidArgumentException(
        "The progress step '$progress_step_id' does not exist in forms steps '{$this->id()}'"
      );
    }

    unset($this->progress_steps[$progress_step_id]);
    return $this;
  }

  /**
   * Gets the weight for a new step or progress step.
   *
   * @param array $items
   *   An array of steps where each item has a
   *   'weight' key with a numeric value.
   *
   * @return int
   *   The weight for a step in the array so that it has the highest weight.
   */
  protected function getNextWeight(array $items): int {
    return array_reduce($items, function ($carry, $item) {
      return max($carry, $item['weight'] + 1);
    }, 0);
  }

  /**
   * {@inheritdoc}
   */
  public function status(): bool {
    // In order for a forms_steps to be usable it must have at least one step.
    return !empty($this->status) && !empty($this->steps);
  }

  /**
   * {@inheritdoc}
   */
  public function setStepPreviousLabel(string $step_id, $label): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['previousLabel'] = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStepPreviousState(string $step_id, bool $state): FormsStepsInterface {
    if (!isset($this->steps[$step_id])) {
      throw new \InvalidArgumentException(
        "The Step '$step_id' does not exist in forms steps '{$this->id()}'"
      );
    }
    $this->steps[$step_id]['displayPrevious'] = $state;
    return $this;
  }

}
