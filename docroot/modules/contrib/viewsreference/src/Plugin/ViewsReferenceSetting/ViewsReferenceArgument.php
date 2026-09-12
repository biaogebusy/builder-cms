<?php

namespace Drupal\viewsreference\Plugin\ViewsReferenceSetting;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\views\ViewExecutable;
use Drupal\viewsreference\Plugin\ViewsReferenceSettingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The views reference setting argument plugin.
 *
 * @ViewsReferenceSetting(
 *   id = "argument",
 *   label = @Translation("Argument"),
 *   default_value = "",
 * )
 */
class ViewsReferenceArgument extends PluginBase implements ViewsReferenceSettingInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Drupal token service container.
   *
   * @var Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /**
   * Constructs a ViewsReferenceArgument object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CurrentRouteMatch $current_route_match,
    Token $token,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $current_route_match;
    $this->tokenService = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @phpstan-ignore-next-line */
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterFormField(array &$form_field) {
    $form_field['#description'] = $this->t(
      'Separate contextual filter values with a "/". For example, %example.',
      ['%example' => '40/12/10'],
    ) . ' ' . $this->t('When using "Allow multiple values" in the view, enter multiple values per contextual filter in the form of 1+2+3 (for OR) or 1,2,3 (for AND).');
    $form_field['#weight'] = 40;
  }

  /**
   * {@inheritdoc}
   */
  public function alterView(ViewExecutable $view, $value) {
    if (!empty($value)) {
      $arguments = [$value];
      if (preg_match('/\//', $value)) {
        $arguments = explode('/', $value);
      }

      $node = $this->routeMatch->getParameter('node');
      if (is_array($arguments)) {
        foreach ($arguments as $index => $argument) {
          if (!empty($this->tokenService->scan($argument))) {
            $arguments[$index] = $this->tokenService->replace($argument, ['node' => $node]);
          }
        }
      }

      $view->setArguments($arguments);
    }
  }

}
