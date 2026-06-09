# LLM: List

## Field it applies to

- **Field type:** `list_string`
- **Target:** N/A

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmListString.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmListString.php?ref_type=heads)

## Description

**LLM: List** helps automatically choose the most appropriate value from a predefined list (such as sentiment ratings, categories, or other enumerated options) based on the content of the entity.

## Requirements

- The target field must be of type `list_string` with defined allowed values.

## Configuration options

- **AI Model:** Select the AI model that will process the content.
- **Base field:** The field that provides the context for determining the appropriate list value.
- **Prompt:** Optional custom instructions that guide how values are selected based on the context.

## Example use cases

- Automatically assign sentiment (e.g., `negative`, `neutral`, `positive`) based on an article’s text.
- Tag a piece of content with an urgency level (`low`, `medium`, `high`) based on its description.
- Classify feedback, reviews, or support tickets into predefined categories.

## Notes

- The value is set only if it matches one of the allowed keys or labels in the list field configuration.
- If the AI returns the label instead of the key, the system maps it to the correct key before saving.
- Values that don’t match any configured option are not stored.

---

*This documentation was AI-generated.*
