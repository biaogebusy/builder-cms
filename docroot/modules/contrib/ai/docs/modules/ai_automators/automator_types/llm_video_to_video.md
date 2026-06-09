# LLM: Video To Video (Experimental)

## Field it applies to

- **Field type:** `file`
- **Potential target:** File fields containing video files (e.g., `video/mp4`)

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmVideoToVideo.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmVideoToVideo.php?ref_type=heads)

## Description

The **LLM: Video To Video (Experimental)** rule allows automatic cutting and mixing of video files based on AI analysis of scenes, audio transcriptions, and instructions you provide.
It generates timestamps for sections to cut and can combine multiple clips into a single video file if configured.
The rule verifies that `ffmpeg` is installed on the system before it is available for use.

## Requirements

- **FFMpeg** must be installed on the server and accessible from the system path.

## Form fields required

- **Cutting Prompt**
  Type: `textarea`
  Description: Commands to specify how the video(s) should be cut or mixed. For example, you can specify scenes to remove or combine. Token replacements can be used if the Token module is installed.
  Default (if not saved): `""`

## Example use cases

1. Automatically remove all scenes where a specific phrase is spoken in the video.
2. Mix together clips where certain actions occur into a single highlight reel.
3. Cut out sections of training videos based on scripted instructions.
4. Create short promotional videos from long raw footage by specifying cut points.
5. Generate timestamped clips for social media sharing.
6. Remove unwanted portions (e.g., silent parts) from webinar recordings.
7. Assemble multiple scenes into a summary video for internal presentations.
8. Produce video snippets for inclusion in documentation or marketing material.

---

*This documentation was AI-generated.*
