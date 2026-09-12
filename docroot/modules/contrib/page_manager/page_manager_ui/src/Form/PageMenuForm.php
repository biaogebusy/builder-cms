<?php

namespace Drupal\page_manager_ui\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\page_manager\PageInterface;
use Drupal\page_manager\Plugin\PageManagerMenuManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Menu page in wizard.
 */
class PageMenuForm extends FormBase {

  /**
   * The menu manager.
   *
   * @var \Drupal\page_manager\Plugin\PageManagerMenuManager
   */
  protected $manager;

  /**
   * The temp store.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempstore;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.page_manager_menu'), $container->get('tempstore.shared'));
  }

  /**
   * Constructs a PageMenuForm.
   *
   * @param \Drupal\page_manager\Plugin\PageManagerMenuManager $manager
   *   The menu manager.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempstore
   *   The temp store factory.
   */
  public function __construct(PageManagerMenuManager $manager, SharedTempStoreFactory $tempstore) {
    $this->manager = $manager;
    $this->tempstore = $tempstore;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'page_manager_menu_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');
    /** @var \Drupal\page_manager\Entity\Page $page */
    $page = $cached_values['page'];
    // Set the machine_name into the form_state for later use.
    $form_state->set('machine_name', $page->id());
    $form['#tree'] = FALSE;
    $options = [
      'none' => $this->t('No menu entry'),
    ];
    foreach ($this->manager->getDefinitions() as $plugin_id => $definition) {
      $options[$plugin_id] = $definition['label'];
    }
    $form['menu_type_settings'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['class' => ['page-manager-menu-type-settings']],
    ];
    $form['menu_type_settings']['menu_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Menu type'),
      '#options' => $options,
      '#default_value' => $page->getMenuType(),
      '#ajax' => [
        'callback' => [$this, 'menuSettings'],
      ],
    ];
    $menu_type = $this->getSubmittedMenuType($form_state, $page->getMenuType());
    if ($instance = $this->createMenuInstance($menu_type, $page)) {
      $form['menu_type_settings']['menu_settings'] = $instance->buildConfigurationForm([], $form_state);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');
    /** @var \Drupal\page_manager\PageInterface $page */
    $page = $cached_values['page'];
    $menu_type = $form_state->getValue(['menu_type_settings', 'menu_type']);
    if (!isset($form['menu_type_settings']['menu_settings'])) {
      return;
    }
    if ($instance = $this->createMenuInstance($menu_type, $page)) {
      // Use a subform state so that any error the plugin raises is reported
      // against the real element rather than being discarded.
      $subform_state = SubformState::createForSubform($form['menu_type_settings']['menu_settings'], $form, $form_state);
      $instance->validateConfigurationForm($form['menu_type_settings']['menu_settings'], $subform_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->hasValue('menu_type_settings')) {
      return;
    }
    $cached_values = $form_state->getTemporaryValue('wizard');
    /** @var \Drupal\page_manager\PageInterface $page */
    $page = $cached_values['page'];
    $menu_type = $form_state->getValue(['menu_type_settings', 'menu_type']);

    $instance = isset($form['menu_type_settings']['menu_settings'])
      ? $this->createMenuInstance($menu_type, $page)
      : NULL;
    if ($instance) {
      $subform_state = SubformState::createForSubform($form['menu_type_settings']['menu_settings'], $form, $form_state);
      $instance->submitConfigurationForm($form['menu_type_settings']['menu_settings'], $subform_state);
      $page->set('menu_type', $menu_type);
      $page->set('menu_settings', $instance->getConfiguration());
    }
    else {
      $page->set('menu_type', 'none');
      $page->set('menu_settings', []);
    }
    $form_state->setTemporaryValue('wizard', $cached_values);
  }

  /**
   * Gets the menu type the form is currently being built or submitted for.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $default
   *   The menu type to fall back to on the initial build.
   *
   * @return string
   *   The menu type plugin ID, or 'none'.
   */
  protected function getSubmittedMenuType(FormStateInterface $form_state, $default) {
    $value = $form_state->getValue(['menu_type_settings', 'menu_type']);
    if ($value === NULL) {
      $input = $form_state->getUserInput();
      $value = $input['menu_type_settings']['menu_type'] ?? NULL;
    }
    return $value ?? $default;
  }

  /**
   * Instantiates the menu type plugin for a page.
   *
   * @param string $menu_type
   *   The menu type plugin ID, or 'none'.
   * @param \Drupal\page_manager\PageInterface $page
   *   The page being edited.
   *
   * @return \Drupal\page_manager\Plugin\PageManagerMenuInterface|null
   *   The plugin, or NULL when the page has no menu entry.
   */
  protected function createMenuInstance($menu_type, PageInterface $page) {
    if (empty($menu_type) || $menu_type === 'none' || !$this->manager->hasDefinition($menu_type)) {
      return NULL;
    }
    // Only reuse the stored settings when the type has not been switched.
    $configuration = $page->getMenuType() === $menu_type ? $page->getMenuSettings() : [];
    return $this->manager->createInstance($menu_type, $configuration);
  }

  /**
   * Ajax menu settings rendering.
   */
  public function menuSettings(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('.page-manager-menu-type-settings', $form['menu_type_settings']));
    return $response;
  }

}
