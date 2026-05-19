<?php

namespace Drupal\simple_oauth\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\simple_oauth\Oauth2ScopeProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if referenced OAuth2 scopes are valid.
 */
class Oauth2ScopeReferenceValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The scope provider.
   *
   * @var \Drupal\simple_oauth\Oauth2ScopeProviderInterface
   */
  protected Oauth2ScopeProviderInterface $scopeProvider;

  /**
   * Constructs a Oauth2ScopeReferenceValidator object.
   *
   * @param \Drupal\simple_oauth\Oauth2ScopeProviderInterface $scope_provider
   *   The scope provider.
   */
  public function __construct(Oauth2ScopeProviderInterface $scope_provider) {
    $this->scopeProvider = $scope_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simple_oauth.oauth2_scope.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    $referenced_scope_ids = [];

    foreach ($value as $item) {
      $referenced_scope_ids[] = $item->scope_id;
    }

    $scopes = $this->scopeProvider->loadMultiple($referenced_scope_ids);

    foreach ($referenced_scope_ids as $delta => $referenced_scope_id) {
      if (!isset($scopes[$referenced_scope_id])) {
        $this->context->buildViolation($constraint->nonExistingMessage)
          ->setParameter('%id', $referenced_scope_id)
          ->atPath((string) $delta . '.scope_id')
          ->setInvalidValue($referenced_scope_id)
          ->addViolation();
      }
    }
  }

}
