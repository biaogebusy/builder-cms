# LLM: JSON Field

## Field it applies to

- **Field type:** `json_native`
- **Target:** N/A

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmJsonNative.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmJsonNative.php?ref_type=heads)

## Description

**LLM: JSON Field** enables automatic population of `json_native` fields using AI-generated structured data. It processes contextual text and generates valid RFC8259-compliant JSON.

## Requirements

- The target field must be of type `json_native`.
- The AI-generated output must be valid JSON, as validated by `json_decode()`.

## Behavior

- The automator generates prompts based on configured context and tokens.
- It accepts and stores valid JSON outputs as field values.
- The system normalizes output by removing code block wrappers and whitespace.
- Invalid JSON is rejected.

## Example use cases

- Automatically generate structured data such as product attributes, event details, or contact information.
- Extract lists or key-value data sets from unstructured context.

## Notes

- The output structure is defined by your prompt instructions. The system does not enforce a schema beyond requiring valid JSON.
- Designed for flexibility — suitable for both simple and complex JSON structures.
- Include `{{ context }}` and tokens in prompts to guide the AI output accurately.

---

*This documentation was AI-generated.*
