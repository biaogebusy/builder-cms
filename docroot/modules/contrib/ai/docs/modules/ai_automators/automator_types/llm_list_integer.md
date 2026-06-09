# LLM: List

## Field it applies to

- **Field type:** `list_integer`
- **Target:** N/A

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmListInteger.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmListInteger.php?ref_type=heads)

## Description

**LLM: List** enables automatic selection of the most appropriate numeric option from a predefined list, based on the content of the entity.

## Requirements

- The target field must be of type `list_integer` with configured allowed values.

## Configuration options

- **AI Model:** Select the AI model used for processing.
- **Base field:** The field providing the context to determine the appropriate list value.
- **Prompt:** Optional custom instruction to guide selection.

## Example use cases

- Automatically assign a numeric rating (e.g., 1 to 5) based on product reviews.
- Set a priority level (e.g., 0 for low, 1 for medium, 2 for high) for support tickets based on the description.
- Categorize content by predefined numeric codes representing internal classifications.

## Notes

- The AI output is matched against configured keys (integers) or labels (values).
- If the AI returns a label instead of a key, it is mapped to the correct key before saving.
- Values not matching any allowed option are discarded.

---

*This documentation was AI-generated.*
