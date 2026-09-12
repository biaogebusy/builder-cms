<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

/**
 * Provides additional external context to CKEditor 5 AI features.
 *
 * Use this hook to supply extra pieces of content (context elements) that can
 * be consumed by CKEditor 5 Premium Features AI integrations (e.g., AI
 * Assistant, AI Sidebar). Each element describes a single piece of contextual
 * data that may improve AI prompts and results.
 *
 * Since the hook is invoked in the editor configuration assembly process the
 * context contains only the editor object. For more meaningful context a routeMatch
 * service is advised, unless the global context is about to be added.
 *
 * The returned array should contain associative arrays with the structure
 * compatible with AIContextResource definition,
 * see https://ckeditor.com/docs/ckeditor5/latest/api/module_ai_aichat_model_aichatcontext-AIContextResource.html:
 * - id: A unique string identifier for the element.
 * - type: A type of the element, allowed values are 'text' and 'web-resource'
 * - label: A human-readable label shown in UI.
 * - data: A string containing link to resource (file or web-page)
 *         or an associative array describing the payload:
 *   - content: The content to be used as context. It can be
 *   - type: Content type, 'html' or 'plain'.
 *
 * @param \Drupal\editor\Entity\Editor $editor
 *   The editor configuration entity for which the context is requested.
 *
 * @return array[]
 *   An array of context element definitions.
 */
function hook_ckeditor5_premium_features_ai_context($editor) {
  // Example: Provide a node body as an external context element.
  $node = \Drupal::entityTypeManager()->getStorage('node')->load(1);

  // Always validate loaded entities and fields in real implementations.
  $elements = [
    [
      'id' => 'text1',
      'type' => 'text',
      'label' => 'External node',
      'data' => [
        'content' => $node ? $node->get('body')->value : '',
        'type' => 'html',
      ],
    ],
    [
      'id' => 'url1',
			'type' => 'web-resource',
			'label' => 'Blog post in Markdown',
			'data' => 'https://example.com/blog-post.md'
    ],
  ];

  return $elements;
}

/**
 * Allows modules to alter AI permissions for a given editor/text format.
 *
 * Use this hook to fine‑tune the set of CKEditor 5 AI permissions that are
 * effective for the current user and text format. It is invoked after base
 * permissions are computed based on Drupal permissions, so you can:
 * - Revoke selected permissions that were previously granted.
 * - Grant additional, more granular permissions.
 * - Completely override the set of permissions.
 *
 * CKEditor configuration assembly process is unaware of entity and/or page
 * context. Entity type based alterations can be made by using a route match
 * service.
 *
 * The permissions are simple strings in the form of namespaces, e.g.:
 * - "ai:admin"
 * - "ai:conversations:*"
 * - "ai:conversations:context:files:pdf"
 * For the full reference please see
 * https://ckeditor.com/docs/cs/latest/guides/ckeditor-ai/permissions.html
 *
 * Order of operations matters only within your implementation. Multiple modules
 * may implement this hook; the final result is the outcome of all alterations
 * applied sequentially by Drupal's module handler.
 *
 * @param string[] &$permissions
 *   An array of permission strings currently effective for
 *   the editor. Alter this array in place: unset entries to revoke, append new
 *   strings to grant, or replace the array entirely to override.
 * @param \Drupal\Core\Session\AccountProxyInterface $user
 *   The current user account proxy. Use it to make user‑dependent decisions.
 * @param string $text_format_id
 *   The machine name of the text format associated with the editor instance.
 *   Can be used to scope alterations to specific formats.
 */
function hook_ckeditor5_premium_features_ai_permissions_alter(array &$permissions, \Drupal\Core\Session\AccountProxyInterface $user, string $text_format_id): void {
  // Example 1: Revoke a broad permission previously granted.
  $to_remove = [
    'ai:conversations:*',
  ];
  foreach ($to_remove as $perm) {
    if (($key = array_search($perm, $permissions, TRUE)) !== FALSE) {
      unset($permissions[$key]);
    }
  }

  // Example 2: Grant more granular permissions.
  $permissions[] = 'ai:conversations:context:files:pdf';
  $permissions[] = 'ai:conversations:context:files:docx';

  // Example 3 (optional): Completely override the permissions set.
  // Uncomment to replace everything with a single permission.
  // $permissions = [
  //   'ai:admin',
  // ];
}
