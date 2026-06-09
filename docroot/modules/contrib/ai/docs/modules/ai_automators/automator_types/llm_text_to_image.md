# LLM: Image Generation

## Field it applies to

- **Field type:** `image`
- **Target:** `file`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmImageGeneration.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmImageGeneration.php?ref_type=heads)

## Description

**LLM: Image Generation** uses AI to create image files based on contextual text from your entity. The generated image files are saved in the configured field.

## Requirements

- The target field must be of type `image`.
- The AI-generated output must produce valid image binary data.

## Behavior

- Generates prompts from context or token-rendered values.
- Uses the configured AI model to produce image binaries.
- Automatically generates filenames like `ai_generated.jpg`.
- Saves image files using file helper utilities and attaches them to the entity field.

## Example use cases

- Automatically generate profile pictures from descriptive text.
- Produce dynamic illustrations for articles based on their summaries.

## Notes

- Ensure your prompts guide the AI to produce coherent image descriptions suitable for your use case.
- The automator does not include explanations or prompt output in stored data.
- The generated files are validated to ensure they contain binary image data before being stored.

---

*This documentation was AI-generated.*
