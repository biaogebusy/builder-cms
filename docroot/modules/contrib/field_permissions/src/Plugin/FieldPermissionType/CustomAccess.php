<?php

namespace Drupal\field_permissions\Plugin\FieldPermissionType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field_permissions\Plugin\AdminFormSettingsInterface;
use Drupal\field_permissions\Plugin\CustomPermissionsInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\RoleStorageInterface;
use Drupal\user\UserInterface;

/**
 * Defines custom access for fields.
 *
 * @FieldPermissionType(
 *   id = "custom",
 *   title = @Translation("Custom permissions"),
 *   description = @Translation("Define custom permissions for this field."),
 *   weight = 50
 * )
 */
class CustomAccess extends Base implements CustomPermissionsInterface, AdminFormSettingsInterface {

  /**
   * {@inheritdoc}
   */
  public function hasFieldAccess($operation, EntityInterface $entity, AccountInterface $account) {
    assert(in_array($operation, ["edit", "view"]), 'The operation is either "edit" or "view", "' . $operation . '" given instead.');

    $field_name = $this->fieldStorage->getName();
    if ($operation === 'edit' && $entity->isNew()) {
      return $account->hasPermission('create ' . $field_name);
    }
    if ($account->hasPermission($operation . ' ' . $field_name)) {
      return TRUE;
    }
    else {
      // User entities don't implement `EntityOwnerInterface`.
      if ($entity instanceof UserInterface) {
        return $entity->id() == $account->id() && $account->hasPermission($operation . ' own ' . $field_name);
      }
      elseif ($entity instanceof EntityOwnerInterface) {
        return $entity->getOwnerId() === $account->id() && $account->hasPermission($operation . ' own ' . $field_name);
      }
    }

    // Default to deny since access can be explicitly granted (edit field_name),
    // even if this entity type doesn't implement the EntityOwnerInterface.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasFieldViewAccessForEveryEntity(AccountInterface $account) {
    $field_name = $this->fieldStorage->getName();
    return $account->hasPermission('view ' . $field_name);
  }

  /**
   * {@inheritdoc}
   */
  public function buildAdminForm(array &$form, FormStateInterface $form_state, RoleStorageInterface $role_storage) {
    $this->addPermissionsGrid($form, $form_state, $role_storage);
  }

  /**
   * {@inheritdoc}
   */
  public function submitAdminForm(array &$form, FormStateInterface $form_state, RoleStorageInterface $role_storage) {
    $this_plugin_applies = $form_state->getValue('type') === $this->getPluginId();
    $custom_permissions = $form_state->getValue('permissions') ?? [];
    $keys = array_keys($custom_permissions);
    $custom_permissions = $this->transposeArray($custom_permissions);
    /** @var \Drupal\user\RoleInterface $role */
    foreach ($role_storage->loadMultiple() as $role) {
      if ($role->isAdmin()) {
        continue;
      }
      $permissions = $role->getPermissions();
      $removed = array_values(array_intersect($permissions, $keys));
      $added = $this_plugin_applies ? array_keys(array_filter($custom_permissions[$role->id()])) : [];
      // Permissions in role object are sorted on save. Permissions on form are
      // not in same order (the 'any' and 'own' items are flipped) but need to
      // be as array equality tests keys and values. So sort the added items.
      sort($added);
      if ($removed != $added) {
        // Rule #1 Do NOT save something that is not changed.
        // Like field storage, delete existing items then add current items.
        $permissions = array_diff($permissions, $removed);
        $permissions = array_merge($permissions, $added);
        $role->set('permissions', $permissions);
        $role->trustData()->save();
      }
    }
  }

  /**
   * Transposes a 2-dimensional array.
   *
   * @param array $original
   *   The array to transpose.
   *
   * @return array
   *   The transposed array.
   */
  protected function transposeArray(array $original) {
    $transpose = [];
    foreach ($original as $row => $columns) {
      foreach ($columns as $column => $value) {
        $transpose[$column][$row] = $value;
      }
    }
    return $transpose;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions() {
    $permissions = [];
    $field_name = $this->fieldStorage->getName();
    $permission_list = $this->fieldPermissionsService->getList($field_name);
    $perms_name = array_keys($permission_list);
    foreach ($perms_name as $perm_name) {
      $name = $perm_name . ' ' . $field_name;
      $permissions[$name] = $permission_list[$perm_name];
    }
    return $permissions;
  }

  /**
   * Attach a permissions grid to the field edit form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param \Drupal\user\RoleStorageInterface $role_storage
   *   The user role storage.
   */
  protected function addPermissionsGrid(array &$form, FormStateInterface $form_state, RoleStorageInterface $role_storage) {
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = $role_storage->loadMultiple();
    $permissions = $this->getPermissions();
    $options = array_keys($permissions);

    // The permissions table.
    $form['permissions'] = [
      '#type' => 'table',
      '#header' => [$this->t('Permission')],
      '#id' => 'permissions',
      '#attributes' => ['class' => ['permissions', 'js-permissions']],
      '#sticky' => TRUE,
    ];
    foreach ($roles as $role) {
      $form['permissions']['#header'][] = [
        'data' => $role->label(),
        'class' => ['checkbox'],
      ];
    }

    $test = $this->fieldPermissionsService->getPermissionsByRole();
    foreach ($permissions as $provider => $permission) {
      $form['permissions'][$provider]['description'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="permission"><span class="title">{{ title }}</span>{% if description or warning %}<div class="description">{% if warning %}<em class="permission-warning">{{ warning }}</em> {% endif %}{{ description }}</div>{% endif %}</div>',
        '#context' => [
          'title' => $permission["title"],
        ],
      ];
      $options[$provider] = '';
      foreach ($roles as $name => $role) {
        $form['permissions'][$provider][$name] = [
          '#title' => $name . ': ' . $permission["title"],
          '#title_display' => 'invisible',
          '#type' => 'checkbox',
          '#attributes' => ['class' => ['rid-' . $name, 'js-rid-' . $name]],
          '#wrapper_attributes' => [
            'class' => ['checkbox'],
          ],
        ];
        if (!empty($test[$name]) && in_array($provider, $test[$name])) {
          $form['permissions'][$provider][$name]['#default_value'] = in_array($provider, $test[$name]);
        }
        if ($role->isAdmin()) {
          $form['permissions'][$provider][$name]['#disabled'] = TRUE;
          $form['permissions'][$provider][$name]['#default_value'] = TRUE;
        }
      }
    }
    // Attach the Drupal user permissions library.
    $form['#attached']['library'][] = 'user/drupal.user.permissions';
  }

}
