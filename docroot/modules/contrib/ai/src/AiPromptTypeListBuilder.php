<?php

namespace Drupal\ai;

use Drupal\ai\Entity\AiPromptTypeInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a class to build a listing of AI Prompt Types.
 *
 * @see \Drupal\ai\Entity\AiPromptType
 */
class AiPromptTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['help'] = [
      '#markup' => '<p>' . $this->t('AI Prompt Types define the structure of AI Prompts, including the required variables and tokens. They are typically provided by modules within the AI ecosystem, but you can also create custom types for your own implementations.') . '</p>',
    ];
    $build += parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['prompt_type'] = $this->t('Prompt Type');
    $header['variables'] = $this->t('Variables');
    $header['tokens'] = $this->t('Tokens');
    $header['prompt_count'] = $this->t('Number of prompts of this type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['id'] = $entity->id();
    $row['prompt_type'] = $entity->label();
    if ($entity instanceof AiPromptTypeInterface) {
      $row['variables'] = $this->compactVariablesTokens($entity->getVariables());
      $row['tokens'] = $this->compactVariablesTokens($entity->getTokens());
      $row['prompt_count'] = $entity->getPromptCount();
    }
    return $row + parent::buildRow($entity);
  }

  /**
   * Provide a compact representation of variables or tokens for the listing.
   *
   * @param array $variables
   *   The array of token or variable configurations.
   *
   * @return string
   *   A preview of the tokens or variables required and suggested.
   */
  protected function compactVariablesTokens(array $variables): string {
    $preview = [];
    foreach ($variables as $variable) {
      $text = $variable['name'];
      $text .= ' (';
      $text .= $variable['required'] ? $this->t('Required') : $this->t('Optional');
      $text .= ')';
      $preview[] = $text;
    }
    return implode(', ', $preview);
  }

}
