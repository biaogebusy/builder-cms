# AI

AI (artificial intelligence) integration for Drupal - a unified framework for
integrating various AI models and services into your Drupal site.

## Dependencies

- Drupal ^10.3 || ^11
- [Key module](https://www.drupal.org/project/key)
- Requires at least one AI Provider to be installed and configured.

## Getting Started

1. Enable the AI module.
2. Choose and enable an AI Provider module:
   1. Choose the AI Provider for your chosen LLM/AI service from the list at [https://project.pages.drupalcode.org/ai/providers/matris/](providers/matris.md)
   2. Download the AI Provider and install and configure according to its
      instructions.
3. Once your AI Provider(s) is configured, the AI module will select appropriate
   defaults for the various [Operation Types](developers/base_calls.md#the-operation-types-and-how-to-use-them).
   You can make your own selections by visiting `/admin/config/ai/settings` and
   updating the settings manually.
4. Enable your desired submodules for specific functionality.

## Try Drupal AI

If you're looking to explore the capabilities of the Drupal AI module without the need to download or set up a full Drupal environment, several platforms offer convenient ways to try out AI solutions.

These platforms provide trial environments for Drupal AI with pre-configured provider integrations, allowing you to directly experience the module's features.

- **[amazee.ai](https://amazee.ai)** offers **open source private AI infrastructure** for Drupal, emphasizing compliance, privacy, and data sovereignty for enterprises. It provides managed AI solutions to enhance content and security.

- **[Drupal Forge](https://drupalforge.org)** provides **instant pre-configured templates for launching** new Drupal trial sites. Quickly spin up temporary sites for testing or development, with an option for a cloud development environment.

## Develop for it

Check the [developers guide](developers/developer_information.md) as well as the [AI UX Principles](./ux/core_ux_principles.md) and [Organizing AI Modules & Features](./ux/organizing_ai_modules_and_features.md) for information on how to develop for the AI module.

## Documentation

This documentation is generated using MKDocs from the source files located in
the `docs/` directory. To build the docs locally:

1. Install MkDocs and associated plugins:
    ```
    pip install mkdocs mkdocs-material mike mkdocs-include-markdown-plugin
    ```
2. Run the MkDocs server in the project root:
    ```
    mkdocs serve
    ```
3. Open `http://localhost:8000` in your browser
