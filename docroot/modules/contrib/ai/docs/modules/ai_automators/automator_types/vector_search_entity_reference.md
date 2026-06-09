# Vector Search: Entity Reference

## Field it applies to

- **Field type:** `entity_reference`
- **Potential target:** Any entity type

## File location

[ai_automators/src/Plugin/AiAutomatorType/VectorSearchEntityReference.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/VectorSearchEntityReference.php?ref_type=heads)

## Description

The **Vector Search: Entity Reference** rule automatically links to the most relevant entity based on vector search results.
It uses AI embeddings to match content semantically against a vector search index and sets the entity reference field accordingly.
This is ideal for dynamically associating related content or resources without manual selection.

## Requirements

- **AI Search** module must be enabled.
- At least one enabled **Search API** index configured with vector database backend.
- A configured **embeddings engine** for generating vectors.
- Appropriate user permissions to administer Search API.

## Form fields required

- **Vector Search Index**
  Type: `select`
  Description: Select the vector search index to use.

- **Maximum Results**
  Type: `number`
  Description: The maximum number of similar items to return.

- **Offset**
  Type: `number`
  Description: The number of items to skip before returning results.

- **Minimum Score**
  Type: `number`
  Description: The minimum score threshold for returned matches.

- **Distinct Entities**
  Type: `checkbox`
  Description: Whether to return only distinct entities.

## Example use cases

1. Automatically reference related articles for a news post.
2. Link to similar products on an e-commerce site.
3. Associate a support ticket with related knowledge base articles.
4. Reference related courses for an educational resource.
5. Link event content types to similar past events.
6. Tag a blog post with the most relevant existing topic.
7. Reference a research paper that most closely matches the current content.
8. Automatically link a new FAQ entry to existing related FAQs.

---

*This documentation was AI-generated.*
