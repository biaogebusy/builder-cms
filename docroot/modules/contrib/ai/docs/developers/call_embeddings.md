

## Embeddings

Embeddings calls are calls that can generate vector outputs of weight for a text. While this might sound boring, it's an important part of RAG/vector search.

### Example normalized Embeddings call

The following is an example of sending the text "What type of comedy is Steve Martin famous for?" into OpenAI using the text-embedding-3-small model. The output is an array of floats.

```php
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;

$input = new EmbeddingsInput('What type of comedy is Steve Martin famous for');
/** @var \Drupal\ai\OperationType\Embeddings\EmbeddingsOutput $vector_object */
$vector_object =  \Drupal::service('ai.provider')->createInstance('openai')->Embeddings($input, 'text-embedding-3-small', ['my-custom-call']);

print_r($vector_object->getNormalized());
// This will output an array of floats.
```

### Embeddings Interfaces & Models

The following files define the methods available when doing an embeddings call as well as the input and output.

* [EmbeddingsInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Embeddings/EmbeddingsInterface.php?ref_type=heads)
* [EmbeddingsInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Embeddings/EmbeddingsInput.php?ref_type=heads)
* [EmbeddingsOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Embeddings/EmbeddingsOutput.php?ref_type=heads)

### Embeddings Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Embeddings Generation Explorer` under `/admin/config/ai/explorers/ai-embeddings` to test out different calls and see the code that you need for it.
