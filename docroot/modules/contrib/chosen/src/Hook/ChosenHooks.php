<?php

namespace Drupal\chosen\Hook;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\migrate\Exception\RequirementsException;

/**
 * Hook implementations for chosen.
 */
class ChosenHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(&$info) {
    $info['select']['#pre_render'][] = '\Drupal\chosen\ChosenFormRender::preRenderSelect';
    if (\Drupal::moduleHandler()->moduleExists('date')) {
      $info['date_combo']['#pre_render'][] = '\Drupal\chosen\ChosenFormRender::preRenderDateCombo';
    }
    if (\Drupal::moduleHandler()->moduleExists('select_or_other')) {
      $info['select_or_other']['#pre_render'][] = '\Drupal\chosen\ChosenFormRender::preRenderSelectOther';
    }
    if (\Drupal::moduleHandler()->moduleExists('synonyms')) {
      $info['synonyms_entity_select']['#pre_render'][] = '\Drupal\chosen\ChosenFormRender::preRenderSelect';
    }
  }

  /**
   * Implements hook_field_widget_form_alter().
   *
   * Add entity type and bundle information to the widget.
   *
   * @see chosen_pre_render_select()
   */

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(&$element, FormStateInterface $form_state, $context) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $context['items']->getFieldDefinition();
    $element['#entity_type'] = $field_definition->getTargetEntityTypeId();
    if ($bundle = $field_definition->getTargetBundle()) {
      $element['#bundle'] = $bundle;
    }
    elseif ($entity = $context['items']->getEntity()) {
      $element['#bundle'] = $entity->bundle();
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.chosen':
        $output = '';
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Chosen uses the Chosen js plugin to make your < select > elements more user-friendly.') . '</p>';
        $output .= '<h3>' . $this->t('Usage') . '</h3>';
        $output .= '<p>' . $this->t('Configure at: <a href=":structure_types">admin/config/user-interface/chosen</a>', [
          ':structure_types' => Url::fromRoute('chosen.admin')->toString(),
        ]) . '</p>';
        return $output;
    }
  }

  /**
   * Implements hook_migration_plugins_alter().
   */
  #[Hook('migration_plugins_alter')]
  public function migrationPluginsAlter(array &$migrations) {
    // Drupal 12 no longer provides Migrate Drupal or its Drupal 7 source
    // plugins. Preserve the existing Drupal 10/11 migration behavior and do
    // nothing when that optional module is unavailable.
    if (!\Drupal::moduleHandler()->moduleExists('migrate_drupal')) {
      return;
    }

    $drupal_sql_base = 'Drupal\\migrate_drupal\\Plugin\\migrate\\source\\DrupalSqlBase';
    if (!class_exists($drupal_sql_base)) {
      return;
    }

    // Check if the module is enabled on source site.
    try {
      $variable_source = \Drupal::service('plugin.manager.migration')->createStubMigration([
        'id' => 'foo',
        'idMap' => [
          'plugin' => 'null',
        ],
        'source' => [
          'plugin' => 'variable',
          'ignore_map' => TRUE,
        ],
        'destination' => [
          'plugin' => 'null',
        ],
      ])->getSourcePlugin();
      if (!$variable_source instanceof $drupal_sql_base) {
        return;
      }

      // These methods are provided by DrupalSqlBase on Drupal 10 and 11. Use
      // callables so Drupal 12 compatibility tools do not create a hard class
      // or method dependency on APIs that no longer exist there.
      $check_requirements = [$variable_source, 'checkRequirements'];
      $get_system_data = [$variable_source, 'getSystemData'];
      if (!is_callable($check_requirements) || !is_callable($get_system_data)) {
        return;
      }
      call_user_func($check_requirements);
    }
    catch (PluginException $e) {
      // The 'variable' source plugin isn't available because Migrate Drupal
      // isn't enabled. There is nothing we can do.
      return;
    }
    catch (RequirementsException $e) {
      // The source database is not a Drupal 7 database.
      return;
    }
    $system_data = call_user_func($get_system_data);
    if (empty($system_data['module']['chosen']['status'])) {
      unset($migrations['d7_chosen_settings']);
      return;
    }
    $chosen_migration = array_filter(
      $migrations,
      static function ($definition) {
        return $definition['id'] === 'd7_field_instance_widget_settings';
      }
    );
    foreach (array_keys($chosen_migration) as $plugin_id) {
      $migrations[$plugin_id]['process']['options/type'][] = [
        'plugin' => 'chosen',
      ];
    }
  }

}
