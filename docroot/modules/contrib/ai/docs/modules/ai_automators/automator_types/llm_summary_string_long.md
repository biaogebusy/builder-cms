# Summarize: Text

## Field it applies to

- **Field type:** `string_long`, `text_long`, `text_with_summary`
- **Potential target:** String Long field

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmSummarizeToStringLong.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmSummarizeToStringLong.php?ref_type=heads)
## Description

The **Summarize: Text** rule generates condensed summaries from longer text content using the AI summarize endpoint.
Unlike chat-based LLM interactions, this uses dedicated summarization models optimized for extracting key information.

## Requirements

- A configured AI summarize provider, such as:
  - **Hugging Face** with models like `facebook/bart-large-cnn`, `sshleifer/distilbart-cnn-12-6`, or `google/pegasus-xsum`
  - **Google Chrome** built-in summarization API

## Example use cases

1. Auto-generate article summaries from body content.
2. Create teaser text for content listings.
3. Generate meta descriptions from page content.
4. Summarize long-form documentation into brief overviews.
5. Extract key points from research or report content.
6. Create newsletter snippets from full articles.
7. Generate preview text for search results.
8. Condense user-submitted content for moderation review.

---

*This documentation was AI-generated.*
