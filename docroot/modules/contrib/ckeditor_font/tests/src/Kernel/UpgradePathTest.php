<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor_font\Kernel;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\ckeditor5\Kernel\SmartDefaultSettingsTest;

/**
 * @covers \Drupal\ckeditor_font\Plugin\CKEditor4To5Upgrade\Font
 * @group ckeditor_font
 * @group ckeditor5
 * @requires module ckeditor5
 * @internal
 */
class UpgradePathTest extends SmartDefaultSettingsTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ckeditor_font',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $filter_config = [
      'filter_html' => [
        'status' => 1,
        'settings' => [
          'allowed_html' => '<p> <br> <strong>',
        ],
      ],
    ];
    FilterFormat::create([
      'format' => 'ckeditor_font_both',
      'name' => 'Both ckeditor_font CKE4 buttons',
      'filters' => $filter_config,
    ])->setSyncing(TRUE)->save();
    FilterFormat::create([
      'format' => 'ckeditor_font_font_only',
      'name' => 'Only the Font ckeditor_font CKE4 button',
      'filters' => $filter_config,
    ])->setSyncing(TRUE)->save();
    FilterFormat::create([
      'format' => 'ckeditor_font_fontsize_only',
      'name' => 'Only the FontSize ckeditor_font CKE4 button',
      'filters' => $filter_config,
    ])->setSyncing(TRUE)->save();

    $generate_editor_settings = function (array $ckeditor_font_buttons) {
      return [
        'toolbar' => [
          'rows' => [
            0 => [
              [
                'name' => 'Basic Formatting',
                'items' => [
                  'Bold',
                  'Format',
                ],
              ],
              [
                'name' => 'ckeditor_font buttons',
                'items' => $ckeditor_font_buttons,
              ],
            ],
          ],
        ],
        'plugins' => [
          // The CKEditor 4 plugin functionality has no settings.
        ],
      ];
    };

    Editor::create([
      'format' => 'ckeditor_font_both',
      'editor' => 'ckeditor',
      'settings' => $generate_editor_settings([
        'Font',
        'FontSize',
      ]),
    ])->setSyncing(TRUE)->save();
    Editor::create([
      'format' => 'ckeditor_font_font_only',
      'editor' => 'ckeditor',
      'settings' => $generate_editor_settings([
        'Font',
      ]),
    ])->setSyncing(TRUE)->save();
    Editor::create([
      'format' => 'ckeditor_font_fontsize_only',
      'editor' => 'ckeditor',
      'settings' => $generate_editor_settings([
        'FontSize',
      ]),
    ])->setSyncing(TRUE)->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function provider() {
    parent::provider();

    // The three permutations of possible CKEditor 4 buttons, but all without
    // any settings in CKEditor 4.
    yield "both CKEditor 4 buttons" => [
      'format_id' => 'ckeditor_font_both',
      'filters_to_drop' => [],
      'expected_ckeditor5_settings' => [
        'toolbar' => [
          'items' => [
            'bold',
            '|',
            'fontFamily',
            'fontSize',
          ],
        ],
        'plugins' => [
          'ckeditor_font_font' => [
            'font_sizes' => '',
            'font_names' => '',
            'supportAllFamilyValues' => FALSE,
            'supportAllSizeValues' => FALSE,
          ],
        ],
      ],
      'expected_superset' => '',
      'expected_fundamental_compatibility_violations' => [],
      'expected_db_logs' => [],
      'expected_messages' => [],
    ];
    yield "LTR CKEditor 4 button only" => [
      'format_id' => 'ckeditor_font_font_only',
      'filters_to_drop' => [],
      'expected_ckeditor5_settings' => [
        'toolbar' => [
          'items' => [
            'bold',
            '|',
            'fontFamily',
          ],
        ],
        'plugins' => [
          'ckeditor_font_font' => [
            'font_sizes' => '',
            'font_names' => '',
            'supportAllFamilyValues' => FALSE,
            'supportAllSizeValues' => FALSE,
          ],
        ],
      ],
      'expected_superset' => '',
      'expected_fundamental_compatibility_violations' => [],
      'expected_db_logs' => [],
      'expected_messages' => [],
    ];
    yield "FontSize CKEditor 4 button only" => [
      'format_id' => 'ckeditor_font_fontsize_only',
      'filters_to_drop' => [],
      'expected_ckeditor5_settings' => [
        'toolbar' => [
          'items' => [
            'bold',
            '|',
            'fontSize',
          ],
        ],
        'plugins' => [
          'ckeditor_font_font' => [
            'font_sizes' => '',
            'font_names' => '',
            'supportAllFamilyValues' => FALSE,
            'supportAllSizeValues' => FALSE,
          ],
        ],
      ],
      'expected_superset' => '',
      'expected_fundamental_compatibility_violations' => [],
      'expected_db_logs' => [],
      'expected_messages' => [],
    ];

    // @todo test cases where the CKEditor 4 plugin does have settings.
  }

}
