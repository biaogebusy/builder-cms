

## Image Classification

Image Classification calls are calls that can take an image and give weights of confidence back of how well this image fits a classification.

### Example normalized Image Classification call

The following is an example of sending the file underwear_ad.jpg into Huggingface with the Falconsai/nsfw_image_detection model and getting the classification back in the form of an array of ImageClassificationItem.

```php
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationInput;

$image_binary = file_get_contents('underwear_ad.jpg');
$normalized_file = new ImageFile($image_binary, 'image/jpeg', 'underwear_ad.jpg');
$input = new ImageClassificationInput($normalized_file);
/** @var array $classification_items */
$classification_items =  \Drupal::service('ai.provider')->createInstance('huggingface')->imageClassification($input, 'Falconsai/nsfw_image_detection', ['my-custom-call'])->getNormalized();

foreach ($classification_items as $item) {
  // If it's slightly not safe for work, we exit here.
  if ($item->getLabel() == 'nsfw' && $item->getConfidenceScore() > 0.7) {
    throw new \Exception('Our site does not tolerate images that might not be safe for work');
  }
}
```

### Image Classification Interfaces & Models

The following files define the methods available when doing an Image Classification call as well as the input and output.

* [ImageClassificationInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/ImageClassification/ImageClassificationInterface.php?ref_type=heads)
* [ImageClassificationInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/ImageClassification/ImageClassificationInput.php?ref_type=heads)
* [ImageClassificationOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/ImageClassification/ImageClassificationOutput.php?ref_type=heads)

### Image Classification Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Image Classification Generation Explorer` under `/admin/config/ai/explorers/ai-image-classification` to test out different calls and see the code that you need for it.
