<?php

namespace Drupal\ai_automators;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Utility\Token;

/**
 * Helps replacing prompts.
 */
class AiPromptHelper {

  /**
   * The twig environment.
   */
  protected TwigEnvironment $twig;

  /**
   * The current user.
   */
  protected AccountProxy $currentUser;

  /**
   * The token service.
   */
  protected Token $token;

  /**
   * Constructs a new AiAutomatorRuleRunner object.
   *
   * @param \Drupal\Core\Template\TwigEnvironment $twig
   *   Twig environment.
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The current user.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(TwigEnvironment $twig, AccountProxy $currentUser, Token $token) {
    $this->twig = $twig;
    $this->currentUser = $currentUser;
    $this->token = $token;
  }

  /**
   * Render a prompt.
   *
   * @var string $prompt
   *   The prompt.
   * @var array $tokens
   *   The placeholders.
   *
   * @return string
   *   The rendered twig.
   */
  public function renderPrompt($prompt, array $tokens) {
    // Get variables.
    $template = $this->twig->createTemplate(htmlspecialchars_decode($prompt));
    return $template->render($tokens);
  }

  /**
   * Render a tokenized prompt.
   *
   * @var string $prompt
   *   The prompt.
   * @var \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The rendered prompt.
   */
  public function renderTokenPrompt($prompt, ContentEntityInterface $entity) {
    // Get variables.
    return $this->token->replace($prompt, [
      $this->getEntityTokenType($entity->getEntityTypeId()) => $entity,
      'user' => $this->currentUser,
    ]);
  }

  /**
   * Gets the entity token type.
   *
   * @param string $entityTypeId
   *   The entity type id.
   *
   * @return string
   *   The corrected type.
   */
  protected function getEntityTokenType($entityTypeId) {
    switch ($entityTypeId) {
      case 'taxonomy_term':
        return 'term';
    }
    return $entityTypeId;
  }

}
