# Full patch test 1.3.x

This is a full patch test for the 1.3.x branch. It includes all changes made since the last full patch test. Note that this is very shallow testing and does not cover all edge cases. Please go through all the issues being added in this patch and test the relevant functionality as well.

## Setup a new environment
1. Create a new folder called `drupal-11-test` with latest version of Drupal 11.
2. `cd drupal-11-test`
3. `ddev config --project-type=drupal11 --docroot=web`
4. `ddev start`
5. `ddev composer create-project "drupal/recommended-project:^11"`
6. `ddev composer require drush/drush`
7. `ddev drush site:install --account-name=admin --account-pass=admin -y`
8. `ddev composer require 'drupal/ai:^1.3' 'drupal/ai_agents:^1.2' 'drupal/ai_provider_openai:^1.2' 'drupal/ai_vdb_provider_milvus:^1.1@beta'`
9. `ddev composer require 'drupal/field_validation:^3.0@beta' 'drupal/search_api:^1.40' 'drupal/token:^1.17' 'drupal/admin_toolbar:^3.6'`
10. Copy the file [docker-compose.milvus.yaml](./resources/docker-compose.milvus.yaml) to the `.ddev` folder.
11. `ddev restart`

## **Important**: If you are testing against a dev release
If you are testing against a dev release, the JS components are not built and you will have to do this yourself. You need nodejs 20+ to build the JS components.

1. `cd web/modules/contrib/ai/ui/json-schema-editor`
2. `npm install`
3. `npm run build`
4. `cd ../mdxeditor`
5. `npm install`
6. `npm run build`
7. `cd ../../modules/ai_ckeditor/`
8. `npm install`
9. `npm run build`

## Install the current versions of everything
1. `ddev drush pm:en ai ai_agents ai_provider_openai ai_logging ai_automators field_widget_actions ai_api_explorer ai_assistant_api ai_chatbot ai_ckeditor ai_content_suggestions ai_search ai_observability ai_translate ai_validations ai_vdb_provider_milvus admin_toolbar admin_toolbar_tools ai_agents_explorer -y`

## Login to the website
1. Open browser to `http://drupal-11-test.ddev.site/user/login`
2. Login with username `admin` and password `admin`

## Setup the VDB Provider:
1. In the menu go `Configuration` -> `AI` -> `Vector DB Settings` -> `Milvus Provider`
2. In host enter `http://milvus` and in port enter `19530`, no key needed.
3. Click `Save configuration`

## Setup OpenAI Provider:
1. In the menu go to `Configuration` -> `AI` -> `Provider Settings` -> `OpenAI Authentication`
2. Click "Create a new key".
3. Name key name `OpenAI Key` and for the key value use your OpenAI API key.
4. Click `Save configuration`
5. Go back twice
6. Reload and select the key `OpenAI Key` from the dropdown for the OpenAI Provider.
7. Click `Save configuration`

## Test the base functionality
1. You can see this under [Testing an AI Provider](../../developers/testing_an_ai_provider.md). Just test the OpenAI provider as normal.

## Setup AI Search
1. Visit https://drupal11-test.ddev.site/admin/config/search/search-api
2. Click `Add server`
3. For `Server name` enter `Milvus Server`
4. For `Embeddings Engine` select `OpenAI | text-embedding-3-small`
5. For `Vector Database` select `Milvus DB`
6. For `Database Name` write `default`
7. For `Collection` write `articles`
8. Click `Save`
9. Visit https://drupal11-test.ddev.site/admin/config/search/search-api again
10. Click `Add index`
11. For `Index name` enter `Articles Index`
12. For `Datasources` select `Content`
13. For `Bundles` choose `Only those selected` and select `Article`
14. For `Server` select `Milvus Server`
15. Click `Save and add fields`
16. Add the fields `Title` and `Body` and click `Done`
17. For `Title` select `Contextual content` as `Indexing options`
18. For `Body` select `Main content` as `Indexing options`
19. Click `Save changes`

### Test AI Search
1. Create a new article with title "Living Standards" and body "Living standards refer to the level of wealth, comfort, material goods, and necessities available to a certain socioeconomic class or geographic area."
2. Click `Save`
3. Visit https://drupal11-test.ddev.site/admin/config/ai/explorers/vector_db_generator
4. Enter the prompt `Affluance Level` and select the `Articles Index` as the index.
5. Click `Run DB Query`
6. The article should show up with over 0.3 in score.
7. Change the prompt to `Cars and Motorbikes` and click `Run DB Query` again.
8. The article should show up with under 0.3 in score.

## Setup AI Automators
1. Visit https://drupal11-test.ddev.site/admin/structure/types/manage/article/fields
2. Click `Edit` under `Tags`
3. Check `Enable AI Automators`
4. Choose `LLM: Taxonomy` as the Automator Type
5. Select `Body` as the `Automator Base Field`
6. In Automator Prompt write `Based on the context text create up to 2 tags that fits the context. Context: {{ context }}`
7. Click `Save settings`

### Test AI Automators
1. Visit the article you created earlier.
2. Click `Edit`
3. Click `Save` without changing anything.
4. After the page reloads, the Tags field should be populated with 2 tags that fit the article.

## Field Widget Actions

### Field Widget Action Text-To-Image
1. Make sure that AI Automators and Field Widget Action modules are enabled.
2. Create a content type under https://drupal11-test.ddev.site/admin/structure/types/add
3. In label set "Image Creation"
4. Click "Save and manage fields"
5. Click "Create a new field"
6. Pick "File upload"
7. Set the label to "Created Image"
8. Pick "Image" from the checkbox and click "Continue"
9. Scroll down and check "Enable AI Automator"
10. In the "Choose AI Automator Type" the "Image Generation"
11. In "Automator Base Field" pick "Title"
12. In Automator Prompt write "Create an image about {{ context }}"
13. Open "Advanced Settings"
14. Pick "Field Widget" under "Automator Worker"
15. Click Save.
16. Goto form display for the content type: https://drupal11-test.ddev.site/admin/structure/types/manage/image_creation/form-display
17. On Created Image, click the cogwheel to edit the field settings.
18. Open "Field Widget Actions"
19. Under "Add new action" choose Text to Image and click "Add action"
20. Check "Enable Automators"
21. Open "Enable an Automator" and click "Created Image Default"
22. Click "Update"
23. Click "Save"
24. Go to Create Image Creation under https://drupal11-test.ddev.site/node/add/image_creation
25. In the title write "A cat playing chess"
26. Click "Text to Image" button - this might take a while.
27. You should see that an image is generated and attached to the Created Image field.
28. Make sure to delete the entity if you created it.
29. Delete the content type: https://drupal11-test.ddev.site/admin/structure/types/manage/image_creation/delete

## Test Agents
1. Visit https://drupal11-test.ddev.site/admin/config/ai/agents
2. On the Content Type Agent, click the dropdown and select `Explore`
3. In the prompt enter `What article types are available on this website?`
4. Click `Run Agent`
5. The explorer should run once and tell you that Article and Basic page are available content types.

## Setup AI Logging
1. Visit https://drupal11-test.ddev.site/admin/config/ai/logging/settings
2. Check `Automatically log requests`
3. Check `Automatically log responses`
4. Click `Save configuration`

### Test AI Logging
1. Visit https://drupal11-test.ddev.site/admin/config/ai/explorers/chat_generator
2. Enter the prompt `What is Drupal?`
3. Click `Ask The AI`
4. Visit https://drupal11-test.ddev.site/admin/config/ai/logging/collection
5. You should see the request logged with the prompt and response.

## Test AI Observability
1. Visit https://drupal11-test.ddev.site/admin/reports/dblog
2. You should see log entries for AI requests and responses with details about the provider used, tokens consumed, and time taken.

## Setup AI Translate
1. Visit https://drupal11-test.ddev.site/admin/structure/types/manage/article
2. Click the tab `Language settings`
3. Check `Enable translation`
4. Visit https://drupal11-test.ddev.site/admin/config/regional/language/add
5. Add `German` as a new language.
6. Visit https://drupal11-test.ddev.site/admin/config/ai/settings
7. Under `Translate Text` choose `Chat proxy to LLM` and provider and `gpt-4.1` as the model.
8. Click `Save configuration`

### Test AI Translate
1. Go to the article you created earlier.
2. Click `Edit`
3. Click the `Translate` tab
4. On the German row, click `Translate using gpt-4.1`
5. You should now see the translated title being `Lebensstandard`.

## Setup AI Content Suggestions
1. Visit https://drupal11-test.ddev.site/admin/config/ai/suggestions
2. Click `Enable Evaluate Readability`
3. Click `Save configuration`

### Test AI Content Suggestions
1. Go to the article you created earlier.
2. Click `Edit`
3. Open the tab called `Evaluate Readability`
4. Click `Score readability`
5. You should see a readability score and suggestions to improve the content.

## Setup AI CKEditor
1. Visit https://drupal11-test.ddev.site/admin/config/content/formats
2. Click `Configure` on the `Basic HTML` text format.
3. Drag the `AI CKEditor` button from `Available buttons` to `Active toolbar`
4. Open the `Generate with AI` section.
5. Check `Enabled`
6. Click `Save configuration`

### Test AI CKEditor
1. Go to the article you created earlier.
2. Click `Edit`
3. In the Body field, place the cursor at the end of the content.
4. Click the `AI Assistant` button in the toolbar and `Generate with AI`.
5. In the modal, enter the prompt `Add a conclusion about living standards.`
6. Click `Generate`
7. The AI should generate a conclusion. Click `Save changes to editor`
8. The conclusion should now be added to the article body.

## Setup AI Validations
1. Visit https://drupal11-test.ddev.site/admin/structure/field_validation
2. Click `Add field validation rule set`
3. Pick `Content` as `Entity Type` and `Article` as `Bundle`
4. Click `Create new field validation rule set`
5. Select `Image` as the field and `AI Image Constraint` as the constraint.
6. Click `Add`
7. In the `Field Validation Rule Title` enter `Car Image Validation`
8. In the `Column of field` select `target_id`
9. Change the prompt to `Check if the image is of a car. Respond with XTRUE if it is a car and XFALSE if it is not.`
10. In `Message` add `The image must be of a car.`
11. Click `Add Rule`

### Test AI Validations
1. Go to the article you created earlier.
2. Click `Edit`
3. In the `Image` field, upload an image of a tree.
4. You should see the validation message `The image must be of a car.`
5. Replace the image with an image of a car.
6. You should be able to save the article without validation errors.

## Setup AI Chatbot and AI Assistants API
1. Visit https://drupal11-test.ddev.site/admin/config/ai/ai-assistant
2. Click `Add AI Assistant`
3. In `Label` enter `Content Type Assistant`
4. In `Instructions` enter `Just forward everything to the content type agent.`
5. Enable the `Content Type Agent` under `Agents Enabled`
6. Click `Save`
7. Visit https://drupal11-test.ddev.site/admin/structure/block
8. Under `Content Below` click `Place block`
9 . Click `Place block` next to `AI DeepChat Chatbot`

10. Under `AI Assistant` select `Content Type Assistant`
11. Under `Styling settings` select `Style` as `Bard`, `width` as `500px`, `height` as `600px` and `Placement` as `Bottom right `
12. Click `Save block`
13. Click the tab `Claro`
14. Under `Content` click `Place block`
15. Click `Place block` next to `AI DeepChat Chatbot`
16. Select the `Content Type Assistant` under `AI Assistant`
17. Under `Styling settings` select `Style` as `Toolbar`, `width` as `auto`, `height` as `100%` and `Placement` as `Toolbar`
18. Click `Save block`

### Test AI Chatbot and AI Assistants API
1. Visit https://drupal11-test.ddev.site/
2. In the bottom right, the chatbot should be visible. Click it open.
3. In the input, enter `What content types are available on this website?`
4. The chatbot should respond with `The available content types on this website are Article and Basic page.`
5. Visit https://drupal11-test.ddev.site/admin/config
6. In the Toolbar on the top click `Assistant`
7. An assistant should slide out from the left side.
8. In the input, enter `What content types are available on this website?`
9. The assistant should respond with `The available content types on this website are Article and Basic page.`

## Guardrails

### First Guardrail
1. Visit Guardrails page at: https://drupal11-test.ddev.site/admin/config/ai/guardrails
2. Click "Add guardrail"
3. Set Label to "Banana Guardrail"
4. Set Guardrail to "Restrict to Topic"
5. Set "Valid Response Message" to:
   ```
   bananas
   fruit
   ```
6. Set "Message to send if valid topics are present" to "You can only ask questions about bananas."
7. Click "Save"

### Second Guardrail
1. Visit Guardrails page at: https://drupal11-test.ddev.site/admin/config/ai/guardrails
2. Click "Add guardrail"
3. Set Label to "Orange Regex Guardrail"
4. Set Guardrail to "Regex Guardrail"
5. Set "Regexp Pattern" to "/orange/i"
6. Set "Violation Message" to "The request contains oranges - we do not like that."
7. Click "Save"

### Create Guardrail Set
1. Visit Guardrails page at: https://drupal11-test.ddev.site/admin/config/ai/guardrails
2. Click tab "AI Guardrail sets"
3. Click "Add guardrail set"
4. Set Label to "Fruit Guardrail Set"
5. In "Pre generated guardrails" select "Orange Regex Guardrail" and click "Add Guardrail"
6. In "Pre generated guardrails" select "Banana Guardrail" and click "Add Guardrail"
7. In "Pre generated guardrails" select "Orange Regex Guardrail" and click "Add Guardrail"
8. On the right side of Orange Regex Guardrail, click "Remove" to see that ajax removal works.
9. Click "Save"

### Test the Guardrail Set Regex Guardrail
1. Enable the AI API Explorer module if you haven't already.
2. Visit the Chat Explorer on: https://drupal11-test.ddev.site/admin/config/ai/explorers/chat_generator
3. In the "System Prompt" field enter "Always answer with an answer about Mercedes."
4. In the message field enter "Talk about oranges please!"
5. Open "Advanced" and then Guardrails and select "Fruit Guardrail Set"
6. Click "Ask the AI"
7. You should get back a response that says "The request contains oranges - we do not like that." if the guardrail is working correctly.

### Test the Guardrail Set Topic Guardrail.
1. Enable the AI API Explorer module if you haven't already.
2. Visit the Chat Explorer on: https://drupal11-test.ddev.site/admin/config/ai/explorers/chat_generator
3. In the "System Prompt" field enter "Always answer with an answer about Mercedes."
4. In the message field enter "What is Mercedes really about?"
5. Open "Advanced" and then Guardrails and select "Fruit Guardrail Set"
6. Click "Ask the AI"
7. You should get back a response that says "You can only ask questions about bananas." if the guardrail is working correctly.

### Test that it bypassed valid requests.
1. Enable the AI API Explorer module if you haven't already.
2. Visit the Chat Explorer on: https://drupal11-test.ddev.site/admin/config/ai/explorers/chat_generator
3. In the "System Prompt" field enter "Always answer with an answer about Mercedes."
4. In the message field enter "Bananas sure taste great!"
5. Open "Advanced" and then Guardrails and select "Fruit Guardrail Set"
6. Click "Ask the AI"
7. You should get back an AI generated response about Mercedes and fruit or something similar to it.

## Upgrade test (on first run only)
1. Now run `ddev composer require 'drupal/ai:1.3.x-dev@dev'` to get the latest changes in the 1.3.x branch.
2. Run database updates with `ddev drush updb -y`. Usually this is not needed for patch releases, but run it to be sure.
3. Clear caches with `ddev drush cr`

## Re-test the base functionality
1. Now rerun all test categories above to ensure everything still works after the upgrade.

## Re-test with the updated code on install
1. Run `ddev delete -O && ddev start` to delete the environment.
2. Rerun all test categories above to ensure everything works on a fresh install with the updated code.
3. The upgrade test is not needed here.

# When finished
1. Run `ddev delete -O` to delete the test environment.
2. Remove the `drupal-11-test` folder if you no longer need it.
