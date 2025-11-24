<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\image\Attribute\ImageEffect;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\image_effects\Component\ImageUtility;
use Drupal\image_effects\Component\PositionedRectangle;
use Drupal\image_effects\Plugin\FontSelectorPluginManager;
use Drupal\image_effects\Plugin\ImageEffectsFontSelectorPluginInterface;
use Drupal\token\TreeBuilderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overlays text on the image, defining text font, size and positioning.
 */
#[ImageEffect(
  id: 'image_effects_text_overlay',
  label: new TranslatableMarkup('Text overlay'),
  description: new TranslatableMarkup('Overlays text on the image, defining text font, size and positioning.'),
)]
class TextOverlayImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * Stores information about image and text wrapper.
   *
   * @var array<string, int>
   */
  protected array $info = [
    'image_xpos' => 0,
    'image_ypos' => 0,
  ];

  /**
   * TextOverlayImageEffect constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Image\ImageFactory $imageFactory
   *   The image factory service.
   * @param \Drupal\image_effects\Plugin\ImageEffectsFontSelectorPluginInterface $fontSelector
   *   The font selector plugin.
   * @param \Drupal\Core\Utility\Token $token
   *   The token resolution service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    LoggerInterface $logger,
    protected ImageFactory $imageFactory,
    protected ImageEffectsFontSelectorPluginInterface $fontSelector,
    protected Token $token,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.image_effects'),
      $container->get('image.factory'),
      $container->get(FontSelectorPluginManager::class)->getPlugin(),
      $container->get('token'),
      $container->get('module_handler')
    );
  }

  /**
   * Returns the textimage.factory service, if available.
   *
   * @return \Drupal\textimage\TextimageFactory|null
   *   The textimage.factory service if available, NULL otherwise.
   */
  protected function getTextimageFactory() {
    return \Drupal::hasService('textimage.factory') ? \Drupal::service('textimage.factory') : NULL;
  }

  /**
   * Returns the token.tree_builder service, if available.
   *
   * @return \Drupal\token\TreeBuilderInterface|null
   *   The token.tree_builder service if available, NULL otherwise.
   */
  protected function getTokenTreeBuilder(): ?TreeBuilderInterface {
    return \Drupal::hasService('token.tree_builder') ? \Drupal::service('token.tree_builder') : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return NestedArray::mergeDeep(
      [
        'font'          => [
          'name'                  => '',
          'uri'                   => '',
          'size'                  => 16,
          'angle'                 => 0,
          'color'                 => '#000000FF',
          'stroke_mode'           => 'outline',
          'stroke_color'          => '#000000FF',
          'outline_top'           => 0,
          'outline_right'         => 0,
          'outline_bottom'        => 0,
          'outline_left'          => 0,
          'shadow_x_offset'       => 1,
          'shadow_y_offset'       => 1,
          'shadow_width'          => 0,
          'shadow_height'         => 0,
        ],
        'layout'       => [
          'padding_top'           => 0,
          'padding_right'         => 0,
          'padding_bottom'        => 0,
          'padding_left'          => 0,
          'x_pos'                 => 'center',
          'y_pos'                 => 'center',
          'x_offset'              => 0,
          'y_offset'              => 0,
          'background_color'      => NULL,
          'overflow_action'       => 'extend',
          'extended_color'        => NULL,
        ],
        'text' => [
          'strip_tags' => TRUE,
          'decode_entities' => TRUE,
          'maximum_width'         => 0,
          'fixed_width'           => FALSE,
          'align'                 => 'left',
          'line_spacing'          => 0,
          'case_format'           => '',
          'maximum_chars' => NULL,
          'excess_chars_text' => $this->t('…'),
        ],
        'text_string'             => $this->t('Preview'),
      ],
      parent::defaultConfiguration()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = [];

    if ($this->getTextimageFactory()) {
      // Preview effect.
      [$success, $preview] = $this->buildPreviewRender($this->configuration);
      $form['preview'] = [
        '#type'   => 'item',
        '#title' => $this->t('Preview'),
        '#theme' => 'image_effects_text_overlay_preview',
        '#success' => $success,
        '#preview' => $preview,
      ];

      // Preview bar.
      $form['preview_bar'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['container-inline'],
        ],
      ];
      // Refresh button.
      $form['preview_bar']['preview'] = [
        '#type'  => 'button',
        '#value' => $this->t('Refresh preview'),
        '#name' => 'preview',
        '#ajax'  => [
          'callback' => [$this, 'processAjaxPreview'],
        ],
      ];
      // Visual aids.
      $form['preview_bar']['debug_visuals'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Visual aids in preview'),
        '#default_value' => FALSE,
      ];
    }

    // Settings.
    $form['settings'] = [
      '#type' => 'vertical_tabs',
      '#tree' => FALSE,
    ];

    // Text default.
    $form['text_default'] = [
      '#type'  => 'details',
      '#title' => $this->t('Text default'),
      '#group'   => 'settings',
    ];
    $form['text_default']['text_string'] = [
      '#type'  => 'textarea',
      '#title' => $this->t('Default text'),
      '#default_value' => $this->configuration['text_string'],
      '#description' => $this->t('Enter the default text string for this effect. You can also enter tokens, that will be resolved when applying the effect. <b>Note:</b> only global tokens can be resolved by standard Drupal Image field formatters and widgets. The Textimage module provides a formatter that can also resolve node, file and user tokens.'),
      '#rows' => 3,
      '#required' => TRUE,
    ];
    if ($token_tree_builder = $this->getTokenTreeBuilder()) {
      $form['text_default']['tokens'] = $token_tree_builder->buildAllRenderable();
    }
    // Strip HTML tags.
    $form['text_default']['strip_tags'] = [
      '#type'  => 'checkbox',
      '#title' => $this->t('Strip HTML tags'),
      '#description' => $this->t("If checked, HTML tags will be stripped from the text. For example, '<kbd>&lt;p&gt;Para1&lt;/p&gt;&lt;!-- Comment --&gt; Para2</kbd>' will be converted to '<kbd>Para1 Para2</kbd>'."),
      '#default_value' => $this->configuration['text']['strip_tags'],
    ];
    // Decode HTML entities.
    $form['text_default']['decode_entities'] = [
      '#type'  => 'checkbox',
      '#title' => $this->t('Decode HTML entities'),
      '#description' => $this->t("If checked, HTML entities will be decoded. For example, '<kbd>&amp;quot;Title&amp;quot;:&amp;nbsp;One</kbd>' will be converted to <kbd>'&quot;Title&quot;: One</kbd>'."),
      '#default_value' => $this->configuration['text']['decode_entities'],
    ];

    // Font settings.
    $form['font'] = [
      '#type'  => 'details',
      '#title' => $this->t('Font settings'),
      '#group'   => 'settings',
    ];
    $form['font']['uri'] = $this->fontSelector->selectionElement([
      '#title' => $this->t('Font'),
      '#description' => $this->t('Select the font to be used in this image.'),
      '#default_value' => $this->configuration['font']['uri'],
    ]);
    $form['font']['size'] = [
      '#type'  => 'number',
      '#title' => $this->t('Size'),
      '#description'   => $this->t('Enter the size of the text to be generated.'),
      '#default_value' => $this->configuration['font']['size'],
      '#maxlength' => 5,
      '#size' => 3,
      '#required' => TRUE,
      '#min' => 1,
    ];
    $form['font']['angle'] = [
      '#type'  => 'number',
      '#title' => $this->t('Rotation'),
      '#maxlength' => 4,
      '#size' => 4,
      '#field_suffix' => $this->t('&deg;'),
      '#description' => $this->t('Enter the angle in degrees at which the text will be displayed. Positive numbers rotate the text clockwise, negative numbers counter-clockwise.'),
      '#default_value' => $this->configuration['font']['angle'],
      '#min' => -360,
      '#max' => 360,
    ];
    $form['font']['color'] = [
      '#type' => 'image_effects_color',
      '#title' => $this->t('Font color'),
      '#description'  => $this->t('Set the font color.'),
      '#allow_opacity' => TRUE,
      '#default_value' => $this->configuration['font']['color'],
    ];
    // Outline.
    $form['font']['stroke'] = [
      '#type' => 'details',
      '#title' => $this->t('Outline / Shadow'),
      '#description'   => $this->t('Optionally add an outline or shadow around the font. Enter the information in pixels.'),
    ];
    $stroke_options = [
      'outline' => $this->t('Outline'),
      'shadow' => $this->t('Shadow'),
    ];
    $form['font']['stroke']['mode'] = [
      '#type'    => 'radios',
      '#title'   => $this->t('Mode'),
      '#options' => $stroke_options,
      '#default_value' => $this->configuration['font']['stroke_mode'],
    ];
    $form['font']['stroke']['top'] = [
      '#type' => 'number',
      '#title' => $this->t('Top'),
      '#default_value' => $this->configuration['font']['outline_top'],
      '#maxlength' => 2,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':radio[name="data[font][stroke][mode]"]' => ['value' => 'outline'],
        ],
      ],
    ];
    $form['font']['stroke']['right'] = [
      '#type' => 'number',
      '#title' => $this->t('Right'),
      '#default_value' => $this->configuration['font']['outline_right'],
      '#maxlength' => 2,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':radio[name="data[font][stroke][mode]"]' => ['value' => 'outline'],
        ],
      ],
    ];
    $form['font']['stroke']['bottom'] = [
      '#type' => 'number',
      '#title' => $this->t('Bottom'),
      '#default_value' => $this->configuration['font']['outline_bottom'],
      '#maxlength' => 2,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':radio[name="data[font][stroke][mode]"]' => ['value' => 'outline'],
        ],
      ],
    ];
    $form['font']['stroke']['left'] = [
      '#type' => 'number',
      '#title' => $this->t('Left'),
      '#default_value' => $this->configuration['font']['outline_left'],
      '#maxlength' => 2,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':radio[name="data[font][stroke][mode]"]' => ['value' => 'outline'],
        ],
      ],
    ];
    $form['font']['stroke']['x_offset'] = [
      '#type' => 'number',
      '#title' => $this->t('Horizontal offset'),
      '#default_value' => $this->configuration['font']['shadow_x_offset'],
      '#maxlength' => 3,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':radio[name="data[font][stroke][mode]"]' => ['value' => 'shadow'],
        ],
      ],
    ];
    $form['font']['stroke']['y_offset'] = [
      '#type' => 'number',
      '#title' => $this->t('Vertical offset'),
      '#default_value' => $this->configuration['font']['shadow_y_offset'],
      '#maxlength' => 3,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':radio[name="data[font][stroke][mode]"]' => ['value' => 'shadow'],
        ],
      ],
    ];
    $form['font']['stroke']['width'] = [
      '#type' => 'number',
      '#title' => $this->t('Horizontal elongation'),
      '#default_value' => $this->configuration['font']['shadow_width'],
      '#maxlength' => 2,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':radio[name="data[font][stroke][mode]"]' => ['value' => 'shadow'],
        ],
      ],
    ];
    $form['font']['stroke']['height'] = [
      '#type' => 'number',
      '#title' => $this->t('Vertical elongation'),
      '#default_value' => $this->configuration['font']['shadow_height'],
      '#maxlength' => 2,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':radio[name="data[font][stroke][mode]"]' => ['value' => 'shadow'],
        ],
      ],
    ];
    $form['font']['stroke']['color'] = [
      '#type' => 'image_effects_color',
      '#title' => $this->t('Color'),
      '#description'  => $this->t('Set the outline/shadow color.'),
      '#allow_opacity' => TRUE,
      '#default_value' => $this->configuration['font']['stroke_color'],
    ];

    // Text settings.
    $form['text'] = [
      '#type'  => 'details',
      '#title' => $this->t('Text settings'),
      '#group'   => 'settings',
    ];
    // Max characters.
    $form['text']['maximum_chars'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum characters'),
      '#description' => $this->t('The maximum allowed characters of text. Text longer than this will be trimmed. Leave blank for no limit.'),
      '#default_value' => $this->configuration['text']['maximum_chars'],
      '#maxlength' => 4,
      '#size' => 5,
      '#min' => 0,
    ];
    $form['text']['excess_chars_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Excess text"),
      '#default_value' => $this->configuration['text']['excess_chars_text'],
      '#description' => $this->t('Text to append to the end of the source text, after trimming.'),
      '#states' => [
        'visible' => [
          ':input[name="data[text][maximum_chars]"]' => ['!value' => ''],
        ],
      ],
    ];
    // Inner width.
    $form['text']['maximum_width'] = [
      '#type'  => 'number',
      '#title' => $this->t('Maximum width'),
      '#field_suffix' => $this->t('px'),
      '#description' => $this->t('Maximum width of the text image, inclusive of padding. Text lines wider than this will be wrapped. Set to 0 to disable wrapping. <b>Note:</b> in case of rotation, the width of the final image rendered will differ, to accommodate the rotation. If you need a strict width/height, add image resize/scale/crop effects afterwards.'),
      '#default_value' => $this->configuration['text']['maximum_width'],
      '#maxlength' => 4,
      '#size' => 4,
      '#min' => 0,
    ];
    $form['text']['fixed_width'] = [
      '#type'  => 'checkbox',
      '#title' => $this->t('Fixed width?'),
      '#description' => $this->t('If checked, the width will always be equal to the maximum width.'),
      '#default_value' => $this->configuration['text']['fixed_width'],
      '#states' => [
        'visible' => [
          ':input[name="data[text][maximum_width]"]' => ['!value' => 0],
        ],
      ],
    ];
    // Text alignment.
    $form['text']['align'] = [
      '#type'  => 'select',
      '#title' => $this->t('Text alignment'),
      '#options' => [
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
        'right' => $this->t('Right'),
      ],
      '#default_value' => $this->configuration['text']['align'],
      '#description' => $this->t('Select how the text should be aligned within the resulting image. The default aligns to the left.'),
    ];
    // Line spacing (Leading).
    $form['text']['line_spacing'] = [
      '#type'  => 'number',
      '#title' => $this->t('Line spacing (Leading)'),
      '#field_suffix'  => $this->t('px'),
      '#default_value' => $this->configuration['text']['line_spacing'],
      '#maxlength' => 4,
      '#size' => 4,
      '#description' => $this->t('Specify the space in pixels to be added between text lines (Leading). Can be negative.'),
    ];
    $form['text']['case_format'] = [
      '#type'  => 'select',
      '#title' => $this->t('Case format'),
      '#options' => [
        '' => $this->t('Default'),
        'upper' => $this->t('UPPERCASE'),
        'lower' => $this->t('lowercase'),
        'ucwords' => $this->t('Uppercase Words'),
        'ucfirst' => $this->t('Uppercase first'),
      ],
      '#description' => $this->t('Convert the input text to a desired format. The default makes no changes to input text.'),
      '#default_value' => $this->configuration['text']['case_format'],
    ];

    // Layout settings.
    $form['layout'] = [
      '#type'  => 'details',
      '#title' => $this->t('Layout settings'),
      '#group'   => 'settings',
    ];
    // Position.
    $form['layout']['position'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Position'),
    ];
    $form['layout']['position']['placement'] = [
      '#type' => 'radios',
      '#title' => $this->t('Placement'),
      '#options' => [
        'left-top' => $this->t('Top left'),
        'center-top' => $this->t('Top center'),
        'right-top' => $this->t('Top right'),
        'left-center' => $this->t('Center left'),
        'center-center' => $this->t('Center'),
        'right-center' => $this->t('Center right'),
        'left-bottom' => $this->t('Bottom left'),
        'center-bottom' => $this->t('Bottom center'),
        'right-bottom' => $this->t('Bottom right'),
      ],
      '#theme' => 'image_anchor',
      '#default_value' => implode('-', [
        $this->configuration['layout']['x_pos'],
        $this->configuration['layout']['y_pos'],
      ]),
      '#description' => $this->t('Position of the text on the underlying image.'),
    ];
    $form['layout']['position']['x_offset'] = [
      '#type'  => 'number',
      '#title' => $this->t('Horizontal offset'),
      '#field_suffix'  => 'px',
      '#description'   => $this->t('Additional horizontal offset from placement.'),
      '#default_value' => $this->configuration['layout']['x_offset'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    $form['layout']['position']['y_offset'] = [
      '#type'  => 'number',
      '#title' => $this->t('Vertical offset'),
      '#field_suffix'  => 'px',
      '#description'   => $this->t('Additional vertical offset from placement.'),
      '#default_value' => $this->configuration['layout']['y_offset'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    // Overflow action.
    $form['layout']['position']['overflow_action'] = [
      '#type' => 'radios',
      '#title' => $this->t('Overflow'),
      '#default_value' => $this->configuration['layout']['overflow_action'],
      '#options' => [
        'extend' => $this->t('<b>Extend image.</b> The underlying image will be extended to fit the text.'),
        'crop' => $this->t('<b>Crop text.</b> Only the part of the text fitting in the image is rendered.'),
        'scaletext' => $this->t('<b>Scale text.</b> The text will be scaled to fit the underlying image.'),
      ],
      '#description' => $this->t('Action to take if text overflows the underlying image.'),
    ];
    $form['layout']['position']['extended_color'] = [
      '#type' => 'image_effects_color',
      '#title' => $this->t('Extended background color'),
      '#description'  => $this->t('Set the color to be used when extending the underlying image.'),
      '#allow_null' => TRUE,
      '#allow_opacity' => TRUE,
      '#default_value' => $this->configuration['layout']['extended_color'],
      '#states' => [
        'visible' => [
          ':radio[name="data[layout][position][overflow_action]"]' => ['value' => 'extend'],
        ],
      ],
    ];
    // Padding.
    $form['layout']['padding'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Padding'),
      '#description' => $this->t('Specify the padding in pixels to be added around the generated text.'),
    ];
    $form['layout']['padding']['top'] = [
      '#type'  => 'number',
      '#title' => $this->t('Top'),
      '#field_suffix'  => $this->t('px'),
      '#default_value' => $this->configuration['layout']['padding_top'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    $form['layout']['padding']['right'] = [
      '#type'  => 'number',
      '#title' => $this->t('Right'),
      '#field_suffix'  => $this->t('px'),
      '#default_value' => $this->configuration['layout']['padding_right'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    $form['layout']['padding']['bottom'] = [
      '#type'  => 'number',
      '#title' => $this->t('Bottom'),
      '#field_suffix'  => $this->t('px'),
      '#default_value' => $this->configuration['layout']['padding_bottom'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    $form['layout']['padding']['left'] = [
      '#type'  => 'number',
      '#title' => $this->t('Left'),
      '#field_suffix'  => $this->t('px'),
      '#default_value' => $this->configuration['layout']['padding_left'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    // Background color.
    $form['layout']['background_color'] = [
      '#type' => 'image_effects_color',
      '#title' => $this->t('Background color'),
      '#description'  => $this->t('Select the color you wish to use for the background of the text.'),
      '#allow_null' => TRUE,
      '#allow_opacity' => TRUE,
      '#default_value' => $this->configuration['layout']['background_color'],
    ];

    return $form;
  }

  /**
   * Preview Ajax callback.
   */
  public function processAjaxPreview($form, FormStateInterface $form_state) {
    [$success, $preview] = $this->buildPreviewRender($form_state->getValue(['data', 'ajax_config']));
    $preview_render = [
      '#theme' => 'image_effects_text_overlay_preview',
      '#success' => $success,
      '#preview' => $preview,
    ];
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#text-overlay-preview', $preview_render));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    // @todo the array syntax for $form_state->getValue([...]) fails code
    // style checking, but this is quite inconvenient. See if sniff gets
    // adjusted or a different way to access nested keys will be available.
    // @codingStandardsIgnoreStart
    // Get x-y position from the anchor element.
    [$x_pos, $y_pos] = explode('-', $form_state->getValue(['layout', 'position', 'placement']));

    $this->configuration = [
      'font'   => [
        'name' => $form_state->hasValue(['font', 'uri']) ? $this->fontSelector->getDescription($form_state->getValue(['font', 'uri'])) : NULL,
        'uri' => $form_state->getValue(['font', 'uri']),
        'size' => $form_state->getValue(['font', 'size']),
        'angle' => $form_state->getValue(['font', 'angle']),
        'color' => $form_state->getValue(['font', 'color']),
        'stroke_mode' => $form_state->getValue(['font', 'stroke', 'mode']),
        'stroke_color' => $form_state->getValue(['font', 'stroke', 'color']),
        'outline_top' => $form_state->getValue(['font', 'stroke', 'top']),
        'outline_right' => $form_state->getValue(['font', 'stroke', 'right']),
        'outline_bottom' => $form_state->getValue(['font', 'stroke', 'bottom']),
        'outline_left' => $form_state->getValue(['font', 'stroke', 'left']),
        'shadow_x_offset' => $form_state->getValue(['font', 'stroke', 'x_offset']),
        'shadow_y_offset' => $form_state->getValue(['font', 'stroke', 'y_offset']),
        'shadow_width' => $form_state->getValue(['font', 'stroke', 'width']),
        'shadow_height' => $form_state->getValue(['font', 'stroke', 'height']),
      ],
      'layout' => [
        'padding_top' => $form_state->getValue(['layout', 'padding', 'top']),
        'padding_right' => $form_state->getValue(['layout', 'padding', 'right']),
        'padding_bottom' => $form_state->getValue(['layout', 'padding', 'bottom']),
        'padding_left' => $form_state->getValue(['layout', 'padding', 'left']),
        'x_pos' => $x_pos,
        'y_pos' => $y_pos,
        'x_offset' => $form_state->getValue(['layout', 'position', 'x_offset']),
        'y_offset' => $form_state->getValue(['layout', 'position', 'y_offset']),
        'overflow_action' => $form_state->getValue(['layout', 'position', 'overflow_action']),
        'extended_color' => $form_state->getValue(['layout', 'position', 'extended_color']),
        'background_color' => $form_state->getValue(['layout', 'background_color']),
      ],
      'text'   => [
        'strip_tags' => (bool) $form_state->getValue(['text_default', 'strip_tags']),
        'decode_entities' => (bool) $form_state->getValue(['text_default', 'decode_entities']),
        'maximum_width' => $form_state->getValue(['text', 'maximum_width']),
        'fixed_width' => $form_state->getValue(['text', 'fixed_width']),
        'align' => $form_state->getValue(['text', 'align']),
        'case_format' => $form_state->getValue(['text', 'case_format']),
        'line_spacing' => $form_state->getValue(['text', 'line_spacing']),
        'maximum_chars' => $form_state->getValue(['text', 'maximum_chars']),
        'excess_chars_text' => $form_state->getValue(['text', 'excess_chars_text']),
      ],
      'text_string' => $form_state->getValue(['text_default', 'text_string']),
    ];
    // @codingStandardsIgnoreEnd

    // Save the updated configuration in a FormState value to enable Ajax
    // preview generation.
    $form_state->setValue(['ajax_config'], $this->configuration);
    $form_state->setValue(
      ['ajax_config', 'preview_bar', 'debug_visuals'],
      $form_state->getValue(['preview_bar', 'debug_visuals']),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $data = $this->configuration;
    $data['font_color_detail'] = [
      '#theme' => 'image_effects_color_detail',
      '#color' => $data['font']['color'],
      '#border' => TRUE,
      '#border_color' => 'matchLuma',
    ];
    if ($stroke_mode = $this->strokeMode()) {
      $data['stroke_mode'] = $stroke_mode;
      $data['stroke_color_detail'] = [
        '#theme' => 'image_effects_color_detail',
        '#color' => $data['font']['stroke_color'],
        '#border' => TRUE,
        '#border_color' => 'matchLuma',
      ];
    }
    if ($data['layout']['background_color']) {
      $data['background_color_detail'] = [
        '#theme' => 'image_effects_color_detail',
        '#color' => $data['layout']['background_color'],
        '#border' => TRUE,
        '#border_color' => 'matchLuma',
      ];
    }

    return [
      '#theme' => 'image_effects_text_overlay_summary',
      '#data' => $data,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    // Preserve current background image dimensions.
    if ($image->getWidth() === 1 && $image->getHeight() === 1) {
      // Special case: when width and height of the source image is 1, we set
      // starting width and height within the effect to be zero. This way we
      // avoid that text overlays that extend the source image lead to images
      // with a single transparent or colored pixel in the center of the image.
      $image_width = 0;
      $image_height = 0;
    }
    else {
      $image_width = $image->getWidth();
      $image_height = $image->getHeight();
    }

    $this->info['image_width'] = $image_width;
    $this->info['image_height'] = $image_height;

    // Get the text wrapper Image object.
    if (!$wrapper = $this->getTextWrapper()) {
      return FALSE;
    }

    // Determine if background image needs resizing.
    if ($this->configuration['layout']['overflow_action'] == 'extend') {
      // The size of the frame sides for color filling.
      $this->info['frame_top'] = 0;
      $this->info['frame_right'] = 0;
      $this->info['frame_bottom'] = 0;
      $this->info['frame_left'] = 0;

      // Check wrapper image overflowing the original image.
      if ($this->canvasResizeNeeded($wrapper, $this->info)) {
        // Apply set_canvas, transparent background.
        $data = [
          'width' => $this->info['image_width'],
          'height' => $this->info['image_height'],
          'x_pos' => $this->info['image_xpos'],
          'y_pos' => $this->info['image_ypos'],
        ];
        if (!$image->apply('set_canvas', $data)) {
          return FALSE;
        }
        // Color fill the frame with extended color.
        if ($main_bg_color = $this->configuration['layout']['extended_color']) {
          // Top rectangle.
          $rectangle = new PositionedRectangle();
          if ($this->info['frame_top']) {
            $rectangle->setFromCorners([
              'c_a' => [0, $this->info['frame_top'] - 1],
              'c_b' => [$this->info['image_width'] - 1, $this->info['frame_top'] - 1],
              'c_c' => [$this->info['image_width'] - 1, 0],
              'c_d' => [0, 0],
            ]);
            if (!$image->apply('draw_rectangle', ['rectangle' => $rectangle, 'fill_color' => $main_bg_color])) {
              return FALSE;
            };
          }
          // Bottom rectangle.
          if ($this->info['frame_bottom']) {
            $rectangle->setFromCorners([
              'c_a' => [0, $this->info['image_height'] - 1],
              'c_b' => [$this->info['image_width'] - 1, $this->info['image_height'] - 1],
              'c_c' => [$this->info['image_width'] - 1, $image_height + $this->info['frame_top']],
              'c_d' => [0, $image_height + $this->info['frame_top']],
            ]);
            if (!$image->apply('draw_rectangle', ['rectangle' => $rectangle, 'fill_color' => $main_bg_color])) {
              return FALSE;
            };
          }
          // Left rectangle.
          if ($this->info['frame_left']) {
            $rectangle->setFromCorners([
              'c_a' => [0, $this->info['frame_top'] + $image_height - 1],
              'c_b' => [$this->info['frame_left'] - 1, $this->info['frame_top'] + $image_height - 1],
              'c_c' => [$this->info['frame_left'] - 1, $this->info['frame_top']],
              'c_d' => [0, $this->info['frame_top']],
            ]);
            if (!$image->apply('draw_rectangle', ['rectangle' => $rectangle, 'fill_color' => $main_bg_color])) {
              return FALSE;
            };
          }
          // Right rectangle.
          if ($this->info['frame_right']) {
            $rectangle->setFromCorners([
              'c_a' => [$this->info['frame_left'] + $image_width, $this->info['frame_top'] + $image_height - 1],
              'c_b' => [$this->info['image_width'] - 1, $this->info['frame_top'] + $image_height - 1],
              'c_c' => [$this->info['image_width'] - 1, $this->info['frame_top']],
              'c_d' => [$this->info['frame_left'] + $image_width, $this->info['frame_top']],
            ]);
            if (!$image->apply('draw_rectangle', ['rectangle' => $rectangle, 'fill_color' => $main_bg_color])) {
              return FALSE;
            };
          }
        }
      }
    }
    else {
      // Nothing to do, just place the wrapper at offset required.
      $x_offset = ImageUtility::getKeywordOffset($this->configuration['layout']['x_pos'], $image_width, $wrapper->getWidth());
      $y_offset = ImageUtility::getKeywordOffset($this->configuration['layout']['y_pos'], $image_height, $wrapper->getHeight());
      $this->info['wrapper_xpos'] = $x_offset + $this->configuration['layout']['x_offset'];
      $this->info['wrapper_ypos'] = $y_offset + $this->configuration['layout']['y_offset'];
    }

    // Finally, lay the wrapper over the source image.
    if (!$image->apply('watermark', [
      'watermark_image' => $wrapper,
      'x_offset' => $this->info['wrapper_xpos'],
      'y_offset' => $this->info['wrapper_ypos'],
    ])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    $dimensions['width'] = $dimensions['width'] ? (int) $dimensions['width'] : NULL;
    $dimensions['height'] = $dimensions['height'] ? (int) $dimensions['height'] : NULL;

    // Dimensions are potentially affected only if the effect is set to
    // autoextend the background image in case of wrapper overflow. Also,
    // current dimensions must be known.
    if ($dimensions['width'] && $dimensions['height'] && $this->configuration['layout']['overflow_action'] == 'extend') {

      $this->info['image_width'] = $dimensions['width'];
      $this->info['image_height'] = $dimensions['height'];

      // Get the text wrapper Image object.
      if (!$wrapper = $this->getTextWrapper()) {
        return;
      }

      // Checks if resizing needed.
      if ($this->canvasResizeNeeded($wrapper, $this->info)) {
        $dimensions['width'] = $this->info['image_width'];
        $dimensions['height'] = $this->info['image_height'];
      }
    }
  }

  /**
   * Gets the text to overlay on the image, after all alterations.
   *
   * @param string $text
   *   The text to be altered.
   *
   * @return string
   *   The text after alteration by modules.
   */
  public function getAlteredText(string $text): string {
    $this->moduleHandler->alter('image_effects_text_overlay_text', $text, $this);
    return $text;
  }

  /**
   * Get the image containing the text.
   *
   * This is separated from ::applyEffect() so that it can also be used
   * by the ::transformDimensions() method.
   */
  protected function getTextWrapper(): ?ImageInterface {
    // If the effect is executed outside of the context of Textimage
    // (e.g. by the core Image module), then the text_string has not been
    // pre-processed to translate tokens or apply text conversion. Do it here.
    $textimage_factory = $this->getTextimageFactory();
    if (!$textimage_factory || $textimage_factory->getState('building_module') !== 'textimage') {
      // Replace any tokens in text with run-time values.
      $this->configuration['text_string'] = $this->token->replace($this->configuration['text_string']);
      // Let modules alter the text as needed.
      $this->configuration['text_string'] = $this->getAlteredText($this->configuration['text_string']);
    }

    // Create the wrapper image object from scratch.
    $wrapper = $this->imageFactory->get();

    // Return the wrapper built by the toolkit operation.
    $ret = $wrapper->apply('text_to_wrapper', [
      'font_uri'                   => $this->configuration['font']['uri'],
      'font_size'                  => $this->configuration['font']['size'],
      'font_angle'                 => $this->configuration['font']['angle'],
      'font_color'                 => $this->configuration['font']['color'],
      'font_stroke_mode'           => $this->configuration['font']['stroke_mode'],
      'font_stroke_color'          => $this->configuration['font']['stroke_color'],
      'font_outline_top'           => $this->configuration['font']['outline_top'],
      'font_outline_right'         => $this->configuration['font']['outline_right'],
      'font_outline_bottom'        => $this->configuration['font']['outline_bottom'],
      'font_outline_left'          => $this->configuration['font']['outline_left'],
      'font_shadow_x_offset'       => $this->configuration['font']['shadow_x_offset'],
      'font_shadow_y_offset'       => $this->configuration['font']['shadow_y_offset'],
      'font_shadow_width'          => $this->configuration['font']['shadow_width'],
      'font_shadow_height'         => $this->configuration['font']['shadow_height'],
      'layout_padding_top'         => $this->configuration['layout']['padding_top'],
      'layout_padding_right'       => $this->configuration['layout']['padding_right'],
      'layout_padding_bottom'      => $this->configuration['layout']['padding_bottom'],
      'layout_padding_left'        => $this->configuration['layout']['padding_left'],
      'layout_x_pos'               => $this->configuration['layout']['x_pos'],
      'layout_y_pos'               => $this->configuration['layout']['y_pos'],
      'layout_x_offset'            => $this->configuration['layout']['x_offset'],
      'layout_y_offset'            => $this->configuration['layout']['y_offset'],
      'layout_background_color'    => $this->configuration['layout']['background_color'],
      'layout_overflow_action'     => $this->configuration['layout']['overflow_action'],
      'text_maximum_width'         => $this->configuration['text']['maximum_width'],
      'text_fixed_width'           => $this->configuration['text']['fixed_width'],
      'text_align'                 => $this->configuration['text']['align'],
      'text_line_spacing'          => $this->configuration['text']['line_spacing'],
      'text_string'                => $this->configuration['text_string'],
      'debug_visuals'              => $this->configuration['debug_visuals'] ?? FALSE,
      'canvas_width'               => $this->info['image_width'],
      'canvas_height'              => $this->info['image_height'],
    ]);

    return $ret ? $wrapper : NULL;
  }

  /**
   * Recalculate background image size.
   *
   * When wrapper overflows the original image, and autoextent is set on.
   */
  protected function canvasResizeNeeded(ImageInterface $wrapper, array &$info): bool {

    $resized = FALSE;

    // Background image dimensions.
    $image_width = $info['image_width'];
    $image_height = $info['image_height'];

    // Wrapper image dimensions.
    $wrapper_width = $wrapper->getWidth();
    $wrapper_height = $wrapper->getHeight();

    // Determine wrapper offset, based on placement option.
    // This is just taking into account the image and wrapper dimensions;
    // additional offset explicitly specified is considered later.
    $x_offset = ImageUtility::getKeywordOffset($this->configuration['layout']['x_pos'], $image_width, $wrapper_width);
    $y_offset = ImageUtility::getKeywordOffset($this->configuration['layout']['y_pos'], $image_height, $wrapper_height);

    // The position of the wrapper, once offset as per explicit
    // input. Width and height are not relevant for the algorithm,
    // but would be determined as follows:
    // @codingStandardsIgnoreStart
    // @code
    //  'width' => ($wrapper_width < $image_width) ? $wrapper_width + abs($this->configuration['layout']['x_offset']) : $wrapper_width;
    //  'height' = ($wrapper_height < $image_height) ? $wrapper_height + abs($this->configuration['layout']['y_offset']) : $wrapper_height;
    // @endcode
    // @codingStandardsIgnoreEnd
    $info['wrapper_xpos'] = $x_offset + $this->configuration['layout']['x_offset'];
    $info['wrapper_ypos'] = $y_offset + $this->configuration['layout']['y_offset'];

    // If offset wrapper overflows to the left, background image
    // will be shifted to the right.
    if ($info['wrapper_xpos'] < 0) {
      $info['image_width'] = $image_width - $info['wrapper_xpos'];
      $info['image_xpos'] = -$info['wrapper_xpos'];
      $info['wrapper_xpos'] = 0;
      $info['frame_left'] = $info['image_width'] - $image_width;
      $resized = TRUE;
    }

    // If offset wrapper overflows to the top, background image
    // will be shifted to the bottom.
    if ($info['wrapper_ypos'] < 0) {
      $info['image_height'] = $image_height - $info['wrapper_ypos'];
      $info['image_ypos'] = -$info['wrapper_ypos'];
      $info['wrapper_ypos'] = 0;
      $info['frame_top'] = $info['image_height'] - $image_height;
      $resized = TRUE;
    }

    // If offset wrapper overflows to the right, background image
    // will be extended to the right.
    if (($info['wrapper_xpos'] + $wrapper_width) > $info['image_width']) {
      $tmp = $info['image_width'];
      $info['image_width'] = $info['wrapper_xpos'] + $wrapper_width;
      $info['frame_right'] = $info['image_width'] - $tmp;
      $resized = TRUE;
    }

    // If offset wrapper overflows to the bottom, background image
    // will be extended to the bottom.
    if (($info['wrapper_ypos'] + $wrapper_height) > $info['image_height']) {
      $tmp = $info['image_height'];
      $info['image_height'] = $info['wrapper_ypos'] + $wrapper_height;
      $info['frame_bottom'] = $info['image_height'] - $tmp;
      $resized = TRUE;
    }

    return $resized;
  }

  /**
   * Get the stroke mode for the font.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The stroke mode.
   */
  protected function strokeMode(): ?TranslatableMarkup {
    if ($this->configuration['font']['stroke_mode'] == 'outline' && ($this->configuration['font']['outline_top'] || $this->configuration['font']['outline_right'] || $this->configuration['font']['outline_bottom'] || $this->configuration['font']['outline_left'])) {
      return $this->t('Outline');
    }
    elseif ($this->configuration['font']['stroke_mode'] == 'shadow') {
      return $this->t('Shadow');
    }
    else {
      return NULL;
    }
  }

  /**
   * Builds a render array with the Text Overlay preview.
   *
   * Requires the Textimage module to be installed.
   *
   * @param array $data
   *   An array with the plugin configuration to be used for rendering the
   *   preview.
   *
   * @return array
   *   A simple array with two elements, indicating:
   *   - success of the preview build.
   *   - a render array of the preview, or markup describing the failure if
   *     the build was unsuccessful.
   */
  protected function buildPreviewRender(array $data): array {
    // If no font file specified, nothing to preview.
    if (empty($data['font']['uri'])) {
      return [
        FALSE,
        ['#markup' => $this->t("No font specified. Select a font and click on 'Refresh preview'.")],
      ];
    }
    // Need the textimage.factory service to produce the preview image.
    $textimage_factory = $this->getTextimageFactory();
    if (!$textimage_factory) {
      return [
        FALSE,
        ['#markup' => $this->t("The Textimage module is not installed. It is not possible to provide the text overlay preview image.")],
      ];
    }
    $data['layout']['x_pos'] = 'center';
    $data['layout']['y_pos'] = 'center';
    $data['layout']['x_offset'] = 0;
    $data['layout']['y_offset'] = 0;
    $data['layout']['overflow_action'] = 'extend';
    $data['layout']['extended_color'] = NULL;
    $data['debug_visuals'] = $data['preview_bar']['debug_visuals'] ?? FALSE;
    try {
      $textimage = $textimage_factory->get()
        ->setEffects([
          ['id' => 'image_effects_text_overlay', 'data' => $data],
        ])
        ->setTemporary(TRUE)
        ->process([$data['text_string']])
        ->buildImage();
      $render = [
        '#theme' => 'textimage_formatter',
        '#uri' => $textimage->getUri(),
        '#width' => $textimage->getWidth(),
        '#height' => $textimage->getHeight(),
        '#title' => $this->t('Text overlay preview'),
        '#alt' => $this->t('Text overlay preview.'),
      ];
      $textimage->getBubbleableMetadata()->applyTo($render);
      return [
        TRUE,
        $render,
      ];
    }
    catch (\Exception $e) {
      return [
        FALSE,
        ['#markup' => $this->t("Could not build a preview of the text overlay.")],
      ];
    }
  }

}
