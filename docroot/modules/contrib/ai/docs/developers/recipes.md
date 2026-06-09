The AI module has a lot of frameworks that do not actually do anything by themselves, but need to be set up to work correctly. The AI Automators, AI Search, Field Widgets and AI Agents are all useless without actual setups.

Since AI can solve very specific business cases, it's very hard to ship the AI module one-size-fits-all solutions and that's where recipes come in.

However to be able to utilize recipes correctly we have added some config actions that you can use to setup recipes.

### verifySetupAi
In many cases a recipe doesn't really care if it's Gemini or OpenAI behind the scenes, it might just care about what the capabilities of the model that is setup are.

Or we might have 20 recipes that set up one AI button or AI Agent each and we do not want all of those to be inheriting from an AI Provider setup recipe, when the site might already have an AI provider setup.

This action helps with that - it's not actually an action though, it's a workaround that checks if all the dependencies on the system are met, and if not it will throw an exception so the recipe reverts any changes.

There are 4 things you can verify (and you can mix)

#### provider_is_setup
This takes an array of the providers you want to check for and if they are actually setup and working (API key set, host set etc.).

Example where I want to verify if OpenAI exists and is setup:
```
config:
  actions:
    ai.settings:
      verifySetupAi:
        provider_is_setup:
          - openai
```

#### operation_type_has_provider
This takes an array of the possible operation types and checks if there exists one provider at least that handles this.

Example where I want to verify if a chat and an image generation model exists:
```
config:
  actions:
    ai.settings:
      verifySetupAi:
        operation_type_has_provider:
          - chat
          - text_to_image
```

#### operation_type_has_default_model
This takes an array of the possible operation types and checks if a default model exists for this.

Example where I want to verify if a chat default model exists:
```
config:
  actions:
    ai.settings:
      verifySetupAi:
        operation_type_has_default_model:
          - chat
```

#### vdb_provider_is_setup
This does the same as the provider, but checks if a vdb provider is setup.

Example where I want to check if Milvus is setup:
```
config:
  actions:
    ai.settings:
      verifySetupAi:
        vdb_provider_is_setup:
          - milvus
```

### setupAiProvider
This makes it possible to setup an AI Provider, but also automatically fill out the key value for it. This will work for any simple provider that is setup using a key.

You can have this running and provide an empty value and it will not fail, it will simply not setup the provider. This is good if you are asking for multiple providers and the user only wants to setup one of them.

In this example we want to setup the OpenAI Provider by asking the end user for the API Key.

If you want to setup a provider where the key is already setup, you can add the value `no_key_needed: true` to the setupAiProvider config and it will run the setup regardless of if a key is provided or not.

```
input:
  openai_api_key:
    data_type: string
    description: The OpenAI API key, if you want to use OpenAI.
    prompt:
      method: ask
      arguments:
        question: 'If you want to use OpenAI, enter your OpenAI API key.'
config:
  ai_provider_openai.settings:
    setupAiProvider:
      key_value: ${openai_api_key}
      key_name: openai_api_key
      key_label: 'OpenAI API Key'
      env_var: openai_api_key
      provider: openai
```

### setupVdbServerWithDefaults
This makes it possible to setup a vdb server, but use the default embeddings engine for it. You just provide the configuration as it should be, but make sure to leave all the embeddings data out.

Example of settings up the Amazee.io vector db.
```
config:
  actions:
    search_api.server.recipe_server:
      setupVdbServerWithDefaults:
        langcode: en
        status: true
        dependencies:
          module:
            - ai_search
        id: recipe_server
        name: 'Recipe Server'
        description: 'This is the server for RAG.'
        backend: search_api_ai_search
        backend_config:
          chat_model: amazeeio__chat
          database: amazeeio_vector_db
          database_settings:
            database_name: db
            collection: recipe_server
            metric: cosine_similarity
          embedding_strategy: contextual_chunks
          embedding_strategy_configuration:
            chunk_size: '2048'
            chunk_min_overlap: '100'
            contextual_content_max_percentage: '30'
          embedding_strategy_details: ''
```

### setupVdbIndex
This is just a helper config action to setup the index after the server. Just give the whole config.
