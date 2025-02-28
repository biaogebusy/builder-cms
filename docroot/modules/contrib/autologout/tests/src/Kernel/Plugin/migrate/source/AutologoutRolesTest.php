<?php

namespace Drupal\Tests\autologout\Kernel\Plugin\migrate\source;

use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;

/**
 * @covers \Drupal\autologout\Plugin\migrate\source\AutologoutRoles
 * @group autologout
 */
class AutologoutRolesTest extends MigrateSqlSourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'migrate_drupal', 'autologout'];

  /**
   * {@inheritdoc}
   */
  public function providerSource() {
    $tests = [];

    $tests[0]['source_data']['variable'] = [
      [
        'name' => 'autologout_role_2_timeout',
        'value' => 's:4:"1800";',
      ],
      [
        'name' => 'autologout_role_2',
        'value' => 'i:0;',
      ],
      [
        'name' => 'autologout_role_3_timeout',
        'value' => 's:4:"2000";',
      ],
      [
        'name' => 'autologout_role_3',
        'value' => 'i:1;',
      ],
    ];
    $tests[0]['source_data']['role'] = [
      [
        'rid' => '2',
        'name' => 'authenticated user',
      ],
      [
        'rid' => '3',
        'name' => 'administrator',
      ],
    ];

    // The expected results.
    $tests[0]['expected_data'] = [
      [
        'enabled' => FALSE,
        'timeout' => '1800',
        'role' => 'authenticated user',
      ],
      [
        'enabled' => TRUE,
        'timeout' => '2000',
        'role' => 'administrator',
      ],
    ];

    return $tests;
  }

}
