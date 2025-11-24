<?php

namespace Drupal\footable\Plugin\views\style;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\footable\Entity\FooTableBreakpoint;
use Drupal\views\Plugin\views\style\Table;

/**
 * Style plugin to render a table as a FooTable.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "footable",
 *   title = @Translation("FooTable"),
 *   help = @Translation("Render a table as a FooTable."),
 *   theme = "views_view_footable",
 *   display_types = { "normal" }
 * )
 */
class FooTable extends Table {

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowClass = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['footable'] = [
      'default' => [
        'expand_all' => FALSE,
        'expand_first' => FALSE,
        'show_header' => TRUE,
        'toggle_column' => 'first',
        'bootstrap' => [
          'striped' => FALSE,
          'bordered' => FALSE,
          'hover' => FALSE,
          'condensed' => FALSE,
        ],
        'component' => [
          'paging' => [
            'enabled' => FALSE,
            'countformat' => '{CP} of {TP}',
            'current' => 1,
            'limit' => 5,
            'position' => 'right',
            'size' => 10,
          ],
          'filtering' => [
            'enabled' => FALSE,
            'delay' => 1200,
            'min' => 3,
            'placeholder' => 'Search',
            'position' => 'right',
            'space' => 'AND',
          ],
          'sorting' => [
            'enabled' => FALSE,
          ],
        ],
      ],
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['footable'] = [
      '#type' => 'details',
      '#title' => $this->t('FooTable settings'),
      '#open' => TRUE,
    ];

    $form['footable']['expand_all'] = [
      '#type' => 'select',
      '#title' => $this->t('Expand all rows'),
      '#description' => $this->t('Whether or not to expand all rows of the table.'),
      '#options' => [
        FALSE => $this->t('Disabled'),
        TRUE => $this->t('Enabled'),
      ],
      '#default_value' => $this->options['footable']['expand_all'],
    ];

    $form['footable']['expand_first'] = [
      '#type' => 'select',
      '#title' => $this->t('Expand first row'),
      '#description' => $this->t('Whether or not to expand the first rows details.'),
      '#options' => [
        FALSE => $this->t('Disabled'),
        TRUE => $this->t('Enabled'),
      ],
      '#default_value' => $this->options['footable']['expand_first'],
      '#states' => [
        'invisible' => [
          ':input[name="style_options[footable][expand_all]"]' => ['value' => 1],
        ],
      ],
    ];

    $form['footable']['show_header'] = [
      '#type' => 'select',
      '#title' => $this->t('Show header'),
      '#description' => $this->t('Whether or not to display a header row in the table.'),
      '#options' => [
        TRUE => $this->t('Yes'),
        FALSE => $this->t('No'),
      ],
      '#default_value' => $this->options['footable']['show_header'],
    ];

    $form['footable']['toggle_column'] = [
      '#title' => $this->t('Expandable column'),
      '#description' => $this->t('Specify which column the toggle is appended to in a row.'),
      '#type' => 'select',
      '#options' => [
        'first' => $this->t('First'),
        'last' => $this->t('Last'),
      ],
      '#default_value' => $this->options['footable']['toggle_column'],
    ];

    // Bootstrap style configuration.
    $config = \Drupal::config('footable.settings');
    if ($config->get('footable_plugin_type') == 'bootstrap') {
      $form['footable']['bootstrap'] = [
        '#type' => 'details',
        '#title' => $this->t('Bootstrap'),
      ];

      $form['footable']['bootstrap']['striped'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Striped'),
        '#default_value' => $this->options['footable']['bootstrap']['striped'],
      ];

      $form['footable']['bootstrap']['bordered'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Bordered'),
        '#default_value' => $this->options['footable']['bootstrap']['bordered'],
      ];

      $form['footable']['bootstrap']['hover'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Hover'),
        '#default_value' => $this->options['footable']['bootstrap']['hover'],
      ];

      $form['footable']['bootstrap']['condensed'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Condensed'),
        '#default_value' => $this->options['footable']['bootstrap']['condensed'],
      ];
    }

    // Components.
    $form['footable']['component'] = [
      '#type' => 'details',
      '#title' => $this->t('Components'),
    ];

    // Filtering.
    $form['footable']['component']['filtering'] = [
      '#type' => 'details',
      '#title' => $this->t('Filtering'),
      '#open' => FALSE,
    ];

    $form['footable']['component']['filtering']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->options['footable']['component']['filtering']['enabled'],
    ];

    $form['footable']['component']['filtering']['delay'] = [
      '#type' => 'number',
      '#title' => $this->t('Delay'),
      '#description' => $this->t('The number of milliseconds before a search input filter is applied after it changes.'),
      '#min' => 1,
      '#default_value' => $this->options['footable']['component']['filtering']['delay'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[footable][component][filtering][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['footable']['component']['filtering']['min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum characters'),
      '#description' => $this->t('The minimum number of characters in the search input before auto applying the filter.'),
      '#min' => 1,
      '#default_value' => $this->options['footable']['component']['filtering']['min'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[footable][component][filtering][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['footable']['component']['filtering']['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#description' => $this->t('The placeholder text displayed within the search input.'),
      '#default_value' => $this->options['footable']['component']['filtering']['placeholder'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[footable][component][filtering][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['footable']['component']['filtering']['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#description' => $this->t('The position of the search input within the filter row.'),
      '#options' => [
        'right' => $this->t('Right'),
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
      ],
      '#default_value' => $this->options['footable']['component']['filtering']['position'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[footable][component][filtering][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['footable']['component']['filtering']['space'] = [
      '#type' => 'select',
      '#title' => $this->t('Space'),
      '#description' => $this->t('How to treat whitespace.'),
      '#options' => [
        'AND' => 'AND',
        'OR' => 'OR',
      ],
      '#default_value' => $this->options['footable']['component']['filtering']['space'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[footable][component][filtering][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Paging.
    $form['footable']['component']['paging'] = [
      '#type' => 'details',
      '#title' => $this->t('Paging'),
      '#open' => FALSE,
    ];

    $form['footable']['component']['paging']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->options['footable']['component']['paging']['enabled'],
    ];

    $form['footable']['component']['paging']['countformat'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Count format'),
      '#description' => $this->t('The string used as a format to generate the count text.'),
      '#default_value' => $this->options['footable']['component']['paging']['countformat'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[footable][component][paging][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['footable']['component']['paging']['current'] = [
      '#type' => 'number',
      '#title' => $this->t('Current'),
      '#description' => $this->t('The page number to display when first initialized.'),
      '#min' => 1,
      '#default_value' => $this->options['footable']['component']['paging']['current'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[footable][component][paging][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['footable']['component']['paging']['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('The maximum number of page links to display in the pagination control.'),
      '#min' => 1,
      '#default_value' => $this->options['footable']['component']['paging']['limit'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[footable][component][paging][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['footable']['component']['paging']['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#description' => $this->t('The position of the pagination control within the paging row.'),
      '#options' => [
        'right' => $this->t('Right'),
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
      ],
      '#default_value' => $this->options['footable']['component']['paging']['position'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[footable][component][paging][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['footable']['component']['paging']['size'] = [
      '#type' => 'number',
      '#title' => $this->t('Size'),
      '#description' => $this->t('The number of rows per page.'),
      '#min' => 1,
      '#default_value' => $this->options['footable']['component']['paging']['size'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[footable][component][paging][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Sorting.
    $form['footable']['component']['sorting'] = [
      '#type' => 'details',
      '#title' => $this->t('Sorting'),
      '#open' => FALSE,
    ];

    $form['footable']['component']['sorting']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->options['footable']['component']['sorting']['enabled'],
    ];

    // Breakpoint configuration.
    $form['footable']['breakpoint'] = [
      '#type' => 'details',
      '#title' => $this->t('Collapsed columns'),
      '#description' => $this->t('Select the "breakpoints" where a particular column should be hidden.'),
      '#open' => TRUE,
    ];

    $breakpoints = [];
    foreach (FooTableBreakpoint::loadAll() as $breakpoint) {
      $breakpoints[$breakpoint->id()] = $breakpoint->label();
    }

    if (!empty($breakpoints)) {
      foreach ($this->displayHandler->getFieldLabels() as $name => $label) {
        $form['footable']['breakpoint'][$name] = [
          '#title' => Html::escape($label),
          '#type' => 'checkboxes',
          '#options' => $breakpoints,
          '#default_value' => isset($this->options['footable']['breakpoint'][$name]) ? $this->options['footable']['breakpoint'][$name] : NULL,
          '#multiple' => TRUE,
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $breakpoints = FALSE;
    foreach ($form_state->getValue(['style_options', 'footable', 'breakpoint'], []) as $breakpoint) {
      if (!empty(array_filter($breakpoint))) {
        $breakpoints = TRUE;
        break;
      }
    }

    if (!$breakpoints) {
      $form_state->setErrorByName('style_options][footable][breakpoint', $this->t('You need to have atleast one column that has a breakpoint.'));
    }
  }

}
