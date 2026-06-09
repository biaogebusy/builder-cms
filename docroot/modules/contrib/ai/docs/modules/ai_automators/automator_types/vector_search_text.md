# Vector Search: Text

## Field it applies to

- **Field type:** `string_long`
- **Potential target:** Any long string field on content entities

## File location

[ai_automators/src/Plugin/AiAutomatorType/VectorSearchText.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/VectorSearchText.php?ref_type=heads)

## Description

The **Vector Search: Text** rule enables automatic extraction of text for a `string_long` field using vector search technology.
It queries a vector database based on embeddings generated from your input and retrieves the most relevant text field(s) from the configured search index.
This rule is useful for referencing or reusing content based on semantic similarity.

## Requirements

- A working **Search API** index with a supported vector database backend.
- Embeddings engine configured for generating vector input.
- Vector database provider available and accessible.

## Form fields required

- **Search Index**
  A select list to choose which search index to query.

- **Output Field**
  Type: `select`
  Description: The field from the search index whose value will be used as the output.
  Default (if not saved): `None`

- **Permissions Warning**
  A warning indicating that any user with access to view this field will see the data, regardless of content permissions or index permissions.

## Example use cases

1. Retrieve and insert a similar FAQ entry’s text into a help content type.
2. Populate product descriptions by matching to similar products in the search index.
3. Reuse parts of policy documents that closely match the current content.
4. Auto-fill summaries for blog posts based on existing similar posts.
5. Pull related knowledge base content into support ticket responses.
6. Generate bios by matching to similar profiles in a directory.
7. Insert excerpts from research papers that align with the current topic.
8. Auto-generate introductory text from related event listings.

---

*This documentation was AI-generated.*
