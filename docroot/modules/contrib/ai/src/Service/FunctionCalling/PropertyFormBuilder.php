<?php

namespace Drupal\ai\Service\FunctionCalling;

use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Textarea;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This service helps with creating a form from function calling properties.
 */
class PropertyFormBuilder {

  /**
   * Constructor.
   *
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager $functionCallPluginManager
   *   The function call plugin manager.
   */
  public function __construct(
    protected FunctionCallPluginManager $functionCallPluginManager,
  ) {
  }

  /**
   * Create the form elements for the function call properties.
   *
   * @param string $function_call_id
   *   The function call id.
   *
   * @return array
   *   The form elements.
   */
  public function createFormElements(string $function_call_id): array {
    $function_call = $this->functionCallPluginManager->createInstance($function_call_id);
    $normalized = $function_call->normalize();
    $form = [];
    foreach ($normalized->getProperties() as $property) {
      $form[$property->getName()] = $this->formElementFromProperty($property);
    }

    return $form;
  }

  /**
   * From element from property.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface $property
   *   The property.
   *
   * @return array
   *   The form element.
   */
  public function formElementFromProperty(ToolsPropertyInputInterface $property): array {
    $form_element = [
      '#title' => $property->getName(),
      '#description' => $property->getDescription(),
      '#default_value' => $property->getDefault(),
      '#required' => $property->isRequired(),
    ];
    switch ($property->getType()) {
      case 'string':
        if (!empty($property->getEnum())) {
          // The options key should also be the options value.
          $values = [];
          // If its not required, we can add a null value.
          if (!$property->isRequired()) {
            $values[''] = '- None -';
          }
          foreach ($property->getEnum() as $value) {
            $values[$value] = $value;
          }

          $form_element['#type'] = 'select';
          $form_element['#options'] = $values;
        }
        else {
          // We don't know the size, so a 2 rows textarea is a good default.
          if ($property->getMaxLength() && $property->getMaxLength() < 255) {
            $form_element['#type'] = 'textfield';
          }
          else {
            $form_element['#type'] = 'textarea';
            $form_element['#rows'] = 1;
          }
        }
        if ($property->getExampleValue()) {
          $form_element['#attributes']['placeholder'] = $property->getExampleValue();
        }
        break;

      case 'bool':
      case 'boolean':
        $form_element['#type'] = 'checkbox';
        break;

      case 'number':
      case 'integer':
        // Type casting is happening and we don't want form validation, so text.
        $form_element['#type'] = 'textfield';
        if ($property->getExampleValue()) {
          $form_element['#attributes']['placeholder'] = $property->getExampleValue();
        }
        if (!empty($property->getEnum())) {
          $form_element['#type'] = 'select';
          $form_element['#options'] = $property->getEnum();
        }
        break;

      case 'array':
        if (!empty($property->getEnum())) {
          $form_element['#type'] = 'select';
          $form_element['#options'] = [];
          foreach ($property->getEnum() as $value) {
            if (is_array($value)) {
              $form_element['#options'][$value['const']] = $value['title'];
            }
            else {
              $form_element['#options'][$value] = $value;
            }
          }
          $form_element['#multiple'] = TRUE;
        }
        else {
          $form_element['#type'] = 'textarea';
          $form_element['#rows'] = 5;
          $form_element['#value_callback'] = [self::class, 'splitContextList'];
          // Convert array default value to newline-separated string for
          // textarea.
          if (is_array($form_element['#default_value'])) {
            $form_element['#default_value'] = implode("\n", $form_element['#default_value']);
          }
          $form_element['#description'] .= new FormattableMarkup(
            '@description @list',
            [
              '@description' => $form_element['#description'],
              '@list' => new TranslatableMarkup('Enter one value per row.'),
            ],
          );
        }
        break;

      default:
        $form_element['#type'] = 'textfield';
        break;
    }
    return $form_element;
  }

  /**
   * Value callback to split textarea into multiple values.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   * @param mixed $input
   *   The incoming input to populate the form element. If this is FALSE,
   *   the element's default value should be returned.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return mixed
   *   The value to assign to the element.
   */
  public static function splitContextList(array &$element, mixed $input, FormStateInterface $form_state) {
    $value = Textarea::valueCallback($element, $input, $form_state);
    return $value !== NULL ?
      array_filter(array_map('trim', explode("\n", $value))) :
      NULL;
  }

}
