

## Text-To-Image

Text-To-Image calls are calls that can generate images from a textual prompt. Famous providers are Dall-E or Stable Diffusion.

### Example normalized Text-To-Image call

The following is an example of sending the prompt "A cow" into OpenAI using the dall-e-3 model. The output is an array of ImageFile.

```php
use Drupal\ai\OperationType\TextToImage\TextToImageInput;

$input = new TextToImageInput('a cow');
/** @var \Drupal\ai\OperationType\TextToImage\TextToImageOutput $return_images */
$return_images =  \Drupal::service('ai.provider')->createInstance('openai')->textToImage($input, 'dall-e-3', ['my-custom-call']);
/** @var \Drupal\ai\OperationType\GenericType\ImageFile */
$image = $return_images[0];
// You can do many cool things now.

// Examples Possibility #1 - get binary from the image.
$binaries = $image->getAsBinary();
// Examples Possibility #2 - get as base 64 encoded string from the image.
$base64 = $image->getAsBase64EncodedString();
// Examples Possibility #3 - get as generated media from the image.
$media = $image->getAsMediaEntity("image", "", "image.png");
// Examples Possibility #4 - get as image file entity from the first image.
$file = $image->getAsFileEntity("public://", "image.png");
```

### Text-To-Image Interfaces & Models

The following files define the methods available when doing a text-to-image call as well as the input and output.

* [TextToImageInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TextToImage/TextToImageInterface.php?ref_type=heads)
* [TextToImageInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TextToImage/TextToImageInput.php?ref_type=heads)
* [TextToImageOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TextToImage/TextToImageOutput.php?ref_type=heads)

### Text-To-Image Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Text-To-Image Generation Explorer` under `/admin/config/ai/explorers/ai-image-generation` to test out different calls and see the code that you need for it.
