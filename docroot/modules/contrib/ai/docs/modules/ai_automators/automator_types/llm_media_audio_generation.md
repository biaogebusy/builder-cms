# LLM: Media Audio Generation

## Field it applies to

- **Field type:** `entity_reference`
- **Target:** `media` entity

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmMediaAudioGeneration.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmMediaAudioGeneration.php?ref_type=heads)

## Description

**LLM: Media Audio Generation** creates audio files based on the text content of an entity and stores them in media entities.
The system automatically generates audio in formats like MP3 and associates them with a selected media type.

## Requirements

- The **Media module** must be installed with at least one media type configured to accept audio files.
- An AI provider capable of text-to-speech generation (such as OpenAI, ElevenLabs, or similar).

## Configuration options

- **Media Type:** Choose the media type where generated audio will be stored.
- **Base field:** Select the text field used as the source for generating audio (configured when setting up the automator).
- **AI Model:** The AI model used for generating audio (chosen based on your provider's capabilities).

## Example use cases

- Automatically generate audio versions of articles, blog posts, or announcements.
- Create podcasts or voiceovers from text content for accessibility purposes.
- Provide audio summaries for news items or product descriptions.

## Notes

- The audio files are stored as media entities and linked to your configured media field.
- The file name defaults to `ai_generated.mp3`, but can be customized in the code if needed.
- The media entity will use the generated audio file and be named based on the content used to create it.

---

*This documentation was AI-generated.*
