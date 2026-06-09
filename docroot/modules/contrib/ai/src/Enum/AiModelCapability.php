<?php

declare(strict_types=1);

namespace Drupal\ai\Enum;

/**
 * Enum of AI provider model capabilities, which aren't shared across.
 */
enum AiModelCapability: string {
  // Chat capabilities.
  case ChatWithImageVision = 'chat_with_image_vision';
  case ChatWithAudio = 'chat_with_audio';
  case ChatWithVideo = 'chat_with_video';
  case ChatWithPdf = 'chat_with_pdf';
  case ChatSystemRole = 'chat_system_role';
  case ChatJsonOutput = 'chat_json_output';
  case ChatStructuredResponse = 'chat_structured_response';
  case ChatTools = 'chat_tools';
  case ChatCombinedToolsAndStructuredResponse = 'chat_combined_tools_and_structured_response';

  // Image to Image capabilities.
  case ImageToImageUpscale = 'image_to_image_upscale';
  case ImageToImageOutpaint = 'image_to_image_outpaint';
  case ImageToImageInpaint = 'image_to_image_inpaint';
  case ImageToImageErase = 'image_to_image_erase';
  case ImageToImageSearchReplace = 'image_to_image_search_replace';
  case ImageToImageSearchRecolor = 'image_to_image_search_recolor';
  case ImageToImageRemoveBackground = 'image_to_image_remove_background';
  case ImageToImageSketch = 'image_to_image_sketch';
  case ImageToImageStyleGuide = 'image_to_image_style_guide';
  case ImageToImageStyleTransfer = 'image_to_image_style_transfer';

  /**
   * Get a base operation type for the capability.
   *
   * @return string
   *   The operation type.
   */
  public function getBaseOperationType(): string {
    return match ($this) {
      self::ChatStructuredResponse,
      self::ChatTools,
      self::ChatCombinedToolsAndStructuredResponse,
      self::ChatWithImageVision,
      self::ChatWithAudio,
      self::ChatWithVideo,
      self::ChatWithPdf,
      self::ChatSystemRole,
      self::ChatJsonOutput => 'chat',
      self::ImageToImageUpscale,
      self::ImageToImageOutpaint,
      self::ImageToImageInpaint,
      self::ImageToImageErase,
      self::ImageToImageSearchReplace,
      self::ImageToImageSearchRecolor,
      self::ImageToImageRemoveBackground,
      self::ImageToImageSketch,
      self::ImageToImageStyleGuide,
      self::ImageToImageStyleTransfer => 'image_to_image',
    };
  }

  /**
   * Get a title for the capability.
   *
   * @return string
   *   The title.
   */
  public function getTitle(): string {
    return match ($this) {
      self::ChatWithImageVision => 'Chat with Image Vision',
      self::ChatWithAudio => 'Chat with Audio',
      self::ChatWithVideo => 'Chat with Video',
      self::ChatWithPdf => 'Chat with PDF',
      self::ChatSystemRole => 'Chat System Role',
      self::ChatJsonOutput => 'Chat JSON Output',
      self::ChatStructuredResponse => 'Chat Structured Response',
      self::ChatTools => 'Chat with Tools/Function Calling',
      self::ChatCombinedToolsAndStructuredResponse => 'Chat with Tools and Structured Response',
      self::ImageToImageUpscale => 'Image to Image Upscale',
      self::ImageToImageOutpaint => 'Image to Image Outpaint',
      self::ImageToImageInpaint => 'Image to Image Inpaint',
      self::ImageToImageErase => 'Image to Image Erase',
      self::ImageToImageSearchReplace => 'Image to Image Search Replace',
      self::ImageToImageSearchRecolor => 'Image to Image Search Recolor',
      self::ImageToImageRemoveBackground => 'Image to Image Remove Background',
      self::ImageToImageSketch => 'Image to Image Sketch',
      self::ImageToImageStyleGuide => 'Image to Image Style Guide',
      self::ImageToImageStyleTransfer => 'Image to Image Style Transfer',
    };
  }

  /**
   * Get a description for the capability.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string {
    return match ($this) {
      self::ChatWithImageVision => 'Is set if the chat model can include images in the chat input.',
      self::ChatWithAudio => 'Is set if the chat model can include audio in the chat input.',
      self::ChatWithVideo => 'Is set if the chat model can include video in the chat input.',
      self::ChatWithPdf => 'Is set if the chat model can include PDF documents in the chat input.',
      self::ChatSystemRole => 'Is set if the chat model can include a system role.',
      self::ChatJsonOutput => 'Is set if the chat model can do flawless complex JSON output.',
      self::ChatStructuredResponse => 'Is set if the chat model can do structured responses.',
      self::ChatTools => 'Is set if the chat model can use tools or function calling.',
      self::ChatCombinedToolsAndStructuredResponse => 'Is set if the chat model can use tools and do structured responses in the same request.',
      self::ImageToImageUpscale => 'Is set if the image model can do upscaling.',
      self::ImageToImageOutpaint => 'Is set if the image model can do outpainting.',
      self::ImageToImageInpaint => 'Is set if the image model can do inpainting.',
      self::ImageToImageErase => 'Is set if the image model can do erasing.',
      self::ImageToImageSearchReplace => 'Is set if the image model can do search and replace.',
      self::ImageToImageSearchRecolor => 'Is set if the image model can do search and recolor.',
      self::ImageToImageRemoveBackground => 'Is set if the image model can do background removal.',
      self::ImageToImageSketch => 'Is set if the image model can do sketch-based editing.',
      self::ImageToImageStyleGuide => 'Is set if the image model can apply a style guide.',
      self::ImageToImageStyleTransfer => 'Is set if the image model can do style transfer.',
    };
  }

}
