<?php

namespace Drupal\commerce_product\Entity;

use Drupal\commerce\Entity\CommerceBundleEntityBase;

/**
 * Defines the product type entity class.
 *
 * @ConfigEntityType(
 *   id = "commerce_product_type",
 *   label = @Translation("Product type"),
 *   label_collection = @Translation("Product types"),
 *   label_singular = @Translation("product type"),
 *   label_plural = @Translation("product types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count product type",
 *     plural = "@count product types",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\commerce\CommerceBundleAccessControlHandler",
 *     "list_builder" = "Drupal\commerce_product\ProductTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\commerce_product\Form\ProductTypeForm",
 *       "edit" = "Drupal\commerce_product\Form\ProductTypeForm",
 *       "duplicate" = "Drupal\commerce_product\Form\ProductTypeForm",
 *       "delete" = "Drupal\commerce\Form\CommerceBundleEntityDeleteFormBase"
 *     },
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "commerce_product_type",
 *   admin_permission = "administer commerce_product_type",
 *   bundle_of = "commerce_product",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "variationType",
 *     "variationTypes",
 *     "multipleVariations",
 *     "injectVariationFields",
 *     "traits",
 *     "locked",
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/config/product-types/add",
 *     "edit-form" = "/admin/commerce/config/product-types/{commerce_product_type}/edit",
 *     "duplicate-form" = "/admin/commerce/config/product-types/{commerce_product_type}/duplicate",
 *     "delete-form" = "/admin/commerce/config/product-types/{commerce_product_type}/delete",
 *     "collection" = "/admin/commerce/config/product-types"
 *   }
 * )
 */
class ProductType extends CommerceBundleEntityBase implements ProductTypeInterface {

  /**
   * The product type description.
   *
   * @var string
   */
  protected $description = '';

  /**
   * The variation type ID.
   *
   * @var string
   */
  protected $variationType;

  /**
   * The variation type IDs.
   *
   * @var array
   */
  protected $variationTypes = [];

  /**
   * Whether products of this type can have multiple variations.
   *
   * @var bool
   */
  protected $multipleVariations = TRUE;

  /**
   * Whether variation fields should be injected.
   *
   * @var bool
   */
  protected $injectVariationFields = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getDescription() : string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) : ProductTypeInterface {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVariationTypeId() : ?string {
    if (count($this->getVariationTypeIds()) > 1) {
      throw new \RuntimeException(sprintf('"%s" supports multiple variation types.', $this->label()));
    }
    return $this->variationType ?? reset($this->variationTypes);
  }

  /**
   * {@inheritdoc}
   */
  public function setVariationTypeId(string $variation_type_id) : ProductTypeInterface {
    $this->variationType = $variation_type_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVariationTypeIds() : array {
    if (!empty($this->variationTypes)) {
      return array_filter($this->variationTypes);
    }
    else {
      return [$this->variationType];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setVariationTypeIds(array $variation_type_ids) : ProductTypeInterface {
    $this->variationTypes = $variation_type_ids;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function allowsMultipleVariations() : bool {
    return $this->multipleVariations;
  }

  /**
   * {@inheritdoc}
   */
  public function setMultipleVariations(bool $multiple_variations) : ProductTypeInterface {
    $this->multipleVariations = $multiple_variations;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldInjectVariationFields() : bool {
    return $this->injectVariationFields;
  }

  /**
   * {@inheritdoc}
   */
  public function setInjectVariationFields(bool $inject) : ProductTypeInterface {
    $this->injectVariationFields = $inject;
    return $this;
  }

}
