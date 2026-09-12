<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_restricted_editing;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions of the filter module.
 */
class RestrictedEditingPermissions implements ContainerInjectionInterface {

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
   * Returns an array of filter permissions.
   *
   * @return array
   */
  public function permissions(): array {
    $permissions = [];

    /** @var \Drupal\filter\FilterFormatInterface[] $formats */
    $formats = $this->entityTypeManager->getStorage('filter_format')->loadByProperties(['status' => TRUE]);
    uasort($formats, 'Drupal\Core\Config\Entity\ConfigEntityBase::sort');
    foreach ($formats as $format) {
      $editorConfig = $this->configFactory->get('editor.editor.' . $format->id());
      if ($editorConfig->isNew() || $editorConfig->get('editor') !== 'ckeditor5') {
        continue;
      }
      $editorSettings = $editorConfig->get('settings');
      if (in_array('restrictedEditing', $editorSettings['toolbar']['items']) === FALSE) {
        continue;
      }

      if ($format->getPermissionName()) {
        $permission_name = $format->id() . ' bypass restricted editing';
        $permissions[$permission_name] = [
          'title' => $this->t('Bypass restricted editing for the <a href=":url">@format</a> text format',
            [
              ':url' => $format->toUrl()->toString(),
              '@format' => $format->label(),
            ]
          ),
          'description' => [
            '#prefix' => '<em>',
            '#markup' => $this->t('Allows to edit all content without any restrictions imposed by the Restricted Editing plugin.'),
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

    return $permissions;
  }
}
