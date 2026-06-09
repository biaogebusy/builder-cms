

## Object Detection

Object Detection calls are calls that can take an image and identify objects within it, returning their labels, confidence scores, and bounding box coordinates.

### Example normalized Object Detection call

The following is an example of sending the file street_photo.jpg into Huggingface with the facebook/detr-resnet-50 model and getting the detected objects back in the form of an array of ObjectDetectionItem.

```php
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ObjectDetection\ObjectDetectionInput;

$image_binary = file_get_contents('street_photo.jpg');
$normalized_file = new ImageFile($image_binary, 'image/jpeg', 'street_photo.jpg');
$input = new ObjectDetectionInput($normalized_file);
/** @var array $detection_items */
$detection_items =  \Drupal::service('ai.provider')->createInstance('huggingface')->objectDetection($input, 'facebook/detr-resnet-50', ['my-custom-call'])->getNormalized();

foreach ($detection_items as $item) {
  // Output each detected object with its bounding box.
  print 'Found ' . $item->getLabel() . ' with confidence ' . $item->getConfidenceScore() . "\n";
  print 'Bounding box: ' . json_encode($item->getBoundingBox()) . "\n";
}
```

### Object Detection Interfaces & Models

The following files define the methods available when doing an Object Detection call as well as the input and output.

* [ObjectDetectionInterface.php](https://git.drupalcode.org/project/ai/-/blob/HEAD/src/OperationType/ObjectDetection/ObjectDetectionInterface.php)
* [ObjectDetectionInput.php](https://git.drupalcode.org/project/ai/-/blob/HEAD/src/OperationType/ObjectDetection/ObjectDetectionInput.php)
* [ObjectDetectionOutput.php](https://git.drupalcode.org/project/ai/-/blob/HEAD/src/OperationType/ObjectDetection/ObjectDetectionOutput.php)

### Object Detection Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Object Detection Explorer` under `/admin/config/ai/explorers/object_detection_generator` to test out different calls and see the code that you need for it.
