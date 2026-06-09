# LLM: Video to Text

## Field it applies to

- **Field type:** `string_long`
- **Potential target:** Any long string field on content entities

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmVideoToStringLong.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmVideoToStringLong.php?ref_type=heads)

## Description

The **LLM: Video to Text** rule automatically generates string-long field content from video files.
It uses AI models to analyze video content, generate transcripts, and produce descriptive text.
This rule is useful for creating searchable summaries or metadata from video sources.

## Requirements

- **FFMpeg** must be installed on the server and accessible from the system path.
- A configured **speech-to-text provider** (e.g., Whisper or similar).

## Form fields required

- **Speech To Text Provider**
  A provider selector for speech-to-text processing of the audio.

## Example use cases

1. Generate metadata or descriptions for video files stored in media entities.
2. Create summaries for marketing or promotional videos.
3. Produce search-friendly text from training or instructional videos.
4. Generate captions or subtitle drafts from video content.
5. Provide short summaries for news or event recordings.
6. Extract key points from product demo videos for catalogs.
7. Auto-generate descriptions for videos uploaded by users.
8. Populate SEO fields with AI-generated summaries from video content.

---

*This documentation was AI-generated.*
