# LLM: Media Image Generation

## Field it applies to

- **Field type:** `entity_reference`
- **Target:** `media` entity

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmMediaImageGeneration.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmMediaImageGeneration.php?ref_type=heads)

## Description

**LLM: Media Image Generation** automatically creates media images based on the content's context.
It leverages an AI-powered text-to-image model to produce images and store them as media entities in a chosen media type.

## Requirements

- The Media module must be installed and configured with appropriate media types.
- A functional AI provider supporting text-to-image generation (e.g., OpenAI, Stability AI).

## Configuration options

- **Media Type:** Select the media type in which generated images will be stored.
- **Field settings:** Ensure the selected media type has a file/image field compatible with image uploads.

## Example use cases

- Generate hero images or thumbnails for articles automatically.
- Create dynamic product or service illustrations based on descriptions.
- Produce visual content for social media sharing or metadata.

## Notes

- Generated images are saved as files and attached to media entities.
- The field value stores references to these media items.
- The file name is `ai_generated.jpg` by default, but this can be customized if needed.
- Each image is saved with a name derived from the image's generation context.

---

*This documentation was AI-generated.*
