# Text-to-Image (Media) Automator

The Text-to-Image Media Automator allows you to automatically generate images from text content and save them as Media entities. This is useful for automatically creating teaser or hero images for articles.

## Overview

### How It Works

1.  When you **Create** an entity, the Automator checks if the media field is empty.
2.  If empty, it sends the text (context) to the AI provider.
3.  The AI generates an image file.
4.  The Automator creates a new Media entity, uploads the image file to it, and attaches this Media entity to your content.

### "Edit when changed"

If you check **Edit when changed**, the Automator will re-generate the image if the content of the *Base Field* changes. **Warning**: This will create new Media entities and overwrite the reference.

## Prerequisites

1.  **Media Reference Field**: You must have a Media Entity Reference field on your Content Type (e.g., `field_image`).
2.  **Image Media Type**: You must have a Media Type configured for Image (e.g., standard `Image` media type in Drupal).
3.  **AI Provider**: You must have an AI Provider with **Text To Image** capability (e.g., OpenAI with gpt-image-1).

## Configuration

To configure the Text-to-Image Media Automator:

1.  Navigate to the **Manage Fields** tab of your entity type (e.g., `Structure > Content types > Article > Manage fields`).
2.  Click **Edit** on the Media field you want to automate.
3.  Scroll down to the **AI Automator Settings** section.
4.  Check **Enable AI Automator**.
5.  In the **Choose AI Automator Type** dropdown, select **LLM: Media Image Generation**.

### Media Configuration

*   **Media Type**: **Crucial Step**. You must select the specific Media Type you want to create (e.g., "Image").
The automator will create a new Media entity of this type and attach the generated image file to it.

### Base Mode Configuration

**Base Mode** uses one existing text field as the input for image generation.

1.  **Automator Input Mode**: Select **Base Mode**.
2.  **Automator Base Field**: Choose the text field that provides context for image generation (e.g., "Body" or "Title").
3.  **Automator Prompt**: Enter instructions for the AI on how to generate the image.
    *   **Standard Usage**: Use `{{ context }}` to pass the text from the base field.

**Example Prompt:**
```text
Create a good teaser image, 500x500px, square. {{ context }}
```

### Advanced Mode (Token) Configuration

**Advanced Mode** allows you to construct the image generation prompt using any Drupal tokens.

1.  **Automator Input Mode**: Select **Advanced Mode (Token)**.
2.  **Automator Prompt (Token)**: Enter your prompt using tokens.

**Example Prompt:**
```text
Title: [node:title]. Summary: [node:summary].
```

### Test the functionality

1.  Go to **Content** > **Add content** > **Article** (or the entity type you configured).
2.  In the **Body** field (or your chosen *Base Field*), paste text that describes the desired image. Use the example below:
    > A calm morning by a lake with mountains in the background, realistic style.
3.  Scroll to the **Image Media** field.
4.  *Option A (Manual Generation):* If you see the **AI Automator** button (magic wand/robot icon), click it.
    *   Wait for the Ajax loader to finish.
    *   Check the widget. A new Image media item should be selected/attached.
5.  *Option B (Automatic Generation):* If no button is present, simply click **Save**.
    *   The page will reload (or redirect).
    *   Check the saved content. The generated image should be visible.

## Field Widget Action support

In addition to the automator (which runs on entity save), you can add a **Text to Image Media Library** action button directly on the content edit form. This allows editors to generate images on demand while editing content.

**Prerequisite**: You must first configure the automator on the field as described above. The Field Widget Action references the configured automator.

To set this up:

1.  Configure the automator on the media field as described in the [Configuration](#configuration) section above.
2.  If you want the automator to **only** run when the user clicks the action button (and not automatically on entity save), set the **Automator Worker** to **Field Widget** in the advanced settings.
3.  Navigate to the **Manage Form Display** tab of your entity type.
4.  Click the settings gear icon on the Media Library widget for your image field.
5.  Under **Field Widget Actions**, add the **Text to Image Media Library** action.
6.  Select the automator to use for generation, enable it, and save.

When editing content, the action button will appear next to the media field. Clicking it generates an image using the configured automator and automatically selects the resulting media entity in the widget.
