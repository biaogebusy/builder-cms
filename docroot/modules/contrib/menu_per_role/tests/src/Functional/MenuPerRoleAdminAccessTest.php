<?php

declare(strict_types = 1);

namespace Drupal\Tests\menu_per_role\Functional;

use Drupal\Core\Session\AccountInterface;

/**
 * Test access control to menu links in BO.
 *
 * @group menu_per_role
 */
class MenuPerRoleAdminAccessTest extends MenuPerRoleAccessTest {

  /**
   * {@inheritdoc}
   */
  protected $user1Permissions = [
    'access content',
    'administer menu',
  ];

  /**
   * {@inheritdoc}
   */
  protected $user2Permissions = [
    'access content',
    'administer menu',
    'bypass menu_per_role access admin',
  ];

  /**
   * {@inheritdoc}
   */
  protected $testPath = 'admin/structure/menu/manage/menu1';

  /**
   * {@inheritdoc}
   */
  protected $adminBypassSetting = 'admin_bypass_access_admin';

  /**
   * Check if users have access to menu links.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testRoleAccess(): void {
    // Test "Show roles" by role.
    $this->prepareMenuLinkAndExpectations('link 1 - show anonymous', [AccountInterface::ANONYMOUS_ROLE], [], [
      'user1' => FALSE,
      'user2' => TRUE,
    ]);
    $this->prepareMenuLinkAndExpectations('link 2 - show role 1', [$this->user1Role], [], [
      'user1' => TRUE,
      'user2' => TRUE,
    ]);
    $this->prepareMenuLinkAndExpectations('link 3 - show role 2', [$this->user2Role], [], [
      'user1' => FALSE,
      'user2' => TRUE,
    ]);
    $this->prepareMenuLinkAndExpectations('link 4 - show role 3', [$this->user3Role], [], [
      'user1' => FALSE,
      'user2' => TRUE,
    ]);

    // Test "Hide roles" by role.
    $this->prepareMenuLinkAndExpectations('link 5 - hide anonymous', [], [AccountInterface::ANONYMOUS_ROLE], [
      'user1' => TRUE,
      'user2' => TRUE,
    ]);
    $this->prepareMenuLinkAndExpectations('link 6 - hide role 1', [], [$this->user1Role], [
      'user1' => FALSE,
      'user2' => TRUE,
    ]);
    $this->prepareMenuLinkAndExpectations('link 7 - hide role 2', [], [$this->user2Role], [
      'user1' => TRUE,
      'user2' => TRUE,
    ]);
    $this->prepareMenuLinkAndExpectations('link 8 - hide role 3', [], [$this->user3Role], [
      'user1' => TRUE,
      'user2' => TRUE,
    ]);

    // Test combinations of roles to show.
    $this->prepareMenuLinkAndExpectations(
      'link 9 - show anonymous and role 1',
      [
        AccountInterface::ANONYMOUS_ROLE,
        $this->user1Role,
      ],
      [],
      [
        'user1' => TRUE,
        'user2' => TRUE,
      ]
    );
    $this->prepareMenuLinkAndExpectations(
      'link 10 - show role 1 and role 2',
      [
        $this->user1Role,
        $this->user2Role,
      ],
      [],
      [
        'user1' => TRUE,
        'user2' => TRUE,
      ]
    );
    $this->prepareMenuLinkAndExpectations(
      'link 11 - show role 2 and role 3',
      [
        $this->user2Role,
        $this->user3Role,
      ],
      [],
      [
        'user1' => FALSE,
        'user2' => TRUE,
      ]
    );
    $this->prepareMenuLinkAndExpectations(
      'link 12 - show anonymous and role 3',
      [
        AccountInterface::ANONYMOUS_ROLE,
        $this->user3Role,
      ],
      [],
      [
        'user1' => FALSE,
        'user2' => TRUE,
      ]
    );

    // Test combinations of roles to hide.
    $this->prepareMenuLinkAndExpectations(
      'link 13 - hide anonymous and role 1',
      [],
      [
        AccountInterface::ANONYMOUS_ROLE,
        $this->user1Role,
      ],
      [
        'user1' => FALSE,
        'user2' => TRUE,
      ]
    );
    $this->prepareMenuLinkAndExpectations(
      'link 14 - hide role 1 and role 2',
      [],
      [
        $this->user1Role,
        $this->user2Role,
      ],
      [
        'user1' => FALSE,
        'user2' => TRUE,
      ]
    );
    $this->prepareMenuLinkAndExpectations(
      'link 15 - hide role 1 and role 3',
      [],
      [
        $this->user1Role,
        $this->user3Role,
      ],
      [
        'user1' => FALSE,
        'user2' => TRUE,
      ]
    );
    $this->prepareMenuLinkAndExpectations(
      'link 16 - hide anonymous and role 2',
      [],
      [
        AccountInterface::ANONYMOUS_ROLE,
        $this->user2Role,
      ],
      [
        'user1' => TRUE,
        'user2' => TRUE,
      ]
    );

    // Test combinations of roles to show and hide.
    $this->prepareMenuLinkAndExpectations('link 17 - show anonymous and hide role 1', [AccountInterface::ANONYMOUS_ROLE], [$this->user1Role], [
      'user1' => FALSE,
      'user2' => TRUE,
    ]);
    $this->prepareMenuLinkAndExpectations('link 18 - show role 2 and hide role 1', [$this->user2Role], [$this->user1Role], [
      'user1' => FALSE,
      'user2' => TRUE,
    ]);
    $this->prepareMenuLinkAndExpectations(
      'link 19 - show anonymous and hide role 1 and role 3',
      [AccountInterface::ANONYMOUS_ROLE],
      [
        $this->user1Role,
        $this->user3Role,
      ],
      [
        'user1' => FALSE,
        'user2' => TRUE,
      ]
    );
    $this->prepareMenuLinkAndExpectations(
      'link 20 - show anonymous and role 1 and role 3 and hide role 2',
      [
        AccountInterface::ANONYMOUS_ROLE,
        $this->user1Role,
        $this->user3Role,
      ],
      [$this->user2Role],
      [
        'user1' => TRUE,
        'user2' => TRUE,
      ]
    );

    $this->linksAccessTest();
  }

}
