<?php

namespace Drupal\metatag_custom_tags\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\MetatagTagPluginManager;
use Drupal\metatag_custom_tags\MetaTagCustomTagInterface;

/**
 * Form handler for the MetaTag Custom Tag entity type.
 */
class MetaTagCustomTagForm extends EntityForm {

  /**
   * The metatag tag plugin manager.
   *
   * @var \Drupal\metatag\MetatagTagPluginManager
   */
  protected MetatagTagPluginManager $metatagTagPluginManager;

  /**
   * Constructs a new MetaTagCustomTagForm.
   */
  public function __construct(
    MetatagTagPluginManager $metatagTagPluginManager,
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->metatagTagPluginManager = $metatagTagPluginManager;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($container) {
    return new static(
      $container->get('plugin.manager.metatag.tag'),
      $container->get('messenger'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#description' => $this->t('Specify the name of the custom tag.'),
      '#required' => TRUE,
      '#default_value' => $entity->label() ?? '',
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#maxlength' => 64,
      '#description' => $this->t('Enter a unique identifier for this custom tag. This identifier can contain lowercase letters, numbers and underscores. This is only used internally, it is not used in the actual HTML output.'),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
        'replace_pattern' => '[^a-z0-9_]+',
        'replace' => '_',
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#maxlength' => 255,
      '#description' => $this->t('Specify the description of the Custom tag.'),
      '#required' => TRUE,
      '#default_value' => $entity->get('description') ?? '',
    ];

    $form['htmlElement'] = [
      '#type' => 'select',
      '#title' => $this->t('HTML element'),
      '#options' => [
        'meta' => $this->t('Meta'),
        'link' => $this->t('Link'),
      ],
      '#description' => $this->t('Select the HTML element of the Custom tag.'),
      '#required' => TRUE,
      '#default_value' => $entity->get('htmlElement') ?? 'meta',
    ];

    $form['htmlValueAttribute'] = [
      '#type' => 'select',
      '#title' => $this->t('Value attribute'),
      '#options' => [
        'content' => $this->t('Content'),
        'href' => $this->t('Href'),
        'charset' => $this->t('Charset'),
      ],
      '#description' => $this->t('Select the Value attribute of the Custom tag. This determines which HTML attribute will contain the user-provided content.'),
      '#required' => TRUE,
      '#default_value' => $entity->get('htmlValueAttribute') ?? 'content',
    ];

    $form['attributes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('HTML Attributes'),
      '#tree' => TRUE,
      '#description' => $this->t('Define the HTML attributes for this custom meta tag. The first attribute serves as the primary identifier and is required. Additional attributes are optional and provide fixed values for all instances of this tag.'),
      '#prefix' => '<div id="attributes-wrapper">',
      '#suffix' => '</div>',
    ];

    $attributes = $form_state->get('attributes') ?? $entity->get('attributes') ?? [];

    // Ensure there is always at least one attribute
    if (empty($attributes)) {
      $attributes[] = ['name' => '', 'value' => ''];
    }

    $form_state->set('attributes', $attributes);

    foreach ($attributes as $index => $attribute) {
      $is_first = $index === 0;

      $form['attributes'][$index] = [
        '#type' => 'fieldset',
        '#title' => $is_first ? $this->t('Primary attribute (Required)') : $this->t('Additional attribute @num', ['@num' => $index]),
        '#description' => $is_first ? $this->t('This attribute identifies the meta tag and is required.') : $this->t('This attribute provides a fixed value for all instances of this tag.'),
      ];

      $form['attributes'][$index]['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute name'),
        '#default_value' => $attribute['name'] ?? '',
        '#required' => $is_first,
        '#description' => $is_first ? $this->t('Common values: name, property, rel, http-equiv, itemprop.') : $this->t('Additional attribute name (e.g., type, sizes).'),
      ];

      $form['attributes'][$index]['value'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute value'),
        '#default_value' => $attribute['value'] ?? ($is_first && $entity->isNew() ? $entity->id() : ''),
        '#required' => $is_first,
        '#description' => $is_first ? $this->t('For new tags, defaults to the machine name. For existing tags, this identifies the specific meta tag.') : $this->t('Fixed value for this attribute.'),
      ];

      // Add remove button for additional attributes (not the first one)
      if (!$is_first) {
        $form['attributes'][$index]['remove'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_' . $index,
          '#submit' => ['::removeAttribute'],
          '#ajax' => [
            'callback' => '::attributesCallback',
            'wrapper' => 'attributes-wrapper',
          ],
          '#limit_validation_errors' => [],
        ];
      }
    }

    $form['attributes']['add_more'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another attribute'),
      '#submit' => ['::addMoreAttributes'],
      '#ajax' => [
        'callback' => '::attributesCallback',
        'wrapper' => 'attributes-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    // Add examples section
    $form['examples'] = [
      '#type' => 'details',
      '#title' => $this->t('Examples'),
      '#collapsed' => TRUE,
    ];

    $form['examples']['markup'] = [
      '#markup' => $this->t('
        <h4>Common Examples:</h4>
        <ul>
          <li><strong>Basic meta tag:</strong><br>
            Primary attribute: name = "description"<br>
            Result: &lt;meta name="description" content="[user-input]" /&gt;</li>
          <li><strong>Open Graph tag:</strong><br>
            Primary attribute: property = "og:title"<br>
            Result: &lt;meta property="og:title" content="[user-input]" /&gt;</li>
          <li><strong>Twitter Card:</strong><br>
            Primary attribute: name = "twitter:card"<br>
            Result: &lt;meta name="twitter:card" content="[user-input]" /&gt;</li>
          <li><strong>Link with type:</strong><br>
            Primary attribute: rel = "icon"<br>
            Additional attribute: type = "image/svg+xml"<br>
            Result: &lt;link rel="icon" type="image/svg+xml" href="[user-input]" /&gt;</li>
        </ul>
      '),
    ];
 
    return $form;
  }

  /**
   * AJAX callback for attributes.
   */
  public function attributesCallback(array &$form, FormStateInterface $form_state) {
    return $form['attributes'];
  }

  /**
   * Submit handler to add more attributes.
   */
  public function addMoreAttributes(array &$form, FormStateInterface $form_state) {
    $attributes = $form_state->get('attributes') ?? [];
    $attributes[] = ['name' => '', 'value' => ''];
    $form_state->set('attributes', $attributes);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler to remove an attribute.
   */
  public function removeAttribute(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $index = (int) str_replace('remove_', '', $trigger['#name']);

    $attributes = $form_state->get('attributes') ?? [];
    unset($attributes[$index]);
    $attributes = array_values($attributes); // Re-index array

    $form_state->set('attributes', $attributes);
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $attributes = $form_state->getValue('attributes') ?? [];

    // Remove empty attributes and the add_more element
    unset($attributes['add_more']);
    $attributes = array_filter($attributes, function($attr) {
      return is_array($attr) && !empty($attr['name']) && !empty($attr['value']);
    });

    if (empty($attributes)) {
      $form_state->setError($form['attributes'], $this->t('At least one attribute with both name and value must be provided.'));
      return;
    }

    // Validate first attribute exists and is complete
    if (empty($attributes[0]['name']) || empty($attributes[0]['value'])) {
      $form_state->setError($form['attributes'][0], $this->t('The primary attribute name and value are required.'));
    }

    // Set cleaned attributes back to form state
    $form_state->setValue('attributes', array_values($attributes));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger->addStatus(
      match ($result) {
        \SAVED_NEW => $this->t('Created %label Custom tag.', $message_args),
        \SAVED_UPDATED => $this->t('Updated %label Custom tag.', $message_args),
      }
    );

    // Clear cached definitions using injected service.
    $this->metatagTagPluginManager->clearCachedDefinitions();

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * Helper function to check whether the configuration entity exists.
   */
  public function exist($id): bool {
    $entity = $this->entityTypeManager->getStorage('metatag_custom_tag')->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

  /**
   * Route title callback.
   *
   * @param \Drupal\metatag_custom_tags\MetaTagCustomTagInterface $metatag_custom_tag
   *   Custom tag entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translated route title.
   */
  public function getTitle(MetaTagCustomTagInterface $metatag_custom_tag): TranslatableMarkup {
    return $this->t('Edit Custom tag @label', [
      '@label' => $metatag_custom_tag->label(),
    ]);
  }

}
