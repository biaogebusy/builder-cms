# LLM: Custom Field

## Field it applies to

- **Field type:** `custom`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmCustomField.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmCustomField.php?ref_type=heads)

## Description

**LLM: Custom Field** is designed for complex field structures where AI-generated data must fill multiple subfields (e.g., quotes, translations, names, roles).
It supports customizable prompts and examples for one-shot learning.

## Requirements

- Field must be of type `custom`.
- Field should have field settings defining its subfields (via `field_settings`).

## Configuration options

- **Subfield instructions:** Textareas to specify how each subfield should be filled.
- **One-shot examples:** Textareas for example values to guide the AI.

## Behavior

- Generates JSON-encoded structured data for the custom field’s subfields.
- Allows form configuration of prompt instructions and example values.
- Validates that the result is a well-formed array before storing.

## Example use cases

- Extract quotes, translations, speaker names, and roles from interview transcripts.
- Populate structured recipe data with ingredients, quantities, and preparation steps.
- Generate product specifications including dimensions, weight, and material.
- Build structured event data with title, date, time, and location.
- Fill in legal contract summaries with clause title, description, and references.
- Create FAQ entries with question, answer, and category tags.
- Generate structured bibliographic entries including author, title, and publication date.
- Extract metadata from scientific abstracts (e.g., objective, method, result).
- Populate CV sections with position, employer, duration, and responsibilities.
- Structure meeting minutes with topics, decisions, and action items.

## Notes

- The AI-generated JSON is consumed directly without exposing raw output to end users.
- Each subfield’s generation instructions and examples help tailor AI responses for accuracy.
- Validation is basic (checks for array structure). Add further checks if needed.

---

*This documentation was AI-generated.*
