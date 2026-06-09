/**
 * @file
 * CodeMirror 6 JSON Schema editor for Drupal.
 *
 * Attaches a CodeMirror editor with JSON language support to each
 * [data-ai-json-schema-editor] element. The editor syncs its content
 * back to a sibling hidden textarea for normal form submission.
 */

import { EditorView, basicSetup } from 'codemirror';
import { json } from '@codemirror/lang-json';
import { linter } from '@codemirror/lint';
import { jsonParseLinter } from '@codemirror/lang-json';

((Drupal, once) => {
    'use strict';

    Drupal.behaviors.aiJsonSchemaEditor = {
        attach(context) {
            const editors = once(
                'ai-json-schema-editor',
                '[data-ai-json-schema-editor]',
                context,
            );

            editors.forEach((editorContainer) => {
                const editorId = editorContainer.getAttribute('data-ai-json-schema-editor');
                const hiddenInput = document.querySelector(
                    `[data-ai-json-schema-textarea="${editorId}"]`,
                );
                const fallbackTextarea = document.querySelector(
                    `[data-ai-json-schema-fallback="${editorId}"]`,
                );

                if (!hiddenInput) {
                    return;
                }

                // Hide the fallback textarea and show the CodeMirror editor.
                // If JS fails, the user still has the plain textarea.
                if (fallbackTextarea) {
                    fallbackTextarea.style.display = 'none';
                    fallbackTextarea.removeAttribute('name');
                }
                editorContainer.style.display = '';

                // Get the initial value and try to pretty-print it.
                let initialValue = hiddenInput.value || '';
                if (initialValue) {
                    try {
                        initialValue = JSON.stringify(JSON.parse(initialValue), null, 2);
                    }
                    catch (e) {
                        // Keep original value if it's not valid JSON yet.
                    }
                }

                const updateListener = EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        hiddenInput.value = update.state.doc.toString();
                    }
                });

                new EditorView({
                    doc: initialValue,
                    extensions: [
                        basicSetup,
                        json(),
                        linter(jsonParseLinter()),
                        updateListener,
                    ],
                    parent: editorContainer,
                });
            });
        },
    };
})(Drupal, once);
