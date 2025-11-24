import { Plugin } from 'ckeditor5/src/core';
import { createDropdown, addListToDropdown, ViewModel } from 'ckeditor5/src/ui';
import { Collection } from 'ckeditor5/src/utils';

import templateIcon from '../../../../icons/template.svg';

export default class Template extends Plugin {
    async init() {
        const editor = this.editor;
        const template_config = this.editor.config.get('template');
        console.log('Value: ', template_config['file_path']);
        const templateArray = await fetch(template_config['file_path'])
          .then(res => res.json())
          .catch(error => console.log(error));
        editor.ui.componentFactory.add('template', function (locale) {

        const dropdownView = createDropdown(locale);
        dropdownView.buttonView.set({
          label: template_config['custom_toolbar_text'],
          withText: template_config['show_toolbar_text'] || false,
          icon: templateIcon,
          tooltip: true
        });
          addListToDropdown(dropdownView, createItems(templateArray));
          dropdownView.listenTo(dropdownView, 'execute', _onexecute);
          dropdownView.render();
          return dropdownView;
        });

        //create the items for the dropdown menu
        const createItems = (templateArray) => {
          const collection = new Collection();
            templateArray.forEach(template => {
              const templateElement = new ViewModel({
                label: template.title,
                withText: true,
                icon: isSVG(template.icon) ? template.icon : templateIcon,
                tooltip: template.description || template.title,
                html: template.html
              });
              collection.add({
                type: 'button',
                model: templateElement
              });
            });
            return collection;
        }

        //function that is executed when the button is clicked
        const _onexecute = (event) => {
          editor.model.change( writer => {

            const template = templateArray.find(template => template.title === event.source.label);
            const viewFragment = editor.data.processor.toView(template.html);
            const modelFragment = editor.data.toModel(viewFragment);
            editor.model.insertContent(modelFragment, editor.model.document.selection);
          });
        }
        function isSVG(svgString) {
          if (svgString == null){
            return false;
          }
          return svgString.trim().startsWith('<svg');
        }
      }
    }
