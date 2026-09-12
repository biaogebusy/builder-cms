<?php

namespace Drupal\pathauto\Hook;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pathauto\AliasCleanerInterface;

/**
 * Token hook implementations for pathauto.
 */
class PathautoTokensHooks {

  use StringTranslationTrait;

  public function __construct(
    protected AliasCleanerInterface $aliasCleaner,
    protected RendererInterface $renderer,
  ) {

  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $info = [];
    $info['tokens']['array']['join-path'] = [
      'name' => $this->t('Joined path'),
      'description' => $this->t('The array values each cleaned by Pathauto and then joined with the slash into a string that resembles an URL.'),
    ];
    return $info;
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];
    if ($type == 'array' && !empty($data['array'])) {
      $array = $data['array'];
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'join-path':
            $values = [];
            foreach (token_element_children($array) as $key) {
              $value = is_array($array[$key]) ? $this->renderer->render($array[$key]) : (string) $array[$key];
              $value = $this->aliasCleaner->cleanString($value, $options);
              $values[] = $value;
            }
            $replacements[$original] = implode('/', $values);
            break;
        }
      }
    }
    return $replacements;
  }

}
