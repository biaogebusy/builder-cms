<?php

declare(strict_types=1);

namespace Drupal\forms_steps;

/**
 * A value object representing a progress step.
 */
class ProgressStep implements ProgressStepInterface {

  /**
   * The forms_steps the progress step is attached to.
   *
   * @var \Drupal\forms_steps\FormsStepsInterface
   */
  protected FormsStepsInterface $formsSteps;

  /**
   * The progress step's ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * The progress step's label.
   *
   * @var string
   */
  protected string $label;

  /**
   * The progress step's weight.
   *
   * @var int
   */
  protected int $weight;

  /**
   * The progress step's active routes.
   *
   * @var string
   */
  protected $routes;

  /**
   * The progress step's link.
   *
   * @var string
   */
  protected string $link;

  /**
   * The progress step's link visibility.
   *
   * @var array
   */
  protected array $linkVisibility;

  /**
   * Step constructor.
   *
   * @param \Drupal\forms_Steps\FormsStepsInterface $forms_steps
   *   The forms_steps the progress step is attached to.
   * @param string $id
   *   The progress step's ID.
   * @param string $label
   *   The progress step's label.
   * @param int $weight
   *   The progress step's weight.
   * @param array $routes
   *   The progress step's active routes.
   * @param string $link
   *   The progress step's link.
   * @param array $link_visibility
   *   The progress step's link visibility.
   */
  public function __construct(FormsStepsInterface $forms_steps, string $id, string $label, int $weight, array $routes, string $link, array $link_visibility) {
    $this->formsSteps = $forms_steps;
    $this->id = $id;
    $this->label = $label;
    $this->weight = $weight;
    $this->routes = $routes;
    $this->link = $link;
    $this->linkVisibility = $link_visibility;
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function weight(): int {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function activeRoutes(): array {
    return $this->routes;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveRoutes(array $routes): array {
    return $this->routes = $routes;
  }

  /**
   * {@inheritdoc}
   */
  public function link(): string {
    return $this->link;
  }

  /**
   * {@inheritdoc}
   */
  public function setLink(string $link): string {
    return $this->link = $link;
  }

  /**
   * {@inheritdoc}
   */
  public function linkVisibility(): array {
    return $this->linkVisibility;
  }

  /**
   * {@inheritdoc}
   */
  public function setLinkVisibility(array $steps): array {
    return $this->linkVisibility = $steps;
  }

}
