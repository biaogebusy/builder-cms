# LLM: Taxonomy

## Field it applies to

- **Field type:** `entity_reference`
- **Potential target:** `taxonomy_term`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmTaxonomy.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmTaxonomy.php?ref_type=heads)

## Description

The **LLM: Taxonomy** rule helps identify, select, or create taxonomy terms from context text using AI chat models.
It supports automatic tag creation, searching for similar terms, and optional text manipulations like lowercase or uppercase conversion.

## Requirements

- A configured AI chat provider (e.g., OpenAI, Azure OpenAI).
- The field must target `taxonomy_term`.
- Optionally, auto-create must be enabled in the field settings if term creation is desired.

## Form fields required

- `Text Manipulation` (select: none, lowercase, uppercase, first character uppercase).
- `Find similar tags` (checkbox; available if auto-create is enabled).

## Example use cases

1. Automatically categorize articles using existing taxonomy terms.
2. Generate tags for blog posts based on content.
3. Auto-fill taxonomy references for imported data.
4. Suggest categories for user-submitted content.
5. Auto-create new tags when relevant categories don’t exist.
6. Clean up and standardize taxonomy terms (e.g., force lowercase).
7. Merge similar tags to avoid duplicates (e.g., "Jesus" → "Jesus Christ").
8. Assign taxonomy terms for product listings based on descriptions.

---

*This documentation was AI-generated.*
