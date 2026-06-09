# LLM: FAQ Field

## Field it applies to

- **Field type:** `faqfield`
- **Target:** *(none)*

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmFaqField.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmFaqField.php?ref_type=heads)

## Description

**LLM: FAQ Field** generates FAQ entries (question and answer pairs) using AI based on provided context.
It is useful for auto-creating relevant FAQs from content or descriptions.

## Requirements

- The field must be of type `faqfield`.
- Each generated entry must include:
  - A non-empty question.
  - A non-empty answer.

## Behavior

- Generates a list of FAQs (typically 5) based on the context.
- Validates each entry to ensure both question and answer are present.
- Stores the generated question-answer pairs in the field.

## Example use cases

- Auto-generate FAQs for product descriptions.
- Summarize content in a question-answer format for support articles.

## Notes

- The automator ensures no explanations or prompt outputs are exposed to the user.
- The JSON format enforced:
  ```json
  [{"value": {"question": "The question to ask", "answer": "The answer"}}]
  ```
- Directly stores the structured FAQ data on the field.

## Field Widget Action Integration

To use this automator with a Field Widget Action button in the content edit form, see the complete setup guide:

- [FAQ Field + `faqfield_default` widget example](../examples/faq-field.md)

---

*This documentation was AI-generated.*
