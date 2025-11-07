<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Import config entity.
 *
 * @ConfigEntityType(
 *   id = "import_config",
 *   label = @Translation("Import config"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\entity_share_client\ImportConfigListBuilder",
 *     "form" = {
 *       "add" = "Drupal\entity_share_client\Form\ImportConfigForm",
 *       "edit" = "Drupal\entity_share_client\Form\ImportConfigForm",
 *       "delete" = "Drupal\entity_share_client\Form\ImportConfigDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "import_config",
 *   admin_permission = "administer_import_config_entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "import_maxsize",
 *     "import_processor_settings",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/services/entity_share/import_config/{import_config}",
 *     "add-form" = "/admin/config/services/entity_share/import_config/add",
 *     "edit-form" = "/admin/config/services/entity_share/import_config/{import_config}/edit",
 *     "delete-form" = "/admin/config/services/entity_share/import_config/{import_config}/delete",
 *     "collection" = "/admin/config/services/entity_share/import_config"
 *   }
 * )
 *
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class ImportConfig extends ConfigEntityBase implements ImportConfigInterface {

  /**
   * The Import config ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Import config label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Import config max size.
   *
   * @var int
   */
  protected $import_maxsize = 50;

  /**
   * The array of import processor settings.
   *
   * The array has the following structure:
   *
   * @var array
   *
   * @code
   * [
   *   'PROCESSOR_ID' => [
   *     'weights' => [],
   *     // Other settings …
   *   ],
   *   …
   * ]
   * @endcode
   */
  protected $import_processor_settings = [];

}
