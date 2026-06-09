<?php

namespace Drupal\ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Plugin implementation of the html to markdown converter.
 */
#[FunctionCall(
  id: 'ai_agent:html_to_markdown',
  function_name: 'ai_agent_html_to_markdown',
  name: 'HTML to Markdown',
  description: 'This method is used to convert HTML to Markdown.',
  group: 'modification_tools',
  context_definitions: [
    'content' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Content"),
      description: new TranslatableMarkup("The html markup to convert to markdown."),
      required: TRUE,
    ),
  ],
)]
class HtmlToMarkdown extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $markdown_options = [
      'header_style' => 'atx',
      'strip_tags' => TRUE,
      'strip_whitespace' => TRUE,
      'strip_placeholder_links' => TRUE,
    ];
    $converter = new HtmlConverter($markdown_options);
    $content = $this->getContextValue('content');
    $content = $converter->convert($content);
    $this->setOutput($content);
  }

}
