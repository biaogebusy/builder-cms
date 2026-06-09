# AI Search
## What is the AI Search module?
This module leverages the popular Drupal Search API contrib module to create
and manage vector databases of your content for highly relevant and accurate retrieval
of content related to any given terms, phrases, or even other content as a
whole.

## What is Retrieval Augmented Generation (RAG)?
RAG is when content is retrieved (typically from a Vector Database) and then
passed to a Large Language Model (LLM) along with, for example, a question or
request from an end user to provide a much more accurate answer about specific
content (and often, but not necessarily, content that it has not been trained
on).

## How does it work?
The content is broken into chunks (if large) along with contextual information
like title and other things you configure so each chunk understands its wider
context. This is then converted into a vector representation of the content -
typically hundreds or thousands of dimensions. Think of the dimensions as
sophisticated tagging where the strength of the tag is also considered - for
example a single dimension might indicate that something might be related to
transportation weakly but another may indicate a much stronger relation to
education.

The query from the user or content being compared is similarly converted to
a vector representation of it and different mathematical functions are used
by the vector database to find the nearest matches (ie, most relevant). This
is far more accurate and sophisticated than keyword scoring that database or
SOLR Search API indexes would use.

## How can it be used?
### Views integration
Views with fulltext search can be used to query the vector database and find
relevant results.

### AI Assistant
The AI Assistants module can use the 'RAG Action' to pull up relevant content
from the Vector Database during a chat.

### Combined with database or SOLR search
The Boost Processors provided can augment a database search or SOLR search to
greatly improve the relevance of search results.

Optionally use the `hook_ai_search_boost_results_alter` to rerank results
before passing them to the database or SOLR search.

### Programmatically
Find relevant results and use them in your own manner, e.g. along with Chat
endpoints from various LLMs (AI Providers). Get a vector representation of your
content via an AI Provider that supports embeddings, then run a Vector Search
via a VDB Provider (Vector Database Provider).

## Installation and configuration
### General setup
1. Enable the AI Search module
2. Enable at least one Vector Database Provider (VDB Provider)
3. Create a Search API Server & Index with AI Search as the back-end (follow
   the instructions during the configuration process). It is recommended to
   index the URL of the content to give your LLM access to this information:
   otherwise it may make unexpected decisions about what the URL to display is
4. Index your content
5. Optionally set a minimum score threshold using the 'Score Threshold' Search
   API processor plugin.

### Use in Views
1. Create a View with your new Search API Index as the content source
2. Add a full text search field
3. Optionally expose the filter (or instead of exposing provide a value to
   search)

### Use as an AI Assistant
1. Follow the instructions in the AI Assistants documentation
2. Enable the RAG Action

### Use Programmatically
1. Load your LLM, e.g. into `$llm`.
2. Get the vectors of the content you want to search with `$vector_input = $llm->embeddings($search_words, $model_id)->getNormalized();`.
3. Load your VDB Provider, e.g. into `$vdb`.
4. Get relevant results `$results = $vdb->vectorSearch($collection_name, $vector_input, []);`.
5. Do something with it like `$answer = $llm->chat("Here is a question, answer it using this content: " . $content_from_results);`

## Automated tests

The automated tests use:

- test_ai_provider_mysql test module to generate embeddings as a Provider.
- test_ai_vdb_provider_mysql test module to store and search the vector database as a VDB Provider.

This requires FFI PHP extension to run. In DDEV this can be enabled as follows:
```bash
mkdir -p .ddev/php
touch .ddev/php/ai-search-test-mysql-php.ini
echo "ffi.enable=true" > .ddev/php/ai-search-test-mysql-php.ini
ddev restart
```

AI Search related tests can then be run as follows:
```bash
ddev ssh
phpunit -c core/phpunit.xml modules/contrib/ai/modules/ai_search/tests/
phpunit -c core/phpunit.xml modules/contrib/ai/tests/src/Kernel/Utility/TextChunkerTest.php
```
Which runs all tests in the AI Search submodule + the Text Chunker which is
heavily used by the submodule.

### Updated the FFI extension for AI Search

When the current major version of PHP run by Gitlab CI e.g. becomes 8.4 up
from 8.3, the .gitlab-ci.yml file in the AI module project root should have the
PHP source file changed from 8.3 to 8.4 in order for it to continue to
recompile correctly with the FFI extension.

Similarly, the skip test against PHP version set in
`modules/ai_search/tests/src/Functional/AiSearchSetupMySqlTest.php` should be
updated.
