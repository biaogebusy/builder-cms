<?php

namespace Drupal\fpa;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\Url;

/**
 * Class FpaFormBuilder.
 *
 * The main class for building the FPA page.
 *
 * @package Drupal\fpa
 */
class FpaFormBuilder {

  /**
   * Gets required memory.
   *
   * @return int
   *   Number of bytes of ram required to render the permissions form.
   */
  public static function getRequiredMemory($suffix = '') {
    $permission = \Drupal::service('user.permissions');
    $permissions_count = count($permission->getPermissions());
    $user_roles_count = count(user_roles());
    $page_ram_required = (9 * 1024 * 1024);
    // Takes ~26kb per row without any checkboxes.
    $permission_row_overhead = 27261.028783658;
    $permissions_ram_required = $permissions_count * $permission_row_overhead;
    // Determined by checking peak ram on permissions page,
    // over several different number of visible roles.
    $bytes_per_checkbox = 18924.508820799;
    $checkboxes_ram_required = $permissions_count * $user_roles_count * $bytes_per_checkbox;
    $output = (int) ($page_ram_required + $permissions_ram_required + $checkboxes_ram_required);
    if (!empty($suffix)) {
      return $output . $suffix;
    }
    return $output;
  }

  /**
   * Checks memory limit.
   *
   * @return bool
   *   Returns true of false.
   */
  public static function checkMemoryLimit() {
    $permissions_memory_required = static::getRequiredMemory('b');
    $memory_limit = ini_get('memory_limit');
    return ((!$memory_limit) || ($memory_limit == -1) || (Bytes::toNumber($memory_limit) >= Bytes::toNumber($permissions_memory_required)));
  }

  /**
   * Builds the FPA pages.
   *
   * @return mixed
   *   Returns render array.
   */
  public static function buildFpaPage() {
    $form = \Drupal::service('form_builder')->getForm('\Drupal\user\Form\UserPermissionsForm');

    $render = static::buildTable($form);
    $render['#attached']['library'][] = 'fpa/fpa.permissions';
    $render['#attached']['drupalSettings']['fpa'] = [
      'attr' => [
        'permission' => FPA_ATTR_PERMISSION,
        'module' => FPA_ATTR_MODULE,
        'role' => FPA_ATTR_ROLE,
        'checked' => FPA_ATTR_CHECKED,
        'not_checked' => FPA_ATTR_NOT_CHECKED,
        'system_name' => FPA_ATTR_SYSTEM_NAME,
      ],
    ];

    return $render;
  }

  /**
   * Builds the permissions table.
   *
   * @param array $form
   *   Form element.
   *
   * @return mixed
   *   Returns render array.
   */
  protected static function buildTable($form) {
    $renderer = \Drupal::service('renderer');

    $nameless_checkbox = [
      '#type' => 'html_tag',
      '#tag' => 'input',
      '#attributes' => [
        'type' => 'checkbox',
        'class' => [
          // Prevents Drupal core Drupal.behaviors.permissions.toggle from applying.
          'rid-anonymous',
          'form-checkbox',
          'fpa-checkboxes-toggle',
        ],
      ],
    ];

    $dummy_checkbox = [
      '#type' => 'html_tag',
      '#tag' => 'input',
      '#attributes' => [
        'type' => 'checkbox',
        'disabled' => 'disabled',
        'checked' => 'checked',
        'title' => t('This permission is inherited from the authenticated user role.'),
        'class' => [
          'dummy-checkbox',
        ],
      ],
    ];

    $dummy_checkbox_output = $renderer->render($dummy_checkbox);

    $permission_col_template = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'fpa-permission-container',
        ],
      ],
      'description' => [],
      'checkbox_cell' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'fpa-row-toggle-container',
          ],
        ],
        'checkbox_form_item' => [
          '#type' => 'container',
          '#attributes' => [
            'title' => t('Toggle visible checkboxes in this row.'),
            'class' => [
              'form-item',
              'form-type-checkbox',
            ],
          ],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#attributes' => [
              'class' => [
                'visually-hidden',
              ],
            ],
            '#value' => 'test',
          ],
          'checkbox' => $nameless_checkbox,
        ],
      ],
    ];

    $roles = \Drupal::service('entity_type.manager')->getStorage('user_role')->loadMultiple();

    // Prepare role names processed by Html::getClass() ahead of time.
    $roles_attr_values = [];
    foreach ($roles as $role) {
      $roles_attr_values[$role->get('id')] = Html::getClass($role->get('label'));
    }

    // Lists for wrapper.
    $modules = [];
    $user_roles = [];

    // Index of current module row.
    $module = NULL;

    // Row counter.
    $i = 0;

    $rows = [];

    foreach (Element::children($form['permissions']) as $key) {

      // Row template.
      $row = [
        // Array of table cells.
        'data' => [],
        // HTML attribute on table row tag.
        'title' => [],
        // HTML attribute on table row tag.
        FPA_ATTR_MODULE => [],
        // HTML attribute on table row tag.
        FPA_ATTR_PERMISSION => [],
        FPA_ATTR_CHECKED => [],
        FPA_ATTR_NOT_CHECKED => [],
      ];

      $current_element = $form['permissions'][$key];
      hide($form['permissions'][$key]);
      $sub_children = Element::children($current_element);

      // Determine if row is module or permission.
      if (is_numeric($sub_children[0])) {
        // Module row.
        $row['class'][] = 'fpa-module-row';

        // Mark current row with escaped module name.
        $row[FPA_ATTR_MODULE] = [
          // System name.
          0 => $key,
          // Readable name.
          1 => strip_tags($current_element[0]['#markup']),
        ];

        // Readable.
        hide($form['permissions'][$key][0]);
        $row['data'][] = [
          'data' => $form['permissions'][$key][0],
          'class' => ['module'],
          'id' => 'module-' . $key,
          'colspan' => count($form['role_names']['#value']) + 1,
        ];

        $row['title'] = [$key];

        $row[FPA_ATTR_SYSTEM_NAME] = $row[FPA_ATTR_MODULE][0];

        $classes = [];
        foreach ($row[FPA_ATTR_MODULE] as $item) {
          $classes[] = Html::getClass($item);
        }
        $row[FPA_ATTR_MODULE] = array_unique($classes);

        // Add modules to left-side modules list.
        $modules[$row[FPA_ATTR_MODULE][0]] = [
          'text' => strip_tags($current_element[0]['#markup']),
          'title' => [$key],
          FPA_ATTR_MODULE => $row[FPA_ATTR_MODULE],
          FPA_ATTR_PERMISSION => [],
        ];

        // Save row number for current module.
        $module = $i;
      }
      else {
        // Permission row.
        $row['class'][] = 'fpa-permission-row';
        $roles_keys = array_keys($roles_attr_values);

        $permission_system_name = (string) $form['permissions'][$key]['description']['#context']['title'];
        /*
        @todo Find out why this was done in D7
        $permission_system_name = '';
        // Might be empty if no modules are displayed in Permissions Filter module.
        if (!empty($sub_children[$roles_keys[0]])) {
          $permission_system_name = $sub_children[$roles_keys[0]['#return_value'];
        }
        */

        $label = $permission_col_template;

        $label['description'] = $current_element['description'];

        /*
        @todo Work on integration with permission filter module
        // Permissions filter might cause no Roles to display.
        if (count(element_children($form['checkboxes'])) == 0) {
          unset($label['checkbox_cell']);
        }
        */

        // Readable.
        $row['data'][] = [
          'data' => $label,
          'class' => ['permission'],
        ];

        foreach ($roles_keys as $rid) {
          $checkbox = $form['permissions'][$key][$rid];
          hide($form['permissions'][$key][$rid]);
          $checkbox['#title'] = $roles[$rid]->get('label') . ': ' . $checkbox['#title'];
          $checkbox['#title_display'] = 'invisible';

          // Filter permissions strips role id class from checkbox. Used by Drupal core functionality.
          $checkbox['#attributes']['class'][] = 'rid-' . $rid;

          // Set authenticated role behavior class on page load.
          if ($rid == 'authenticated' && $checkbox['#checked'] === TRUE) {
            $row['class'][] = 'fpa-authenticated-role-behavior';
          }

          // For all roles that inherit permissions from 'authenticated user' role, add in dummy checkbox for authenticated role behavior.
          // @todo Needs further testing.
          if ($rid != 'anonymous' && $rid != 'authenticated') {
            // '#suffix' doesn't have wrapping HTML like '#field_suffix'.
            $checkbox['#suffix'] = $dummy_checkbox_output;
          }

          // Add rid's to row attribute for checked status filter.
          if ($checkbox['#checked'] === TRUE) {
            $row[FPA_ATTR_CHECKED][] = $rid;
          }
          else {
            $row[FPA_ATTR_NOT_CHECKED][] = $rid;
          }

          $row['data'][] = [
            'data' => $checkbox,
            'class' => [
              'checkbox',
            ],
            'title' => [
              $roles[$rid]->get('label'),
            ],
            // For role filter.
            FPA_ATTR_ROLE => [
              $rid,
            ],
          ];
        }

        if (!empty($rid)) {
          $row['title'] = [
            $key,
          ];

          $row[FPA_ATTR_SYSTEM_NAME] = [
            $key,
          ];
        }

        // Mark current row with escaped permission name.
        $row[FPA_ATTR_PERMISSION] = [
          // Permission system name.
          0 => $permission_system_name,
          // Readable description.
          1 => (string) $form['permissions'][$key]['description']['#context']['title'],
        ];

        // Mark current row with current module.
        $row[FPA_ATTR_MODULE] = $rows[$module][FPA_ATTR_MODULE];

        $classes = [];
        foreach ($row[FPA_ATTR_PERMISSION] as $item) {
          $classes[] = Html::getClass($item);
        }
        $row[FPA_ATTR_PERMISSION] = array_unique($classes);

        // Add current permission to current module row.
        $rows[$module][FPA_ATTR_PERMISSION] = array_merge($rows[$module][FPA_ATTR_PERMISSION], $row[FPA_ATTR_PERMISSION]);

        $rows[$module][FPA_ATTR_CHECKED] = array_unique(array_merge($rows[$module][FPA_ATTR_CHECKED], $row[FPA_ATTR_CHECKED]));
        $rows[$module][FPA_ATTR_NOT_CHECKED] = array_unique(array_merge($rows[$module][FPA_ATTR_NOT_CHECKED], $row[FPA_ATTR_NOT_CHECKED]));

        $modules[$rows[$module][FPA_ATTR_MODULE][0]][FPA_ATTR_PERMISSION][] = $row[FPA_ATTR_PERMISSION];
      }

      $rows[$i++] = $row;
    }

    $reset_button = [
      '#type' => 'html_tag',
      '#tag' => 'input',
      '#attributes' => [
        'type' => 'reset',
        'class' => 'form-submit',
        'value' => t('Reset changes'),
      ],
    ];

    // If there is no submit button, don't add the reset button.
    if (count(Element::children($form['actions'])) > 0) {

      // Have the reset button appear before the submit button.
      array_unshift($form['actions'], $reset_button);
    }

    $actions_output = [];
    foreach (Element::children($form['actions']) as $key) {
      $actions_output[] = $form['actions'][$key];
    }

    $header = [];

    $header[] = [
      'data' => [
        'label' => [
          '#type' => 'markup',
          '#markup' => t('Permission'),
        ],
        'actions' => $actions_output,
      ],
    ];

    foreach ($form['role_names']['#value'] as $rid => $label) {

      $header[] = [
        'data' => [
          'label' => [
            '#type' => 'markup',
            '#markup' => $label,
          ],
          'checkbox' => $nameless_checkbox,
        ],
        'class' => [
          'checkbox',
        ],
        'title' => [
          $label,
        ],
        FPA_ATTR_ROLE => [
          $rid,
        ],
      ];
      $user_roles[$rid] = $label;
    }

    $table = [
      'header' => $header,
      'rows' => $rows,
    ];

    $table_wrapper = static::buildTableWrapper($table, $modules, $user_roles, $actions_output);

    foreach (Element::children($form) as $key) {
      if ($key == 'actions' || $key == 'permissions') {
        continue;
      }
      $table_wrapper[$key] = $form[$key];
    }

    unset($form['role_names']);
    unset($form['permissions']);
    unset($form['actions']);
    $form['fpa_container'] = $table_wrapper;

    return $form;
  }

  /**
   * Build table wrapper.
   *
   * @param array $permissions_table
   *   Permissions table.
   * @param array $modules
   *   Array of modules.
   * @param array $user_roles
   *   Array of user roles.
   * @param array $actions_output
   *   Actions.
   *
   * @return array
   *   Returns render array.
   */
  protected static function buildTableWrapper(array $permissions_table, array $modules, array $user_roles, array $actions_output) {
    $renderer = \Drupal::service('renderer');

    // @todo Find out if there is a sf way to do this.
    $same_page = FALSE;
    if (isset($_SERVER['HTTP_REFERER']) && isset($_GET['q'])) {
      $same_page = trim(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH), '/') == $_GET['q'];
    }

    $render = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'fpa-container',
        ],
      ],
    ];

    $hiders = [
      'fpa-hide-descriptions' => [
        'hide' => t('Hide descriptions'),
        'show' => t('Show descriptions'),
      ],
      'fpa-hide-system-names' => [
        'hide' => t('Hide system names'),
        'show' => t('Show system names'),
      ],
    ];

    $render['#attributes']['class'][] = 'fpa-hide-system-names';

    $hide_container = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'fpa-toggle-container',
        ],
      ],
    ];

    foreach ($hiders as $hide_class => $labels) {
      $url = Url::fromUri('base:', [
        'fragment' => ' ',
        'external' => TRUE,
        'attributes' => array_merge($labels, [
          'fpa-toggle-class' => $hide_class,
        ]),
      ]);
      $hide_container[$hide_class] = Link::fromTextAndUrl('', $url)->toRenderable();
    }

    $render['hide_container'] = $hide_container;

    $wrapper = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'fpa-wrapper',
        ],
      ],
    ];

    $render['wrapper'] = &$wrapper;

    // <style /> block template.
    $style_template = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          // Override on specific block.
          'style-wrapper-class-name',
        ],
      ],
    ];

    $style_template['style'] = [
      '#type' => 'html_tag',
      '#tag' => 'style',
      '#attributes' => [
        'type' => [
          'text/css',
        ],
      ],
      // #value needed for closing tag.
      '#value' => '',
    ];

    // <style /> block for role filtering.
    $wrapper['role_styles'] = $style_template;
    $wrapper['role_styles']['#attributes']['class'][0] = 'fpa-role-styles';

    // <style /> block for permission filtering.
    $wrapper['perm_styles'] = $style_template;
    $wrapper['perm_styles']['#attributes']['class'][0] = 'fpa-perm-styles';

    // Left section contains module list and form submission button.
    $left_section = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'fpa-left-section',
        ],
      ],
    ];

    $wrapper['left_section'] = &$left_section;

    // Right section contains filter form and permissions table.
    $right_section = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'fpa-right-section',
        ],
      ],
    ];

    $wrapper['right_section'] = &$right_section;

    $module_template = [
      '#wrapper_attributes' => [
        FPA_ATTR_MODULE => [],
        FPA_ATTR_PERMISSION => [],
      ],
      'data' => [
        '#type' => 'container',
        '#attributes' => [],

        'link' => NULL,

        'counters' => [],

        'total' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'class' => ['fpa-perm-total'],
            'fpa-total' => 0,
          ],
          // #value needed for closing tag.
          '#value' => '',
        ],
      ],
    ];

    $counter_template = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [
        'class' => ['fpa-perm-counter'],
        // Counters only count permissions match.
        FPA_ATTR_PERMISSION => [],
      ],
      // #value required for closing tag.
      '#value' => '',
    ];

    $items = [];

    $all_modules = [
      'text' => t('All modules'),
      FPA_ATTR_MODULE => [],
      FPA_ATTR_PERMISSION => [],
    ];

    array_unshift($modules, $all_modules);

    $all_modules_counters = [];

    foreach ($modules as $module) {

      $module_item = $module_template;

      $module_item['#wrapper_attributes'][FPA_ATTR_MODULE] = $module[FPA_ATTR_MODULE];
      $module_item['#wrapper_attributes'][FPA_ATTR_PERMISSION] = array_reduce($module[FPA_ATTR_PERMISSION], 'array_merge', []);

      // Use link for accessibility and tabability.
      $options = [
        'fragment' => 'all',
      ];

      if (!empty($module['title'])) {
        $options['fragment'] = 'module-' . $module['title'][0];
        $options['attributes']['title'] = $module['title'][0];
      }

      $module_item['data']['link'] = Link::createFromRoute($module['text'], 'user.admin_permissions', [], $options)->toRenderable();

      foreach ($module[FPA_ATTR_PERMISSION] as $module_perm) {
        $counter_item = $counter_template;
        $counter_item['#attributes'][FPA_ATTR_PERMISSION] = $module_perm;
        $all_modules_counters[] = $counter_item;
        $module_item['data']['counters'][] = $counter_item;
      }

      $module_item['data']['total']['#attributes']['fpa-total'] = count($module[FPA_ATTR_PERMISSION]);

      $items[] = $module_item;
    }

    $items[0]['data']['counters'] = $all_modules_counters;
    $items[0]['data']['total']['#attributes']['fpa-total'] = count($all_modules_counters);

    $left_section['list'] = [
      '#items' => $items,
      '#theme' => 'item_list',
    ];

    $left_section['buttons'] = $actions_output;

    $filter_form = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'fpa-filter-form',
        ],
      ],
    ];

    $clear_button = [
      '#type' => 'html_tag',
      '#tag' => 'input',
      '#attributes' => [
        'type' => [
          'button',
        ],
        'class' => [
          'fpa-clear-search',
          'form-submit',
        ],
        'value' => 'Clear filter',
      ],
    ];

    $default_filter = '';

    if (!empty($_GET['fpa_perm'])) {
      $default_filter = $_GET['fpa_perm'];
    }

    if (!empty($_COOKIE['fpa_filter']) && $same_page) {
      $default_filter = $_COOKIE['fpa_filter'];
    }

    $filter_form['permission_module_filter'] = [
      '#type' => 'textfield',
      '#title' => t('Filter:'),
      '#size' => 25,
      '#field_suffix' => $renderer->render($clear_button),
      '#attributes' => [
        'placeholder' => [
          'permission@module',
        ],
        'autofocus' => 'autofocus',
      ],
      '#value' => $default_filter,
      '#description' => t('<p>Enter in the format of "permission@module",</p><p>e.g. <em>admin@system</em> will show only permissions with the<br>text "admin" in modules with the text "system".</p><p>This will also match on system name of a permission.</p>'),
      '#description_display' => TRUE,
    ];

    // Populate the permission filter styles.
    $matches = [];
    preg_match('/^\s*([^@]*)@?(.*?)\s*$/i', $filter_form['permission_module_filter']['#value'], $matches);
    // Remove whole match item.
    array_shift($matches);

    $safe_matches = [];
    foreach ($matches as $match) {
      $safe_matches[] = Html::getClass($match);
    }

    $module_match = !empty($_COOKIE['module_match']) ? $_COOKIE['module_match'] : '*=';

    $filters = [
      mb_strlen($safe_matches[0]) > 0 ? ('[' . FPA_ATTR_PERMISSION . '*="' . $safe_matches[0] . '"]') : '',
      mb_strlen($safe_matches[1]) > 0 ? ('[' . FPA_ATTR_MODULE . $module_match . '"' . $safe_matches[1] . '"]') : '',
    ];

    $filter_styles = [
      '.fpa-table-wrapper tr[' . FPA_ATTR_MODULE . ']{display: none;}',
      '.fpa-table-wrapper tr[' . FPA_ATTR_MODULE . ']',
      $filters[0],
      $filters[1],
      '{display: table-row;}',
      '.fpa-perm-counter{display: none;}',
      '.fpa-perm-counter',
      $filters[0],
      '{display: inline;}',
      '.fpa-left-section li[' . FPA_ATTR_MODULE . ']',
      mb_strlen($filters[1]) > 0 ? $filters[1] : '[' . FPA_ATTR_MODULE . '=""]',
      '{margin-right:-1px; background-color: white; border-right: solid 1px transparent;}',
    ];

    $wrapper['perm_styles']['style']['#value'] = implode('', $filter_styles);

    $cookie_roles = (!empty($_COOKIE['fpa_roles']) && $same_page) ? json_decode($_COOKIE['fpa_roles']) : [];

    $options = [
      '*' => t('--All Roles'),
    ];

    if (!empty($user_roles)) {
      // Preserves keys.
      $options += $user_roles;
    }

    if (in_array('*', $cookie_roles)) {
      $cookie_roles = ['*'];
    }

    $filter_form['role_filter'] = [
      '#type' => 'select',
      '#title' => t('Roles:'),
      '#description' => t('Select which roles to display.<br>Ctrl+click to select multiple.'),
      '#description_display' => TRUE,
      '#size' => 5,
      '#options' => $options,
      '#attributes' => [
        'multiple' => 'multiple',
        // Keep browser from populating this from 'cached' input.
        'autocomplete' => 'off',
      ],
      '#value' => count(array_intersect($cookie_roles, array_keys($options))) > 0 ? $cookie_roles : ['*'],
    ];

    /*
     * Populate the roles styles.
     */
    if (!in_array('*', $filter_form['role_filter']['#value'])) {

      $role_styles = [
        '.fpa-table-wrapper [' . FPA_ATTR_ROLE . '] {display: none;}',
      ];

      foreach ($filter_form['role_filter']['#value'] as $value) {

        $role_styles[] = '.fpa-table-wrapper [' . FPA_ATTR_ROLE . '="' . $value . '"] {display: table-cell;}';
      }

      $role_styles[] = '.fpa-table-wrapper [' . FPA_ATTR_ROLE . '="' . end($filter_form['role_filter']['#value']) . '"] {border-right: 1px solid #bebfb9;}';

      $wrapper['role_styles']['style']['#value'] = implode('', $role_styles);
    }

    $checked_status = [
      '#type' => 'checkboxes',
      '#title' => t('Display permissions that are:'),
      '#options' => [
        FPA_ATTR_CHECKED => t('Checked'),
        FPA_ATTR_NOT_CHECKED => t('Not Checked'),
      ],
      '#attributes' => [],
      '#title_display' => 'before',
      '#id' => 'permissions_checkboxes',
      '#description' => t('Applies to all visible roles.<br />Unsaved changes are not counted.<br />Most effective when a single role is visible.<br />Empty module rows sometimes display when used with permission filter.'),
      '#description_display' => TRUE,
    ];

    $checked_status_keys = array_keys($checked_status['#options']);

    $checked_status['#value'] = array_combine($checked_status_keys, $checked_status_keys);

    $pseudo_form = [];
    $filter_form['checked_status'] = Checkboxes::processCheckboxes($checked_status, new FormState(), $pseudo_form);

    foreach (Element::children($filter_form['checked_status']) as $key) {
      $filter_form['checked_status'][$key]['#checked'] = TRUE;
    }

    $right_section['filter_form'] = $filter_form;

    $table_wrapper = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'fpa-table-wrapper',
        ],
      ],
    ];

    $table_wrapper['table'] = [
      '#theme' => 'table',
      '#header' => $permissions_table['header'],
      '#rows' => $permissions_table['rows'],
      '#attributes' => [
        'id' => 'permissions',
      ],
    ];

    // Show after full table HTML is loaded.
    // Reduces progressive table load reflow/repaint.
    $table_wrapper['show_table'] = [
      '#type' => 'html_tag',
      '#tag' => 'style',
      '#attributes' => [
        'type' => [
          'text/css',
        ],
      ],
      '#value' => '#permissions {display: table;} .fpa-table-wrapper {background: none;}',
    ];

    $table_wrapper['buttons'] = $actions_output;

    $right_section['table_wrapper'] = $table_wrapper;

    return $render;
  }

}
