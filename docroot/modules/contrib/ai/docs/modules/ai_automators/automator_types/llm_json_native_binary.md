# LLM: JSON Field

## Field it applies to

- **Field type:** `json_native_binary`
- **Target:** N/A

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmJsonNativeBinary.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmJsonNativeBinary.php?ref_type=heads)

## Description

**LLM: JSON Field** generates JSON data from context text and stores it in a `json_native_binary` field. It is designed to produce structured data like lists of items, properties, or records extracted from unstructured text.

## Requirements

- The target field must be of type `json_native_binary`.
- The AI output must be valid RFC8259-compliant JSON.

## Configuration options

- **AI Model:** The AI model used for JSON generation.
- **Base field:** The source field containing the context text.

## Example use cases

- Extract a filmography list for an actor including movie titles and release years.
- Convert unstructured descriptions into structured JSON objects for further processing.

## Notes

- The system validates that output is well-formed JSON before storing it.
- The AI is guided to output clean JSON without any extra explanation text.
- The raw JSON string is stored directly in the field.

---

*This documentation was AI-generated.*
