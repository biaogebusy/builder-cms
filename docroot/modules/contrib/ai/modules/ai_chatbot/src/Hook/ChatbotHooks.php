<?php

declare(strict_types=1);

namespace Drupal\ai_chatbot\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Hook implementations for contextual.
 */
class ChatbotHooks {

  use StringTranslationTrait;

  private const CHATBOT_BLOCK_PLUGIN_ID = 'ai_deepchat_block';

  /**
   * Constructs the ChatbotHooks object.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ThemeManagerInterface $themeManager,
  ) {
  }

  /**
   * Implements hook_preprocess_top_bar().
   */
  #[Hook('preprocess_top_bar')]
  public function topbar(array &$variables): void {
    // Check if user has permission.
    if (!$this->currentUser->hasPermission('access deepchat api')) {
      return;
    }

    // Cache by block plugin to refresh on change.
    $variables['#cache']['tags'][] = 'block:' . self::CHATBOT_BLOCK_PLUGIN_ID;

    // Check if any ai_deepchat_block exists on the site.
    if (!$this->hasDeepChatBlock()) {
      return;
    }

    $ai_chatbot = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#attributes' => [
        'class' => ['hidden', 'button--ai-chatbot'],
        'aria-label' => $this->t('Open AI assistant'),
      ],
      '#weight' => -9999,
    ];

    $variables['tools'][] = $ai_chatbot;
  }

  /**
   * Implements hook_toolbar().
   */
  #[Hook('toolbar')]
  public function toolbar() {
    // Check if user has permission.
    if (!$this->currentUser->hasPermission('access deepchat api')) {
      return;
    }

    // Check if any ai_deepchat_block exists on the site.
    if (!$this->hasDeepChatBlock()) {
      return;
    }

    $items = [];

    $items['ai_chatbot'] = [
      '#type' => 'toolbar_item',
      '#cache' => [
        'tags' => ['block:' . self::CHATBOT_BLOCK_PLUGIN_ID],
      ],
      'tab' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Assistant'),
        '#attributes' => [
          'class' => [
            'hidden',
            'toolbar-icon',
            'toolbar-icon-ai-chatbot',
            'open-chat',
            'button--ai-chatbot',
          ],
          'aria-pressed' => 'false',
          'type' => 'button',
        ],
      ],
      '#wrapper_attributes' => [
        'class' => [
          'ai-chatbot-toolbar-tab',
        ],
      ],
    ];

    return $items;
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter().
   */
  #[Hook('theme_suggestions_ai_deepchat_alter')]
  public function themeSuggestionsAiDeepchatAlter(array &$suggestions, array $variables): void {
    if (!empty($variables['settings']['placement'])) {
      $placement = strtr($variables['settings']['placement'], '-', '_');
      $suggestions[] = 'ai_deepchat__' . $placement;
    }
  }

  /**
   * Checks if any ai_deepchat_block is placed on the site.
   *
   * @return bool
   *   TRUE if at least one ai_deepchat_block exists and is enabled.
   */
  protected function hasDeepChatBlock(): bool {
    try {
      $theme = $this->themeManager->getActiveTheme()->getName();
      $block_storage = $this->entityTypeManager->getStorage('block');

      // Load all blocks for the current theme.
      $blocks = $block_storage->loadByProperties([
        'theme' => $theme,
        'plugin' => self::CHATBOT_BLOCK_PLUGIN_ID,
      ]);

      // Check if any of the blocks are enabled.
      foreach ($blocks as $block) {
        /** @var \Drupal\block\Entity\Block $block */
        if ($block->get('status')) {
          return TRUE;
        }
      }
    }
    catch (\Exception $e) {
      // If something goes wrong, fail gracefully.
      return FALSE;
    }

    return FALSE;
  }

}
