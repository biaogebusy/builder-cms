<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge;

/** Names shared by the model, queue and index. */
final class Knowledge {

  public const NODE = 'xinshi_knowledge';
  public const MEDIA = 'xinshi_knowledge_document';
  public const ATTACHMENTS = 'field_knowledge_documents';
  public const FILE = 'field_knowledge_file';
  public const INDEX = 'xinshi_knowledge';
  public const QUEUE = 'xinshi_knowledge_extract';
  public const TABLE = 'xinshi_knowledge_extraction';
  public const MAX_FILE_BYTES = 20 * 1024 * 1024;
  public const MAX_TEXT_CHARACTERS = 1000000;

}
