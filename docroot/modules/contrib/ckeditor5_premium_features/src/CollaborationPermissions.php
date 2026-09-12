<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Entity\FilterFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions of the filter module.
 */
class CollaborationPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public const COMMENTS_WRITE = 'comments_write';
  public const COMMENTS_ADMIN = 'comments_admin';
  public const DOCUMENT_WRITE = 'document_write';

  public const COMMON_PERMISSIONS = [
    self::COMMENTS_WRITE,
    self::COMMENTS_ADMIN,
    self::DOCUMENT_WRITE,
  ];

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Permissions available in current module.
   *
   * @var array
   */
  protected $permissions;

  /**
   * Constructs a new CollaborationPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactory $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->permissions = $this->permissions ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * Returns list of permissions types handled by given module.
   *
   * @return array
   */
  public static function getModulePermissions(): array {
    return static::COMMON_PERMISSIONS;
  }

  /**
   * Returns an array of filter permissions.
   *
   * @return array
   */
  public function permissions(): array {
    return [];
  }

  protected function getPermissions($premiumPlugins, $availablePermissions): array {
    $permissions = [];

    /** @var \Drupal\filter\FilterFormatInterface[] $formats */
    $formats = $this->entityTypeManager->getStorage('filter_format')->loadByProperties(['status' => TRUE]);
    uasort($formats, 'Drupal\Core\Config\Entity\ConfigEntityBase::sort');
    foreach ($formats as $format) {
      $editorConfig = $this->configFactory->get('editor.editor.' . $format->id());
      if ($editorConfig->isNew()) {
        continue;
      }
      $editorSettings = $editorConfig->get('settings');
      $plugins = array_keys($editorSettings["plugins"] ?? []);
      if (empty(array_intersect($plugins, $premiumPlugins))) {
        continue;
      }

      if ($format->getPermissionName()) {
        foreach ($availablePermissions as $collaborationPermission) {
          $description = $this->getPermissionDescription($collaborationPermission);
          $permissions[$this->getPermissionName($format, $collaborationPermission)] = [
            'title' => $this->t('Collaboration @permission for the <a href=":url">@format</a> text format',
              [
                ':url' => $format->toUrl()->toString(),
                '@format' => $format->label(),
                '@permission' => $this->getPermissionLabel($collaborationPermission),
              ]
            ),
            'description' => [
              '#prefix' => '<em>',
              '#markup' => $description,
              '#suffix' => '</em>',
            ],
            // This permission is generated on behalf of $format text format,
            // therefore add this text format as a config dependency.
            'dependencies' => [
              $format->getConfigDependencyKey() => [
                $format->getConfigDependencyName(),
              ],
            ],
          ];
        }
      }
    }
    return $permissions;
  }

  /**
   * Returns label of the collaboration permission.
   *
   * @param string $permission
   *   Collaboration permission name.
   *
   * @return string|TranslatableMarkup
   *   Permission label
   */
  protected function getPermissionLabel(string $permission): string|TranslatableMarkup {
    return match ($permission) {
      self::COMMENTS_WRITE => $this->t('Write comments'),
      self::COMMENTS_ADMIN => $this->t('Administer comments'),
      self::DOCUMENT_WRITE => $this->t('Edit content'),
      default => '',
    };
  }

  /**
   * Returns description for the collaboration permission.
   *
   * @param string $permission
   *   Collaboration permission name.
   *
   * @return string|TranslatableMarkup
   *   Permission description
   */
  protected function getPermissionDescription(string $permission): string|TranslatableMarkup {
    return match ($permission) {
      self::COMMENTS_WRITE => $this->t('Allows to add, modify, and delete own collaboration comments. It also allows to resolve all collaboration comment threads.'),
      self::COMMENTS_ADMIN => $this->t('Allows to add, modify, and delete own collaboration comments. It also allows to resolve and delete all collaboration comment threads.'),
      self::DOCUMENT_WRITE => $this->t('Allows to add, evaluate suggestions and make non-suggestion changes.'),
      default => '',
    };
  }

  /**
   * Returns a permission name based on filter and collaboration permission.
   *
   * @param \Drupal\filter\Entity\FilterFormat $format
   *   The filter format entity.
   * @param string $permission
   *   The collaboration permission string.
   *
   * @return string
   *   The collaboration permission name.
   */
  public static function getPermissionName(FilterFormat $format, string $permission): string {
    $formatPermission = $format->getPermissionName();
    return $formatPermission . ' with collaboration ' . $permission;
  }

}
