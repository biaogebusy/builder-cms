<?php

namespace Drupal\webform_node\Hook;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\node\NodeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_node.
 */
class WebformNodeTokensHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo() {
    $types['webform_submission'] = [
      'name' => $this->t('Webform submissions'),
      'description' => $this->t('Tokens related to webform submission.'),
      'needs-data' => 'webform_submission',
    ];
    $webform_submission['node'] = [
      'name' => $this->t('Node'),
      'description' => $this->t("The node that the webform was submitted from."),
      'type' => 'node',
    ];
    return ['types' => $types, 'tokens' => ['webform_submission' => $webform_submission]];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $token_service = \Drupal::token();
    $replacements = [];
    if ($type === 'webform_submission' && !empty($data['webform_submission'])) {
      /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
      $webform_submission = $data['webform_submission'];
      $source_entity = $webform_submission->getSourceEntity(TRUE);
      if (!$source_entity || !$source_entity instanceof NodeInterface) {
        return $replacements;
      }
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'node':
            $replacements[$original] = $source_entity->label();
            break;
        }
      }
      if ($entity_tokens = $token_service->findWithPrefix($tokens, 'node')) {
        $replacements += $token_service->generate('node', $entity_tokens, ['node' => $source_entity], $options, $bubbleable_metadata);
      }
    }
    return $replacements;
  }

}
