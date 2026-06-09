# LLM: Image Alt Text

## Field it applies to

- **Field type:** `image`
- **Target:** `file`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmImageAltText.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmImageAltText.php?ref_type=heads)

## Description

**LLM: Image Alt Text** generates descriptive alternative text (alt text) for image fields using AI.
It analyzes image-related context and provides meaningful alt text suggestions that are stored in the image field.

## Requirements

- The target field must be of type `image` and contain a valid file reference.
- The automator runs only if the alt text is empty or missing.

## Behavior

- Automatically generates alt text for images without one.
- Uses AI models to produce meaningful descriptions.
- Stores the generated alt text in the image field’s alt attribute.
- Ensures the alt text is non-empty before storing.

## Example use cases

- Automatically describe uploaded images for accessibility.
- Ensure all content images meet alt text compliance for SEO and screen readers.

## Notes

- The automator does not include explanations in the alt text or expose the prompt output to users.
- The alt text generated is stored directly in the image field’s data array.

---

*This documentation was AI-generated.*
