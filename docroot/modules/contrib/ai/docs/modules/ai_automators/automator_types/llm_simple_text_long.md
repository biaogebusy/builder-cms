# LLM: Text (simple)

## Field it applies to

- **Field type:** `text_long`
- **Potential target:** (none)

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmSimpleTextLong.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmSimpleTextLong.php?ref_type=heads)

## Description

The **LLM: Text (simple)** rule generates or transforms long-form text using a simple language model chat interface.
It allows optional extraction of code blocks (e.g., HTML, JSON) and can apply a selected text format to the output.

## Requirements

- A configured AI chat provider (e.g., OpenAI, Azure)

## Form fields required

- `Prompt` or `Token` (depending on configuration)
- AI model selection
- **Text format:** Optional text format selector for applying a specific format to generated text
- **Code block extraction type:** (Optional) Choose a code block type to extract (e.g., HTML, JSON, PHP)

## Example use cases

1. Generate detailed content for body fields or long descriptions.
2. Automatically create long-form summaries or reports.
3. Enrich product descriptions or event details with AI-generated text.
4. Generate formatted HTML snippets for CKEditor.
5. Extract specific code block formats from LLM output (e.g., HTML, JSON).
6. Build FAQs or guide content automatically.
7. Rewrite or clean up existing long text fields.
8. Generate introductory or concluding paragraphs for articles.

---

*This documentation was AI-generated.*
