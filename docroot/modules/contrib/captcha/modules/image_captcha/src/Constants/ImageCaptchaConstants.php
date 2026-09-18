<?php

namespace Drupal\image_captcha\Constants;

/**
 * Constants for the image_captcha module.
 */
class ImageCaptchaConstants {

  const IMAGE_CAPTCHA_ALLOWED_CHARACTERS = 'aAbBCdEeFfGHhijKLMmNPQRrSTtWXYZ23456789';

  // Setup status flags:
  const IMAGE_CAPTCHA_ERROR_NO_GDLIB = 1;
  const IMAGE_CAPTCHA_ERROR_NO_TTF_SUPPORT = 2;
  const IMAGE_CAPTCHA_ERROR_TTF_FILE_READ_PROBLEM = 4;

  const IMAGE_CAPTCHA_FILE_FORMAT_JPG = 1;
  const IMAGE_CAPTCHA_FILE_FORMAT_PNG = 2;
  const IMAGE_CAPTCHA_FILE_FORMAT_TRANSPARENT_PNG = 3;

  /**
   * The image captcha captcha type.
   *
   * @var string
   */
  const IMAGE_CAPTCHA_CAPTCHA_TYPE = 'image_captcha/Image';

}
