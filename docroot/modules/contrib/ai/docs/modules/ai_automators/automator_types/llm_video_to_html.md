# LLM: Video To HTML (Experimental)

## Field it applies to

- **Field type:** `text_long`
- **Potential target:** *none*

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmVideoToHtml.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmVideoToHtml.php?ref_type=heads)

## Description

The **LLM: Video To HTML (Experimental)** rule generates structured HTML content based on video analysis.
It uses video screenshots and audio transcripts to compose rich HTML, including images, captions, and text, according to provided instructions.
The output is designed for insertion into CKEditor or similar WYSIWYG editors.

## Requirements

- **FFMpeg** must be installed on the server and available in the system path.
- A configured **speech-to-text provider** for transcription (e.g., Whisper).
- The Token module (optional) to support dynamic prompts using entity tokens.

## Form fields required

- **Generating Prompt**
  Defines instructions for the AI model regarding HTML generation (e.g., structure, tags to use, number of sections, image placement).
  Supports tokens if the Token module is enabled.

## Example use cases

1. Automatically create blog article HTML from a recorded presentation.
2. Generate rich media reports from event recordings.
3. Build marketing content with structured text and images from product demos.
4. Create documentation snippets from tutorial videos.
5. Generate newsletter-ready HTML sections from webinars.
6. Summarize interviews or meetings with transcribed quotes and images.
7. Auto-compose content for educational platforms.
8. Assist editors in producing visually enriched stories from raw video.

---

*This documentation was AI-generated.*
