<?php

namespace Drupal\ai\OperationType\ObjectDetection;

/**
 * One object detection item.
 */
class ObjectDetectionItem {

  /**
   * The label of the detected object.
   *
   * @var string
   */
  private string $label;

  /**
   * The confidence score of the detected object.
   *
   * @var float|null
   */
  private float|NULL $confidenceScore;

  /**
   * The rectangle coordinates of detected object.
   *
   * @var array
   */
  private array $box = [];

  /**
   * The constructor.
   */
  public function __construct(string $label, array $box = [], float|NULL $confidence_score = NULL) {
    $this->label = $label;
    $this->confidenceScore = $confidence_score;
    $this->box = $box;
  }

  /**
   * Returns the label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string {
    return $this->label;
  }

  /**
   * Sets the label.
   *
   * @param string $label
   *   The label.
   */
  public function setLabel(string $label): void {
    $this->label = $label;
  }

  /**
   * Returns the confidence score.
   *
   * @return float|null
   *   The confidence score.
   */
  public function getConfidenceScore(): float|NULL {
    return $this->confidenceScore;
  }

  /**
   * Sets the confidence score.
   *
   * @param float|null $confidence_score
   *   The confidence score.
   */
  public function setConfidenceScore(float|NULL $confidence_score): void {
    $this->confidenceScore = $confidence_score;
  }

  /**
   * Returns the confidence score as a percentage.
   *
   * @return float|null
   *   The confidence score as a percentage.
   */
  public function getConfidenceScorePercentage(): string {
    return $this->confidenceScore ? round($this->confidenceScore * 100, 2) : '0';
  }

  /**
   * Gets the coordinates of the detected object.
   *
   * @return array
   *   The coordinates in format:
   *   [
   *     "xmin" => 535,
   *     "ymin" => 1332,
   *     "xmax" => 3643,
   *     "ymax" => 4618,
   *   ]
   */
  public function getBox(): array {
    return $this->box;
  }

  /**
   * Sets the coordinates of the detected object.
   *
   * @param array $box
   *   The coordinates of the detected object.
   */
  public function setBox(array $box): void {
    $this->box = $box;
  }

}
