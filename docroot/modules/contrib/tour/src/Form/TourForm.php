<?php

namespace Drupal\tour\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\tour\TipPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

/**
 * Form controller for the tour entity edit forms.
 */
class TourForm extends EntityForm {

  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('language_manager'),
      $container->get('plugin.manager.tour.tip'),
      $container->get('cache_tags.invalidator')
    );
  }

  /**
   * Constructs a TourForm object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The Language Manager service.
   * @param \Drupal\tour\TipPluginManager $tipPluginManager
   *   The Tip Plugin Manager service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The Cache Tags Invalidator service.
   */
  public function __construct(
    protected LanguageManagerInterface $languageManager,
    protected TipPluginManager $tipPluginManager,
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit <em>%label</em> tour', ['%label' => $this->entity->label()]);
    }

    $tour = $this->entity;
    $form = parent::form($form, $form_state);

    if ($tour->isNew()) {
      $form_state->set('is_new', TRUE);
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tour name'),
      '#required' => TRUE,
      '#default_value' => $tour->label(),
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => '\Drupal\tour\Entity\Tour::load',
      ],
      '#default_value' => $tour->id(),
      '#disabled' => !$tour->isNew(),
    ];

    $form['langcode'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Language'),
      '#languages' => LanguageInterface::STATE_ALL,
      // Default to the content language opposed to und (no language).
      '#default_value' => empty($tour->language()) ? $this->languageManager->getCurrentLanguage()->getId() : $tour->language()->getId(),
    ];

    $modules_list = [];
    $dependencies = $tour->getDependencies();
    if ($dependencies && isset($dependencies['module'])) {
      $modules_list = implode(',', $dependencies['module']);
    }

    $form['module'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Module name'),
      '#description' => $this->t('Each tour can have a number of module dependencies. Separate by a comma.'),
      '#required' => FALSE,
      '#autocomplete_route_name' => 'tour.get_modules',
      '#default_value' => $modules_list,
    ];

    $default_routes = [];
    if ($routes = $tour->getRoutes()) {
      foreach ($routes as $route) {
        $default_routes[] = $route['route_name'];
        if (isset($route['route_params'])) {
          foreach ($route['route_params'] as $key => $value) {
            $default_routes[] = '- ' . $key . ':' . $value;
          }
        }
      }
    }
    $form['routes'] = [
      '#type' => 'textarea',
      '#required' => TRUE,
      '#title' => $this->t('Routes'),
      '#default_value' => implode("\n", $default_routes),
      '#rows' => 5,
      '#description' => $this->t('Provide a list of routes that this tour will be displayed on. Add route_name first then optionally route parameters. For example:<pre>entity.node.canonical<br>- node:2</pre> will only show on the <em>node/2</em> page.<pre>entity.dashboard.canonical<br/>- id:secondary_dashboard</pre> will only show on the<em>admin/dashboard/secondary_dashboard</em> page.<br><p>Other supported param keys are <em>taxonomy_term</em>, <em>bundle/node_type</em>, <em>taxonomy_vocabulary</em></p><br>NOTE: route parameters are <strong>not validated yet</strong>.'),
    ];

    $form['find-routes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Find route or path fragment'),
      '#description' => $this->t('You can type a route name or path fragment.'),
      '#required' => FALSE,
      '#autocomplete_route_name' => 'tour.get_routes',
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => 'Enabled',
      "#default_value" => $tour->get('status'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo validate the routes
    $routes = $this->routesFromArray($form_state->getValue('routes'));
  }

  /**
   * Rebuild the lines into route structures.
   *
   * @param string $routes_in
   *   Inbound routes.
   *
   * @return array
   *   Matching routes.
   */
  protected function routesFromArray(string $routes_in): array {
    // Normalize the new lines.
    $routes_in = preg_replace("/(\r\n?|\n)/", "\n", $routes_in);
    $routes_in = explode("\n", $routes_in);
    // Trim each line.
    $routes_in = array_map('trim', $routes_in);

    $routes = [];
    $route = NULL;
    foreach ($routes_in as $line) {
      if (empty($line)) {
        continue;
      }
      if (!str_starts_with($line, '-')) {
        $routes[] = [];
        $route = &$routes[count($routes) - 1];
        $route['route_name'] = $line;
      }
      else {
        if (count($routes) === 0) {
          // Abort when having a route_params without a route_name.
          break;
        }
        [$key, $value] = explode(':', $line, 2);
        $key = trim(substr($key, 1));
        $value = trim($value);
        $route['route_params'][$key] = $value;
      }
    }
    return $routes;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Filter out invalid characters and convert to an array.
    $routes = $this->routesFromArray($form_state->getValue('routes'));

    $form_state->setValue('routes', array_filter($routes));
    $form_state->setValue('status', $form_state->getValue('status'));

    $input_values = $form_state->getUserInput();
    if (!empty($input_values) && !$form_state->get('is_new')) {
      $this->messenger()->addMessage($this->t('The tour %tour has been updated.', ['%tour' => $form_state->getValue('label')]));
    }
    else {
      $this->messenger()->addMessage($this->t('The tour %tour has been created.', ['%tour' => $form_state->getValue('label')]));
    }

    // Capture original routes BEFORE saving (only if not new).
    $original_routes = NULL;
    $entity_id = $this->entity->id();
    if (!$this->entity->isNew()) {
      $original_config = $this->configFactory()->get('tour.tour.' . $entity_id);
      if ($original_config) {
        $original_routes = $original_config->get('routes');
      }
    }

    parent::submitForm($form, $form_state);

    // Compare pre/post routes; if changed, invalidate relevant cache tags.
    $new_routes = $this->entity->get('routes') ?? [];
    if ($original_routes !== $new_routes) {
      $this->cacheTagsInvalidator->invalidateTags([
        'config:tour.tour.' . $entity_id,
        'tour_settings',
      ]);
    }

    $form_state->setRedirect('entity.tour.edit_form', ['tour' => $this->entity->id()]);
  }

}
