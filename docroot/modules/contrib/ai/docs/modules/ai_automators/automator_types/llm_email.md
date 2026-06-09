# LLM: Email

## Field it applies to

- **Field type:** `email`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmEmail.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmEmail.php?ref_type=heads)

## Description

**LLM: Email** extracts valid email addresses from context text using AI and stores them in an email field.
It ensures only properly formatted email addresses are accepted.

## Requirements

- Field must be of type `email`.

## Configuration options

There are no additional configuration options for this automator beyond standard automator settings.

## Behavior

- Scans the provided context text for valid email addresses.
- Stores email addresses that pass validation (`FILTER_VALIDATE_EMAIL`).
- If multiple emails are returned, all valid ones are stored according to field cardinality.

## Example use cases

- Extract and store contact email addresses from a body of text.
- Automatically populate email fields from imported or pasted content.

## Notes

- The automator ensures that only syntactically valid emails are stored.
- It does not output or expose AI prompt responses to the end user.
- The system enforces strict RFC-compliant JSON parsing from AI output.

---

*This documentation was AI-generated.*
