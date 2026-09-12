import { Plugin } from 'ckeditor5/src/core.js';
import { ViewModel, createDropdown, addListToDropdown } from 'ckeditor5/src/ui.js';
import { Collection } from 'ckeditor5/src/utils.js';

import wproofreaderIcon from '../theme/icons/wproofreader.svg';
import '../theme/wproofreader.css';

/**
 * The {@code WProofreaderUI} plugin. It introduces the {@code WProofreader} toolbar button.
 */
export default class WProofreaderUI extends Plugin {
	/**
	 * @inheritDoc
	 */
	static get pluginName() {
		return 'WProofreaderUI';
	}

	/**
	 * @inheritDoc
	 */
	constructor(editor) {
		super(editor);

		this._commands = {
			toggle: 'WProofreaderToggle',
			settings: 'WProofreaderSettings',
			proofreadDialog: 'WProofreaderDialog'
		};
	}

	/**
	 * Initializes the {@code WProofreaderUI} plugin.
	 * @public
	 */
	init() {
		this._registerDropdown();
	}

	/**
	 * Registers the {@code WProofreader} dropdown among the UI components of the editor.
	 * @private
	 */
	_registerDropdown() {
		const editor = this.editor;
		const wproofreader = editor.plugins.get('WProofreader');

		editor.ui.componentFactory.add('wproofreader', (locale) => {
			const dropdownView = createDropdown(locale);

			let dropdownItemsDefinitions, actions;

			dropdownView.buttonView.set({
				label: 'WProofreader text checker',
				icon: wproofreaderIcon,
				tooltip: true
			});

			dropdownView.on('change:isOpen', () => {
				const ready = wproofreader.isInstancesReady();
				const enabled = wproofreader.isInstancesEnabled();

				if (!ready) {
					dropdownView.class = 'ck-wproofreader-empty';

					return;
				}

				if (!dropdownItemsDefinitions) {
					actions = wproofreader.getStaticActions();
					dropdownItemsDefinitions = this._getDropdownItemsDefinitions(actions);

					addListToDropdown(dropdownView, dropdownItemsDefinitions);
				}

				dropdownView.class = '';

				dropdownItemsDefinitions.map((item) => {
					item.model.class = enabled ? '' : 'ck-hidden';

					if (item.model.commandParam === 'WProofreaderToggle') {
						item.model.label = enabled ? item.model.localization.disable : item.model.localization.enable;
						item.model.class = '';
					}
				});
			});

			// Execute the command when the dropdown item is clicked (executed).
			dropdownView.on('execute', (evt) => {
				editor.execute(evt.source.commandParam);
			});

			// The dropdown view should be disabled if the WProofreaderToggle command is disabled.
			dropdownView.bind('isEnabled').to(editor.commands.get('WProofreaderToggle'));

			return dropdownView;
		});
	}

	/**
	 * Creates dropdown items for the {@code WProofreader} actions.
	 * @private
	 */
	_getDropdownItemsDefinitions(actions) {
		const itemDefinitions = new Collection();

		actions.forEach((action) => {
			const definition = {
				type: 'button',
				model: new ViewModel({
					commandParam: this._commands[action.name],
					label: action.localization.default,
					localization: action.localization,
					class: '',
					withText: true
				})
			};

			// Add the item definition to the collection.
			itemDefinitions.add(definition);
		})

		return itemDefinitions;
	}
}
