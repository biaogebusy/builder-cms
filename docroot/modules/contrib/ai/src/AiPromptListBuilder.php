<?php

namespace Drupal\ai;

use Drupal\ai\Entity\AiPromptInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a class to build a listing of AI Prompt.
 *
 * @see \Drupal\ai\Entity\AiPrompt
 */
class AiPromptListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['help'] = [
      '#markup' => '<p>' . $this->t('AI Prompts are reusable prompt templates used throughout the AI ecosystem. Each prompt belongs to a Prompt Type, which defines the variables and tokens available for that prompt.') . '</p>',
    ];
    $build += parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['type'] = $this->t('Prompt Type');
    $header['label'] = $this->t('Label');
    $header['prompt'] = $this->t('Prompt (trimmed)');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['id'] = $entity->id();
    if ($entity instanceof AiPromptInterface) {
      $row['type'] = $entity->bundle();
      $row['label'] = $entity->label();

      // Make a truncated version of the prompt without new lines for the list
      // of prompts to keep it compact.
      $row['prompt'] = str_replace("\n", ' ', $entity->getPrompt());
      $row['prompt'] = Unicode::truncate($row['prompt'], 100, TRUE, TRUE);
    }
    return $row + parent::buildRow($entity);
  }

}
