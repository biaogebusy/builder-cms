/**
 * @file defines InsertVideoEmbedCommand, which is executed when the videoEmbed
 * toolbar button is pressed.
 */

import { Command } from 'ckeditor5/src/core';

function createVideoEmbed(writer, attributes) {
  // Create instances of the element registered with the editor in
  // video-embed-editing.js.
  const videoEmbed = writer.createElement('videoEmbed', attributes);

  // Return the element to be added to the editor.
  return videoEmbed;
}

export default class InsertVideoEmbedCommand extends Command {
  execute(attributes) {
    const { model } = this.editor;

    model.change((writer) => {
      // Insert <videoEmbed *></videoEmbed> at the current selection position
      // in a way that will result in creating a valid model structure.
      model.insertContent(createVideoEmbed(writer, attributes));
    });
  }

  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;

    // Determine if the cursor (selection) is in a position where adding a
    // videoEmbed is permitted. This is based on the schema of the model(s)
    // currently containing the cursor.
    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'videoEmbed',
    );

    // If the cursor is not in a location where a videoEmbed can be added,
    // return null so the addition doesn't happen.
    this.isEnabled = allowedIn !== null;
  }
}
