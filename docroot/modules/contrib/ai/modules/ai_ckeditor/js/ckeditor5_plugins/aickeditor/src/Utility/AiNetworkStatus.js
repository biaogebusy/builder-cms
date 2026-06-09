import {Plugin} from 'ckeditor5/src/core';
import {Template, View} from 'ckeditor5/src/ui';

export default class AiNetworkStatus extends Plugin {

  constructor( editor ) {
    super(editor);
    this.set( 'status', Drupal.t('Idle') );
  }

  init() {
    const editor = this.editor;
    editor.sourceElement.parentElement.appendChild( this.statusContainer() );

    this.on('ai_status', (evt, data) => {
      this._setStatus(data);
    });
  }

  statusContainer() {
    const editor = this.editor;
    const t = editor.t;
    const bind = Template.bind(this, this);
    const children = [];

    if (!this._outputView) {
      this._outputView = new View();

      this.bind('_ai_status').to(this, 'status', status => {
        return Drupal.t('AI Writer: @status', { '@status': status });
      });

      children.push({
        tag: 'div',
        children: [
          {
            text: [bind.to('_ai_status')]
          }
        ],
        attributes: {
          class: 'ck-ai-status__activity'
        }
      });

      this._outputView.setTemplate({
        tag: 'div',
        attributes: {
          class: [
            'ck',
            'ck-ai-status'
          ]
        },
        children
      });

      this._outputView.render();
    }

    return this._outputView.element;
  }

  /**
   * @inheritDoc
   */
  destroy() {
    if (this._outputView) {
      this._outputView.element.remove();
      this._outputView.destroy();
    }
    super.destroy();
  }

  _setStatus(data) {
    this.set('status', data.status);
  }
}
