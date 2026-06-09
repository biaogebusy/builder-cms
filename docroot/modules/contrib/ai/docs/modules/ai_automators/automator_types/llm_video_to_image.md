# LLM: Video To Image (Experimental)

## Field it applies to

- **Field type:** `image`
- **Potential target:** `file` entities

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmVideoToImage.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmVideoToImage.php?ref_type=heads)

## Description

The **LLM: Video To Image (Experimental)** rule automatically generates images from video content.
It uses AI models to analyze video frames and audio transcripts to determine relevant scenes or moments, then extracts and stores images based on identified timestamps.
This is experimental functionality and may require refinement for production use.

## Requirements

- **FFMpeg** must be installed on the server and accessible in the system path.
- A configured **speech-to-text provider** (e.g., Whisper) if audio transcription is involved.
- The Token module (optional) for dynamic prompt generation using entity tokens.

## Form fields required

- **Cutting Prompt**
  Instructions for the AI model to determine which frames should be extracted.
  Supports tokens if the Token module is enabled.

## Example use cases

1. Generate cover images or thumbnails for uploaded videos automatically.
2. Extract significant frames from event recordings for gallery use.
3. Create preview images for product demos or tutorials.
4. Auto-generate images for video blog posts.
5. Produce visual highlights for marketing or promotional videos.
6. Capture key scenes for documentation or reporting.
7. Assist editorial workflows by suggesting scene cuts.
8. Provide visual assets for social sharing from long-form video content.

---

*This documentation was AI-generated.*
