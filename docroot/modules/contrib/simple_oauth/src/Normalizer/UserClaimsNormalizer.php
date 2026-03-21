<?php

namespace Drupal\simple_oauth\Normalizer;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\serialization\Normalizer\NormalizerBase;
use Drupal\simple_oauth\Entities\UserEntityWithClaims;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes a user entity to extract the claims.
 */
class UserClaimsNormalizer extends NormalizerBase implements NormalizerInterface {

  protected $supportedInterfaceOrClass = UserEntityWithClaims::class;

  protected $format = 'json';

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The claims.
   *
   * @var string[]
   */
  protected $claims;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * UserClaimsNormalizer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param string[] $claims
   *   The list of claims being selected.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, array $claims, ModuleHandlerInterface $module_handler) {
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->claims = $claims;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($user_entity, $format = NULL, array $context = []) {
    assert($user_entity instanceof UserEntityWithClaims);
    $identifier = $user_entity->getIdentifier();
    // Check if the account is in $context. If not, load it from the database.
    $account = $context[$identifier] instanceof AccountInterface
      ? $context[$identifier]
      : $this->userStorage->load($identifier);
    assert($account instanceof AccountInterface);
    return $this->getClaimsFromAccount($account);
  }

  /**
   * Gets the claims for a given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return array
   *   The claims key/values.
   */
  private function getClaimsFromAccount(AccountInterface $account) {
    /** @var \Drupal\simple_oauth\Authentication\TokenAuthUserInterface $profile_url */
    $profile_url = $account->toUrl('canonical', ['absolute' => TRUE])
      ->toString();
    $claim_values = [
      'sub' => $account->id(),
      'name' => $account->getDisplayName(),
      'preferred_username' => $account->getAccountName(),
      'email' => $account->getEmail(),
      'email_verified' => TRUE,
      'profile' => $profile_url,
      'locale' => $account->getPreferredLangcode(),
      'zoneinfo' => $account->getTimeZone(),
    ];
    if ($account instanceof EntityChangedInterface) {
      $claim_values['updated_at'] = $account->getChangedTime();
    }
    $context = ['account' => $account, 'claims' => $this->claims];
    $this->moduleHandler->alter('simple_oauth_oidc_claims', $claim_values, $context);
    return array_intersect_key($claim_values, array_flip($this->claims));
  }

}
