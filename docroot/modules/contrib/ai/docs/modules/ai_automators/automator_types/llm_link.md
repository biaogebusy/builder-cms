# LLM: Link

## Field it applies to

- **Field type:** `link`
- **Target:** N/A

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmLink.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmLink.php?ref_type=heads)

## Description

**LLM: Link** enables automatic extraction of valid links (URLs) and their optional titles from text fields in your entity. This can help populate link fields based on content context.

## Requirements

- The target field must be of type `link`.
- The AI output must contain valid URLs. If titles are required by field configuration, those must also be present.

## Configuration options

- **AI Model:** The AI model used for link detection.
- **Base field:** The field providing the text to analyze.

## Example use cases

- Extract all URLs from a body field and store them in a dedicated link field.
- Populate a references or resources link field from the main content.

## Notes

- The system validates that URLs follow standard formats and ensures any required link titles are provided.
- Titles will be set to an empty string if the field is configured not to use titles.
- Values that do not pass validation will be discarded.
- The AI is instructed to produce clean JSON output with `uri` and optional `title` for each link.

---

*This documentation was AI-generated.*
