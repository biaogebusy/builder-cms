import { Plugin } from 'ckeditor5/src/core';
import { toWidget, toWidgetEditable, Widget } from 'ckeditor5/src/widget';
import InsertVideoEmbedCommand from './insert-video-embed-command';

/**
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * CKEditor 5 internally interacts with videoEmbed as this model:
 * <videoEmbed videoUrl="https://some.video.url" responsive="true-or-false"
 * width="42" height="42" autoplay="true-or-false"
 * title_format="@provider | @title" title_fallback="true-or-false"
 * previewThumbnail="/some/image/path.jpg" settingsSummary="Some help
 * text."></videoEmbed>
 *
 * Which is converted in database (dataDowncast) as this:
 * <p>{"preview_thumbnail":"/some/image/path.jpg",
 * "video_url":"https://some.video.url","settings":{"responsive":0or1,"width":"42","height":"42","title_format":"@provider | @title",title_fallback:"true-or-false","autoplay":0or1}",
 * settings_summary":["Some help text."]}</p>
 *
 * The Drupal video_embed_wysiwyg format filter will then convert this into a
 * real HTML video embed, on PHP frontend rendering.
 *
 * videoEmbed model elements are also converted to HTML for preview in CKE5 UI
 * (editingDowncast).
 *
 * And the database markup can be converted back to model (upcast).
 *
 * This file has the logic for defining the videoEmbed model, and for how it is
 * converted from/to standard DOM markup for database/UI.
 */
export default class VideoEmbedEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  init() {
    this._defineSchema();
    this._defineConverters();
    this.editor.commands.add(
      'insertVideoEmbed',
      new InsertVideoEmbedCommand(this.editor),
    );
  }

  /*
   * This registers the structure that will be seen by CKEditor 5 as
   * <videoEmbed *></videoEmbed>
   *
   * The logic in _defineConverters() will determine how this is converted to
   * markup.
   */
  _defineSchema() {
    // Schemas are registered via the central `editor` object.
    const schema = this.editor.model.schema;

    schema.register('videoEmbed', {
      inheritAllFrom: '$blockObject',
      allowAttributes: [
        'videoUrl',
        'responsive',
        'width',
        'height',
        'title_format',
        'title_fallback',
        'autoplay',
        'previewThumbnail',
        'settingsSummary',
      ],
    });
  }

  /**
   * Converters determine how CKEditor 5 models are converted into markup and
   * vice-versa.
   */
  _defineConverters() {
    // Converters are registered via the central editor object.
    const { conversion } = this.editor;

    // Upcast Converters: determine how existing HTML is interpreted by the
    // editor. These trigger when an editor instance loads.
    //
    // If <p>{"preview_thumbnail":......}</p> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <videoEmbed> model.
    // @see
    // https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_conversion_conversion-ConverterDefinition.html
    conversion.for('upcast').elementToElement({
      view(element) {
        const child = element.getChild(0);
        if (element.name === 'p') {
          if (child && child.is('text')) {
            const text = element.getChild(0).data;
            if (
              text.match(
                /^({(?=.*preview_thumbnail\b)(?=.*settings\b)(?=.*video_url\b)(?=.*settings_summary)(.*)})$/,
              )
            ) {
              return { name: true };
            }
          }
        }
        return null;
      },
      model: (viewElement, { writer }) => {
        const data = JSON.parse(viewElement.getChild(0).data);
        return writer.createElement('videoEmbed', {
          videoUrl: data.video_url,
          responsive: !!data.settings.responsive,
          width: data.settings.width,
          height: data.settings.height,
          title_format: data.settings.title_format,
          title_fallback: !!data.settings.title_fallback,
          autoplay: !!data.settings.autoplay,
          previewThumbnail: data.preview_thumbnail,
          settingsSummary: data.settings_summary[0],
        });
      },
      // Avoid it's converted to a normal paragraph.
      converterPriority: 'high',
    });

    // Data Downcast Converters: converts stored model data into HTML.
    // These trigger when content is saved.
    //
    // Instances of <videoEmbed> are saved as
    // <p>{"preview_thumbnail":......}</p>.
    conversion.for('dataDowncast').elementToElement({
      model: 'videoEmbed',
      view: (modelElement, { writer }) => {
        const data = {};
        data.preview_thumbnail = modelElement.getAttribute('previewThumbnail');
        data.video_url = modelElement.getAttribute('videoUrl');
        data.settings = {};
        [
          'responsive',
          'width',
          'height',
          'autoplay',
          'title_format',
          'title_fallback',
        ].forEach(function (attributeName) {
          data.settings[attributeName] =
            modelElement.getAttribute(attributeName);
        });
        data.settings_summary = [modelElement.getAttribute('settingsSummary')];
        return writer.createContainerElement('p', {}, [
          writer.createText(JSON.stringify(data)),
        ]);
      },
    });

    // Editing Downcast Converters. These render the content to the user for
    // editing, i.e. this determines what gets seen in the editor. These trigger
    // after the Data Upcast Converters, and are re-triggered any time there
    // are changes to any of the models' properties.
    //
    // Convert the <videoEmbed> model into a container widget in the editor UI.
    conversion.for('editingDowncast').elementToElement({
      model: 'videoEmbed',
      view: (modelElement, { writer }) => {
        const preview = writer.createContainerElement(
          'span',
          { class: 'video-embed-widget' },
          [
            writer.createEmptyElement('img', {
              class: 'video-embed-widget__image',
              src: modelElement.getAttribute('previewThumbnail'),
            }),
            writer.createContainerElement(
              'span',
              { class: 'video-embed-widget__summary' },
              [writer.createText(modelElement.getAttribute('settingsSummary'))],
            ),
          ],
        );
        return toWidget(preview, writer, { label: Drupal.t('Video Embed') });
      },
    });
  }
}
