<?php

namespace Drupal\ai\OperationType\TextClassification;

/**
 * One classification item.
 */
class TextClassificationItem {

  /**
   * The label of the text classification.
   *
   * @var string
   */
  private string $label;

  /**
   * The confidence score of the text classification.
   *
   * @var float|null
   */
  private float|null $confidenceScore;

  /**
   * The constructor.
   *
   * @param string $label
   *   The classification label.
   * @param float|null $confidence_score
   *   The confidence score.
   */
  public function __construct(string $label, float|null $confidence_score = NULL) {
    $this->label = $label;
    $this->confidenceScore = $confidence_score;
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
  public function getConfidenceScore(): float|null {
    return $this->confidenceScore;
  }

  /**
   * Sets the confidence score.
   *
   * @param float|null $confidence_score
   *   The confidence score.
   */
  public function setConfidenceScore(float|null $confidence_score): void {
    $this->confidenceScore = $confidence_score;
  }

  /**
   * Returns the confidence score as a percentage.
   *
   * @return float
   *   The confidence score as a percentage.
   */
  public function getConfidenceScorePercentage(): float {
    return $this->confidenceScore ? round($this->confidenceScore * 100, 2) : 0.0;
  }

}
