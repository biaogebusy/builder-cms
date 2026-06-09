# LLM: List

## Field it applies to

- **Field type:** `list_float`
- **Target:** N/A

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmListFloat.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmListFloat.php?ref_type=heads)

## Description

**LLM: List** enables automatic selection of the most appropriate float value from a predefined list, based on the content of the entity.

## Requirements

- The target field must be of type `list_float` with configured allowed values.

## Configuration options

- **AI Model:** Select the AI model used for processing.
- **Base field:** The field providing the context to determine the appropriate list value.
- **Prompt:** Optional custom instruction to guide selection.

## Example use cases

- Automatically assign a sentiment score (e.g., -1.0 to 1.0) based on customer feedback.
- Set a confidence level for classified data based on the description text.
- Choose a predefined probability or risk score for certain entities.

## Notes

- The AI output is matched against configured keys (float values) or their corresponding labels.
- If the AI returns a label instead of a key, it is mapped to the correct key before saving.
- Values not matching any allowed option are discarded.

---

*This documentation was AI-generated.*
