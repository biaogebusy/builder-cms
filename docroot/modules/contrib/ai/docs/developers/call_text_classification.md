


## Text Classification

Text Classification calls are calls that can take text and give weights of confidence back of how well this text fits a classification. Famous providers are Hugging Face models like `nlptown/bert-base-multilingual-uncased-sentiment` for sentiment analysis or `protectai/deberta-v3-base-prompt-injection` for prompt injection detection.

### Example normalized Text Classification call

The following is an example of sending the text "This product is absolutely wonderful!" into Hugging Face using the nlptown/bert-base-multilingual-uncased-sentiment model and getting the classification back in form of an array of TextClassificationItem.

```php
use Drupal\ai\OperationType\TextClassification\TextClassificationInput;

$input = new TextClassificationInput('This product is absolutely wonderful!');
/** @var array $classification_items */
$classification_items = \Drupal::service('ai.provider')->createInstance('huggingface')->textClassification($input, 'nlptown/bert-base-multilingual-uncased-sentiment', ['my-custom-call'])->getNormalized();

foreach ($classification_items as $item) {
  // If the text is classified as very negative with high confidence, flag it.
  if ($item->getLabel() == '1 star' && $item->getConfidenceScore() > 0.7) {
    throw new \Exception('This text has been classified as very negative.');
  }
}
```

### Text Classification Interfaces & Models

The following files defines the methods available when doing a text classification call as well as the input and output.

* [TextClassificationInterface.php](https://git.drupalcode.org/project/ai/-/blob/HEAD/src/OperationType/TextClassification/TextClassificationInterface.php?ref_type=heads)
* [TextClassificationInput.php](https://git.drupalcode.org/project/ai/-/blob/HEAD/src/OperationType/TextClassification/TextClassificationInput.php?ref_type=heads)
* [TextClassificationOutput.php](https://git.drupalcode.org/project/ai/-/blob/HEAD/src/OperationType/TextClassification/TextClassificationOutput.php?ref_type=heads)

### Text Classification Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Text Classification Explorer` under `/admin/config/ai/explorers/ai-text-classification` to test out different calls and see the code that you need for it.
