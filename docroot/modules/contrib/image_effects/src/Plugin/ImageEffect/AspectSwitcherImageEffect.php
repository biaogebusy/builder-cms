<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\image\Entity\ImageStyle;

/**
 * Choose image styles to apply based on source image orientation.
 *
 * @ImageEffect(
 *   id = "image_effects_aspect_switcher",
 *   label = @Translation("Aspect switcher"),
 *   description = @Translation("Choose image styles to use depending on the orientation of the source image (landscape/protrait).")
 * )
 */
class AspectSwitcherImageEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'landscape_image_style' => '',
      'portrait_image_style' => '',
      'ratio_adjustment' => 1,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $data = $this->configuration;
    if ($portrait_image_style = $this->failSafeGetImageStyle($this->configuration['portrait_image_style'])) {
      $data['portrait'] = $portrait_image_style->label();
    }
    else {
      $data['portrait'] = $this->t("(none)");
    }
    if ($landscape_image_style = $this->failSafeGetImageStyle($this->configuration['landscape_image_style'])) {
      $data['landscape'] = $landscape_image_style->label();
    }
    else {
      $data['landscape'] = $this->t("(none)");
    }
    return [
      '#theme' => 'image_effects_aspect_switcher',
      '#data' => $data,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $image_styles = [];
    if ($portrait_image_style = $this->failSafeGetImageStyle($this->configuration['portrait_image_style'])) {
      $image_styles[] = $portrait_image_style->getConfigDependencyName();
    }
    if ($landscape_image_style = $this->failSafeGetImageStyle($this->configuration['landscape_image_style'])) {
      $image_styles[] = $landscape_image_style->getConfigDependencyName();
    }
    return ['config' => $image_styles];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['info'] = [
      '#type'  => 'details',
      '#title' => $this->t('Information'),
    ];
    $form['info']['help'] = [
      '#markup' => $this->t("'Convert' effects included in the image style specified will not be effective. It is not possible to change the image format based on the aspect. If you need to change the image format, you will have to add a 'Convert' effect in this image style."),
    ];

    $form['landscape_image_style'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Landscape image style'),
      '#target_type' => 'image_style',
      '#default_value' => $this->failSafeGetImageStyle($this->configuration['landscape_image_style']),
      '#description' => $this->t("Leave empty to avoid switching."),
    ];

    $form['portrait_image_style'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Portrait image style'),
      '#target_type' => 'image_style',
      '#default_value' => $this->failSafeGetImageStyle($this->configuration['portrait_image_style']),
      '#description' => $this->t("Leave empty to avoid switching."),
    ];

    $form['ratio_adjustment'] = [
      '#type' => 'number',
      '#title' => $this->t('Ratio adjustment (advanced)'),
      '#required' => TRUE,
      '#size' => 7,
      '#min' => 0,
      '#max' => 5,
      '#step' => 0.01,
      '#default_value' => $this->configuration['ratio_adjustment'],
      '#description' => $this->t("This allows you to bend the rules for how different the proportions need to be to trigger the switch.") . "<br/>" . $this->t("If n = (width/height)*ratio is greater than 1, use 'landscape', otherwise use 'portrait'.") . "<br/>" . $this->t("When ratio = 1 (the default) it will just switch between portrait and landscape modes."),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    if ($form_state->getValue('portrait_image_style') === NULL && $form_state->getValue('landscape_image_style') === NULL) {
      $form_state->setErrorByName('portrait_image_style', $this->t("At least one of 'Landscape image style' or 'Portrait image style' must be selected."));
      $form_state->setErrorByName('landscape_image_style', $this->t("At least one of 'Landscape image style' or 'Portrait image style' must be selected."));
    }
    if ($this->failSafeGetImageStyle($form_state->getValue('portrait_image_style')) === FALSE) {
      $form_state->setErrorByName('portrait_image_style', $this->t("The image style does not exist."));
    }
    if ($this->failSafeGetImageStyle($form_state->getValue('landscape_image_style')) === FALSE) {
      $form_state->setErrorByName('landscape_image_style', $this->t("The image style does not exist."));
    }
    // @todo at the moment it is not possible to validate the style selected
    // not being a circular reference to the current style itself.
    // @see https://www.drupal.org/node/1826362
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['portrait_image_style'] = $form_state->getValue('portrait_image_style');
    $this->configuration['landscape_image_style'] = $form_state->getValue('landscape_image_style');
    $this->configuration['ratio_adjustment'] = $form_state->getValue('ratio_adjustment');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    $style_name = $this->getChildImageStyleToExecute($image->getWidth(), $image->getHeight());
    $style = $this->failSafeGetImageStyle($style_name);

    // No child style to process.
    if ($style === NULL) {
      return TRUE;
    }

    // Child style to process missing.
    if ($style === FALSE) {
      return FALSE;
    }

    foreach ($style->getEffects() as $effect) {
      $effect->applyEffect($image);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    $style_name = $this->getChildImageStyleToExecute($dimensions['width'], $dimensions['height']);
    $style = $this->failSafeGetImageStyle($style_name);

    // No or missing child style to process.
    if (!$style) {
      return;
    }

    foreach ($style->getEffects() as $effect) {
      $effect->transformDimensions($dimensions, $uri);
    }
  }

  /**
   * Gets the name of the child image style to process based on image aspect.
   *
   * @param int $width
   *   The width of the image.
   * @param int $height
   *   The height of the image.
   *
   * @return string
   *   The name of the image style to process.
   */
  protected function getChildImageStyleToExecute($width, $height) {
    $ratio_adjustment = isset($this->configuration['ratio_adjustment']) ? floatval($this->configuration['ratio_adjustment']) : 1;
    // Width / height * adjustment. If > 1, it's wide.
    return (($width / $height * $ratio_adjustment) > 1) ? $this->configuration['landscape_image_style'] : $this->configuration['portrait_image_style'];
  }

  /**
   * Gets an image style object.
   *
   * @param string $image_style_name
   *   The name of the image style to get.
   *
   * @return \Drupal\image\Entity\ImageStyle|null|false
   *   The image style object, or NULL if the name is NULL, or FALSE if the
   *   image style went missing from the db.
   */
  protected function failSafeGetImageStyle($image_style_name) {
    if ($image_style_name === NULL) {
      return NULL;
    }
    $image_style = ImageStyle::load($image_style_name);
    if ($image_style === NULL) {
      // Required style has gone missing?
      $this->logger->error("Cannot find image style '%style_name' to execute an 'aspect switcher' effect.", ['%style_name' => $image_style_name]);
      return FALSE;
    }
    return $image_style;
  }

}
