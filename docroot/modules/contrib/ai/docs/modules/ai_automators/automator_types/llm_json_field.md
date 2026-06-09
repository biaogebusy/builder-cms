# LLM: JSON Field

## Field it applies to

- **Field type:** `json`
- **Target:** N/A

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmJsonField.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmJsonField.php?ref_type=heads)

## Description

**LLM: JSON Field** automatically generates and stores structured JSON data in `json` fields. The AI processes context text and outputs valid JSON in line with RFC8259.

## Requirements

- The target field must be of type `json`.
- AI output must be valid JSON, as checked by `json_decode()`.

## Behavior

- The automator generates prompts based on configured context and tokens.
- The AI output is cleaned of formatting artifacts (e.g., code block wrappers).
- Only valid JSON data is stored.
- No additional explanations or comments are included in the stored value.

## Example use cases

- Generate structured data from text, such as event schedules, product specs, or key-value mappings.
- Transform unstructured descriptions into machine-readable JSON format.

## Notes

- The JSON structure is determined by your prompt instructions.
- Use tokens like `{{ context }}` to inject field or entity data into the AI request.
- Ensure your prompt guides the AI toward producing the expected JSON structure.

---

*This documentation was AI-generated.*
