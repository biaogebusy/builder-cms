# AI

AI (artificial intelligence) integration for Drupal - a unified framework for
integrating various AI models and services into your Drupal site.

## Dependencies

- Drupal ^10.3 || ^11
- [Key module](https://www.drupal.org/project/key)
- Requires at least one AI Provider to be installed and configured

## Documentation

This project uses MkDocs for documentation: you can see the current
documentation at [https://project.pages.drupalcode.org/ai/](https://project.pages.drupalcode.org/ai/).
The documentation source files are located in the `docs/` directory, and to
build your own local version of the documentation please follow these steps:

1. Install MkDocs and associated plugins:
    ```
    pip install mkdocs mkdocs-material mike mkdocs-include-markdown-plugin
    ```
2. Run the MkDocs server in the project root:
    ```
    mkdocs serve
    ```
3. Open `http://localhost:8000` in your browser
