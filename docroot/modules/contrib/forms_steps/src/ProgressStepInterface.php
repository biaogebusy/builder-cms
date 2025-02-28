<?php

declare(strict_types=1);

namespace Drupal\forms_steps;

/**
 * An interface for progress step value objects.
 */
interface ProgressStepInterface {

  /**
   * Gets the progress step's ID.
   *
   * @return string
   *   The progress step's ID.
   */
  public function id(): string;

  /**
   * Gets the progress step's label.
   *
   * @return string
   *   The progress step's label.
   */
  public function label(): string;

  /**
   * Gets the progress step's weight.
   *
   * @return int
   *   The progress step's weight.
   */
  public function weight(): int;

  /**
   * Gets the active routes for this progress step.
   *
   * @return array
   *   The active routes for this progress step.
   */
  public function activeRoutes(): array;

  /**
   * Set the active routes for this progress step.
   *
   * @param array $routes
   *   Routes to set for this progress step.
   */
  public function setActiveRoutes(array $routes);

  /**
   * Gets the link for this progress step.
   *
   * @return string
   *   The link for this progress step.
   */
  public function link(): string;

  /**
   * Set the link for this progress step.
   *
   * @param string $link
   *   Links to set for this progress step.
   */
  public function setLink(string $link);

  /**
   * Gets the link visibility for this progress step.
   *
   * @return array
   *   The steps for which the link is visible for this progress step.
   */
  public function linkVisibility(): array;

  /**
   * Set the link visibility for this progress step.
   *
   * @param array $steps
   *   Set the steps where the link will be visible.
   */
  public function setLinkVisibility(array $steps);

}
