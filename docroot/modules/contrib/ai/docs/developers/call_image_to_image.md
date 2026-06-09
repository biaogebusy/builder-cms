

## Image To Image

Image To Image calls are calls that can take an image and possibly other input and generate a transformed image.

### Example normalized Image Classification call

The following is an example of sending the file small_image.jpg into DreamStudio with the upscale model and getting an upscaled version of the image back.

```php
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInput;

$image_binary = file_get_contents('small_image.jpg');
$normalized_file = new ImageFile($image_binary, 'image/jpeg', 'small_image.jpg');
$input = new ImageToImageInput($normalized_file);
/** @var array $images */
$images =  \Drupal::service('ai.provider')->createInstance('dreamstudio')->imageClassification($input, 'upscale', ['my-custom-call'])->getNormalized();

foreach ($classification_items as $item) {
  file_put_content('large_image.jpg', $image->getBinary());
}
```

### Image To Image Interfaces & Models

The following files define the methods available when doing an Image To Image call as well as the input and output.

* [ImageToImageInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/src/OperationType/ImageToImage/ImageToImageInterface.php?ref_type=heads)
* [ImageToImageInput.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/src/OperationType/ImageToImage/ImageToImageInput.php?ref_type=heads)
* [ImageToImageOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/src/OperationType/ImageToImage/ImageToImageOutput.php?ref_type=heads)

### Image Classification Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Image To Image Generation Explorer` under `/admin/config/ai/explorers/image_to_image_generator` to test out different calls and see the code that you need for it.
