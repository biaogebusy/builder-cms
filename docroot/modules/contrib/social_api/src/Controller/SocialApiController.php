<?php

namespace Drupal\social_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\social_api\Plugin\NetworkManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders integrations of social api.
 */
class SocialApiController extends ControllerBase {

  /**
   * Extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected ExtensionPathResolver $extensionPathResolver;

  /**
   * The network manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private NetworkManager $networkManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('extension.path.resolver'),
      $container->get('plugin.network.manager')
    );
  }

  /**
   * SocialApiController constructor.
   *
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver
   *   Extension path resolver.
   * @param \Drupal\social_api\Plugin\NetworkManager $networkManager
   *   The network manager.
   */
  public function __construct(ExtensionPathResolver $extensionPathResolver, NetworkManager $networkManager) {
    $this->extensionPathResolver = $extensionPathResolver;
    $this->networkManager = $networkManager;
  }

  /**
   * Render the list of plugins for a social network.
   *
   * @param string $type
   *   Integration type: social_auth, social_post, or social_widgets.
   *
   * @return array
   *   Render array listing the integrations.
   */
  public function integrations(string $type): array {
    $networks = $this->networkManager->getDefinitions();
    $header = [
      $this->t('Module'),
      $this->t('Social Network'),
    ];
    $data = [];
    foreach ($networks as $network) {
      if ($network['type'] == $type) {
        $data[] = [
          $network['id'],
          $network['social_network'],
        ];
      }
    }
    return [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $data,
      '#empty' => $this->t('There are no social integrations enabled.'),
    ];
  }

}
