<?php

namespace Drupal\tour\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormState;
use Drupal\tour\Entity\Tour;
use Drupal\tour\TipPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles page returns for tour tip.
 */
class TourTipController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs a new TourTipController object.
   *
   * @param \Drupal\tour\TipPluginManager $tipPluginManager
   *   The Tip Plugin Manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   */
  public function __construct(
    protected TipPluginManager $tipPluginManager,
    protected RequestStack $requestStack,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.tour.tip'),
      $container->get('request_stack')
    );
  }

  /**
   * Provides a creation form for a new tip to be added to a tour entity.
   *
   * @param \Drupal\tour\Entity\Tour $tour
   *   The tour in which the tip needs to be added to.
   * @param string $type
   *   The type of tip that will be added to the tour.
   *
   * @return array
   *   A renderable form array.
   */
  public function add(Tour $tour, string $type = ''): array {
    // We need a type to build this form.
    if (!$type) {
      throw new NotFoundHttpException();
    }

    // Default values.
    $defaults = [
      'plugin' => Html::escape($type),
      'weight' => $this->requestStack->getCurrentRequest()->query->get('weight'),
    ];

    // Build a new stub tip.
    $stub = $this->tipPluginManager->createInstance($type, $defaults);

    // Attach the tour, tip and if it's new to the form.
    $form_state = new FormState();
    $form_state->setFormState([
      '#tour' => $tour,
      '#tip' => $stub,
      '#new' => TRUE,
    ]);
    return $this->formBuilder()->buildForm('\Drupal\tour\Form\TourTipForm', $form_state);
  }

  /**
   * Provides an edit form for tip to be updated against a tour entity.
   *
   * @param \Drupal\tour\Entity\Tour $tour
   *   The tour in which the tip is being edited against.
   * @param string $tip
   *   The identifier of tip that will be edited against the tour.
   *
   * @return array
   *   A renderable form array.
   */
  public function edit(Tour $tour, string $tip = ''): array {
    $the_tip = $tour->getTip($tip);

    // Attach the tour and tip.
    $form_state = new FormState();
    $form_state->setFormState([
      '#tour' => $tour,
      '#tip' => $the_tip,
    ]);
    return $this->formBuilder()->buildForm('\Drupal\tour\Form\TourTipForm', $form_state);
  }

}
