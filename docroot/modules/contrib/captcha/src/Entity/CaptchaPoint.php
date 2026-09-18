<?php

namespace Drupal\captcha\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\captcha\CaptchaPointInterface;
use Drupal\captcha\Constants\CaptchaConstants;

/**
 * Defines the CaptchaPoint entity.
 *
 * The 'rendered' tag for the List cache is necessary since captchas have to be
 * rerendered once they are modified. Invalidating the render cache ensures
 * we always display the correct captcha for every form.
 *
 * @ConfigEntityType(
 *   id = "captcha_point",
 *   label = @Translation("Captcha Point"),
 *   handlers = {
 *     "list_builder" = "Drupal\captcha\Entity\Controller\CaptchaPointListBuilder",
 *     "form" = {
 *       "add" = "Drupal\captcha\Form\CaptchaPointForm",
 *       "edit" = "Drupal\captcha\Form\CaptchaPointForm",
 *       "disable" = "Drupal\captcha\Form\CaptchaPointDisableForm",
 *       "enable" = "Drupal\captcha\Form\CaptchaPointEnableForm",
 *       "delete" = "Drupal\captcha\Form\CaptchaPointDeleteForm"
 *     }
 *   },
 *   config_prefix = "captcha_point",
 *   admin_permission = "administer CAPTCHA settings",
 *   list_cache_tags = {
 *    "rendered"
 *   },
 *   entity_keys = {
 *     "id" = "formId",
 *     "label" = "label",
 *     "status" = "status",
 *   },
 *   config_export = {
 *     "formId",
 *     "captchaType",
 *     "label",
 *     "uuid",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/people/captcha/captcha-points/{captcha_point}",
 *     "disable" = "/admin/config/people/captcha/captcha-points/{captcha_point}/disable",
 *     "enable" = "/admin/config/people/captcha/captcha-points/{captcha_point}/enable",
 *     "delete-form" = "/admin/config/people/captcha/captcha-points/{captcha_point}/delete",
 *   }
 * )
 */
class CaptchaPoint extends ConfigEntityBase implements CaptchaPointInterface {

  /**
   * The captcha type.
   *
   * @var string
   */
  public $captchaType;

  /**
   * The captcha label.
   *
   * @var string
   */
  protected $label;

  /**
   * The formid associated with the captcha.
   *
   * @var string
   */
  public $formId;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->formId;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->formId;
  }

  /**
   * {@inheritdoc}
   */
  public function setFormId($form_id) {
    $this->formId = $form_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel($label) {
    $this->label = $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getCaptchaType() {
    if (isset($this->captchaType)) {
      return $this->captchaType;
    }
    else {
      return static::getConfigManager()
        ->getConfigFactory()
        ->get('captcha.settings')
        ->get('default_challenge');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setCaptchaType($captcha_type) {
    $this->captchaType = $captcha_type != CaptchaConstants::CAPTCHA_TYPE_DEFAULT ? $captcha_type : NULL;
  }

}
