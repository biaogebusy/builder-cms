import { Plugin } from 'ckeditor5/src/core.js';

import WProofreaderEditing from './wproofreaderediting.js';
import WProofreaderUI from './wproofreaderui.js';
import { ScriptLoader } from './utils/script-loader.js';

const DISABLE_COMMAND_ID = 'WProofreaderToggleCommandDisabling';
const DISABLE_INSTANCES_ID = 'InstancesDisabling';

/**
 * Initializes and creates instances of the {@code WEBSPELLCHECKER}.
 */
export default class WProofreader extends Plugin {
	/**
	 * @inheritdoc
	 */
	static get requires() {
		return [WProofreaderEditing, WProofreaderUI];
	}

	/**
	 * @inheritdoc
	 */
	static get pluginName() {
		return 'WProofreader';
	}

	/**
	 * @inheritDoc
	 */
	constructor(editor) {
		super(editor);

		/**
		 * Flag indicating whether the {@code WProofreaderToggle} command is enabled.
		 *
		 * @observable
		 * @member {Boolean}
		 */
		this.set('isToggleCommandEnabled', true);

		this._instances = [];

		this._restrictedEditingName = 'RestrictedEditingMode';
	}

	/**
	 * Initializes the {@code WProofreader} plugin.
	 * @public
	 */
	init() {
		this._userOptions = this._getUserOptions();
		this._setTheme();
		this._setAutoStartup();
		this._setBadgeOffset();
		this._setIsEnabled(this._userOptions.autoStartup, DISABLE_INSTANCES_ID);

		this._loadWscbundle()
			.then(() => {
				this._handleWscbundleLoaded();
			})
			.catch((error) => {
				throw new Error(error);
			});

		this.bind('isToggleCommandEnabled').to(
			this.editor.commands.get('WProofreaderToggle'), 'isEnabled',
			(isEnabled) => this._handleToggleCommandEnabled(isEnabled)
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * Destroys the {@code WProofreader} plugin.
	 * @public
	 */
	destroy() {
		super.destroy();
		this._instances.forEach((instance) => instance.destroy());
		this._instances = null;
	}

	/**
	 * Gets the configuration of the {@code WEBSPELLCHECKER} from the {@code CKEditor 5} config.
	 * @private
	 */
	_getUserOptions() {
		const config = this.editor.config.get('wproofreader');

		if (!config) {
			throw new Error('No WProofreader configuration.');
		}

		return config;
	}

	/**
	 * Checks if the theme option exists otherwise sets ckeditor5 theme.
	 * @private
	 */
	_setTheme() {
		if (!this._userOptions.theme) {
			this._userOptions.theme = 'ckeditor5';
		}
	}

	/**
	 * Checks if the autoStartup option exists otherwise sets {@code true} value.
	 * @private
	 */
	_setAutoStartup() {
		if (!Object.prototype.hasOwnProperty.call(this._userOptions, 'autoStartup')) {
			this._userOptions.autoStartup = true;
		}
	}

	/**
	 * Checks if the badgeOffsetX/badgeOffsetY and fullSizeBadge options exist otherwise sets values by default.
	 * @private
	 */
	_setBadgeOffset() {
		const badgeOffset = 11;

		if (this._userOptions.fullSizeBadge) {
			return;
		}

		if (!Object.prototype.hasOwnProperty.call(this._userOptions, 'badgeOffsetX')) {
			this._userOptions.badgeOffsetX = badgeOffset;
		}

		if (!Object.prototype.hasOwnProperty.call(this._userOptions, 'badgeOffsetY')) {
			this._userOptions.badgeOffsetY = badgeOffset;
		}
	}

	/**
	 * Configures the {@code isEnabled} state of the plugin.
	 * @private
	 */
	_setIsEnabled(enable, disableId) {
		enable ? this.clearForceDisabled(disableId) : this.forceDisabled(disableId);
	}

	/**
	 * Loads {@code wscbundle} script.
	 * @private
	 */
	_loadWscbundle() {
		const scriptLoader = new ScriptLoader(this._userOptions.srcUrl);

		return scriptLoader.load()
			.then(() => {
				if (!window.WEBSPELLCHECKER) {
					throw new Error('WEBSPELLCHECKER is not defined.');
				}
			});
	}

	/**
	 * Handles the {@code wscbundle} loaded state.
	 * @private
	 */
	_handleWscbundleLoaded() {
		if (this.editor.state === 'ready') {
			this._createInstances();
		} else {
			this._subscribeOnEditorReady();
		}
	}

	/**
	 * Creates {@code WEBSPELLCHECKER} intances.
	 * @private
	 */
	_createInstances() {
		const roots = this.editor.editing.view.domRoots.values();

		this._setFields();

		for (const root of roots) {
			this._createInstance(root);
		}
	}

	/**
	 * Sets extra fields related to the {@code WEBSPELLCHECKER} instance creating.
	 * @private
	 */
	_setFields() {
		this._isRestrictedEditingMode = this._checkRestrictedEditingMode();
		this._options = this._createOptions();
	}

	/**
	 * Checks if the current editor in the restricted editing mode.
	 * @private
	 */
	_checkRestrictedEditingMode() {
		return this.editor.plugins.has(this._restrictedEditingName);
	}

	/**
	 * Creates options for the {@code WEBSPELLCHECKER} initialization.
	 * @private
	 */
	_createOptions() {
		return {
			appType: 'proofreader_ck5',
			restrictedEditingMode: this._isRestrictedEditingMode,
			disableBadgePulsing: true,
			onCommitOptions: this._onCommitOptions.bind(this),
			onToggle: this._onToggle.bind(this)
		};
	}

	/**
	 * Handles the {@code commitOptions} behavior of the {@code WEBSPELLCHECKER} instance.
	 * @private
	 */
	_onCommitOptions(changedOptions) {
		this._syncOptions(changedOptions);
	}

	/**
	 * Synchronizes the changed options between each instance of the {@code WEBSPELLCHECKER}.
	 * @private
	 */
	_syncOptions(changedOptions) {
		this._instances.forEach((instance) => {
			instance.commitOption(changedOptions, { ignoreCallback: true });
		});
	}

	/**
	 * Handles the {@code toggle} behavior of the {@code WEBSPELLCHECKER} instance.
	 * @private
	 */
	_onToggle(instance) {
		const enable = !instance.isDisabled();

		this._setIsEnabled(enable, DISABLE_INSTANCES_ID);
		this._syncToggle(enable);
	}

	/**
	 * Synchronizes the toggle state between each instance of the {@code WEBSPELLCHECKER}.
	 * @private
	 */
	_syncToggle(enable) {
		this._instances.forEach((instance) => {
			enable ? this._enableInstance(instance) : this._disableInstance(instance);
		});
	}

	/**
	 * Enables an instance of the {@code WEBSPELLCHECKER}.
	 * @private
	 */
	_enableInstance(instance) {
		const options = { ignoreCallback: true };

		if (!this.isEnabled) {
			return;
		}

		instance.enable(options);
	}

	/**
	 * Disables an instance of the {@code WEBSPELLCHECKER}.
	 * @private
	 */
	_disableInstance(instance) {
		const options = { ignoreCallback: true };

		instance.disable(options);
	}

	/**
	 * Creates an instance of the {@code WEBSPELLCHECKER}.
	 * @private
	 */
	_createInstance(root) {
		window.WEBSPELLCHECKER.init(this._mergeOptions(root), this._handleInstanceCreated.bind(this));
	}

	/**
	 * Creates the configuration of the {@code WEBSPELLCHECKER}.
	 * @private
	 */
	_mergeOptions(container) {
		return Object.assign({}, this._userOptions, this._options, { container: container });
	}

	/**
	 * Handles a state when an instance of the {@code WEBSPELLCHECKER} completely created.
	 * @private
	 */
	_handleInstanceCreated(instance) {
		if (!instance) {
			return;
		}

		if (this.editor.state === 'destroyed') {
			instance.destroy();

			return;
		}

		if (!this.isEnabled) {
			this._disableInstance(instance);
		}

		this._instances.push(instance);
	}

	/**
	 * Subscribes on the ready state of the {@code CKEditor 5}.
	 * @private
	 */
	_subscribeOnEditorReady() {
		this.editor.on('ready', () => {
			this._createInstances();
		});
	}

	/**
	 * Handles an enabled state of the {@code WProofreaderToggle} command.
	 * @private
	 */
	_handleToggleCommandEnabled(isEnabled) {
		this._setIsEnabled(isEnabled, DISABLE_COMMAND_ID);
		this._syncToggle(isEnabled);

		// Method should return a boolean value to correct work of the bind functionality.
		return isEnabled;
	}

	/**
	 * Returns available static actions of the {@code WEBSPELLCHECKER}.
	 * @public
	 *
	 * @returns {Array} Static actions.
	 */
	getStaticActions() {
		if (this._instances.length === 0) {
			return [];
		}

		return this._instances[0].getStaticActions();
	}

	/**
	 * Toggles instances state of the {@code WEBSPELLCHECKER}.
	 * @public
	 */
	toggle() {
		if (this._instances.length === 0) {
			return;
		}

		const enable = this.isInstancesEnabled();

		this._setIsEnabled(!enable, DISABLE_INSTANCES_ID);
		this._syncToggle(!enable);
	}

	/**
	 * Opens settings of the {@code WEBSPELLCHECKER}.
	 * @public
	 */
	openSettings() {
		if (this._instances.length === 0) {
			return;
		}

		this._instances[0].openSettings();
	}

	/**
	 * Opens the proofread Dialog of the {@code WEBSPELLCHECKER}.
	 * @public
	 */
	openDialog() {
		if (this._instances.length === 0) {
			return;
		}

		this._instances[0].openDialog();
	}

	/**
	 * Indicates that instances of the {@code WEBSPELLCHECKER} are ready to use.
	 * @public
	 *
	 * @returns {Boolean} {@code true} if instances are ready, {@code false} otherwise.
	 */
	isInstancesReady() {
		return this._instances.length > 0;
	}

	/**
	 * Indicates that instances of the {@code WEBSPELLCHECKER} are enabled.
	 * @public
	 *
	 * @returns {Boolean} {@code true} if instances are enabled, {@code false} otherwise.
	 */
	isInstancesEnabled() {
		if (this._instances.length === 0) {
			return false;
		}

		return !this._instances[0].isDisabled();
	}
}
