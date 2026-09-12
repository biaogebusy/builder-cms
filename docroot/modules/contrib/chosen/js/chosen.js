/**
 * @file
 * Attaches behaviors for the Chosen module.
 */
(function(Drupal, drupalSettings, once) {
  'use strict';

  // Update Chosen elements when state has changed.
  document.addEventListener('state:disabled', function(e) {
    if (e.target && e.target.matches('select')) {
      const event = new Event('chosen:updated', { bubbles: true, cancelable: true });
      e.target.dispatchEvent(event);
    }
  });

  Drupal.behaviors.chosen = {

    settings: {

      /**
       * Completely ignores elements that match one of these selectors.
       *
       * Disabled on:
       * - Field UI
       * - WYSIWYG elements
       * - Tabledrag weights
       * - Elements that have opted-out of Chosen
       * - Elements already processed by Chosen.
       *
       * @type {string}
       */
      ignoreSelector: '#field-ui-field-storage-add-form select, #entity-form-display-edit-form select, #entity-view-display-edit-form select, .wysiwyg, .draggable select[name$="[weight]"], .draggable select[name$="[position]"], .locale-translate-filter-form select, .chosen-disable, .chosen-processed',

      /**
       * Explicit "opt-in" selector.
       *
       * @type {string}
       */
      optedInSelector: 'select.chosen-enable',

      /**
       * The default selector, overridden by drupalSettings.
       *
       * @type {string}
       */
      selector: 'select:visible',

      /**
       * Minimum options for single and multiple selects.
       * These may be defined in drupalSettings.chosen.
       */
      minimum_single: 0,
      minimum_multiple: 0,

      /**
       * Other default options for Chosen.
       */
      options: {},
      minimum_width: 0,
      use_relative_width: false,
    },

    /**
     * Drupal attach behavior.
     */
    attach: function(context, settings) {
      this.settings = this.getSettings(settings);
      const elements = this.getElements(context);
      // Use Drupal's once function to process elements only once
      once('chosen', elements).forEach(function(element) {
        // If inside a Drupal dialog, resize the dialog on open & close event.
        if (element.closest('#drupal-modal')) {
          element.addEventListener('chosen:showing_dropdown', (e) => {
            const nextElement = element.nextElementSibling;
            if (nextElement) {
              const chosenDrop = nextElement.querySelector('.chosen-drop');
              if (chosenDrop) {
                chosenDrop.style.position = 'static';
                const event = new Event('dialogContentResize', { bubbles: true, cancelable: true });
                element.dispatchEvent(event);
              }
            }
          });
          element.addEventListener('chosen:hiding_dropdown', (e) => {
            const nextElement = element.nextElementSibling;
            if (nextElement) {
              const chosenDrop = nextElement.querySelector('.chosen-drop');
              if (chosenDrop) {
                chosenDrop.style.position = '';
                const event = new Event('dialogContentResize', { bubbles: true, cancelable: true });
                element.dispatchEvent(event);
              }
            }
          });
        }
        this.createChosen(element);
      }.bind(this));
    },

    /**
     * Creates a Chosen instance for a specific element.
     *
     * @param {HTMLElement} element
     *   The element.
     */
    createChosen: function(element) {
      const options = this.getElementOptions(element);
      element.chosen(options);
      this.observeDisabledState(element);
      this.redirectSelectFocus(element);

      if (options.add_helper_buttons || element.hasAttribute('chosen_add_helper_buttons')) {
        if (element.hasAttribute('multiple')) {
          const allButton = document.createElement('button');
          allButton.type = 'button';
          allButton.className = 'button chosen-helper-btn';
          allButton.textContent = 'All';
          allButton.addEventListener('click', function() {
            Array.from(element.options).forEach(function(option) {
              option.selected = true;
            });
            const event = new Event('chosen:updated', { bubbles: true, cancelable: true });
            element.dispatchEvent(event);
          });

          const noneButton = document.createElement('button');
          noneButton.type = 'button';
          noneButton.className = 'button chosen-helper-btn';
          noneButton.textContent = 'None';
          noneButton.addEventListener('click', function() {
            Array.from(element.options).forEach(function(option) {
              option.selected = false;
            });
            const event = new Event('chosen:updated', { bubbles: true, cancelable: true });
            element.dispatchEvent(event);
          });

          element.parentNode.appendChild(allButton);
          element.parentNode.appendChild(noneButton);
        }
      }
    },

    /**
     * Redirects focus from the hidden native select to the visible Chosen UI.
     *
     * Drupal Ajax may restore focus to the rebuilt native select using
     * data-drupal-selector. Once Chosen is applied, that select is hidden, so
     * keyboard users need focus moved to the generated Chosen control.
     *
     * @param {HTMLElement} element
     *   The original select element.
     */
    redirectSelectFocus: function(element) {
      if (element.__chosenFocusRedirect) {
        return;
      }

      element.__chosenFocusRedirect = true;

      const redirect = function() {
        window.requestAnimationFrame(function() {
          const chosen = element.__chosen_instance;

          if (!chosen || !chosen.container || element.disabled) {
            return;
          }

          const focusTarget = chosen.is_multiple ? chosen.search_field : chosen.selected_item;

          if (focusTarget && document.activeElement === element) {
            focusTarget.focus();
          }
        });
      };

      element.addEventListener('focus', redirect);

      // Also handle the case where Drupal Ajax has already restored focus to the
      // native select before Chosen's behavior finishes attaching.
      if (document.activeElement === element) {
        redirect();
      }
    },

    /**
     * Observes disabled attribute changes on a Chosen-managed select.
     *
     * @param {HTMLElement} element
     *   The select element.
     */
    observeDisabledState: function(element) {
      if (element.__chosenDisabledObserver) {
        return;
      }

      const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.type === 'attributes' && mutation.attributeName === 'disabled') {
            const event = new Event('chosen:updated', { bubbles: true, cancelable: true });
            element.dispatchEvent(event);
          }
        });
      });

      observer.observe(element, {
        attributes: true,
        attributeFilter: ['disabled'],
      });

      element.__chosenDisabledObserver = observer;
    },

    /**
     * Filter out elements that should not be converted into Chosen.
     *
     * @param {HTMLElement} element
     *   The element.
     *
     * @return {boolean}
     *   TRUE if the element should stay, FALSE otherwise.
     */
    filterElements: function(element) {
      // Remove elements that should be ignored completely.
      if (element.matches(this.settings.ignoreSelector)) {
        return false;
      }

      // Zero value means no minimum.
      const minOptions = element.hasAttribute('multiple') ? this.settings.minimum_multiple : this.settings.minimum_single;
      return !minOptions || element.querySelectorAll('option').length >= minOptions;
    },

    /**
     * Checks if the element is visible.
     *
     * @param {HTMLElement} el
     *   A DOM element to check visibility for.
     * @return {boolean}
     *   True if the element is visible, false otherwise.
     */
    isVisible: function(el) {
      return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    },

    /**
     * Splits a selector string by top-level commas only.
     *
     * @param {string} selector
     *   A CSS selector string.
     * @return {Array}
     *   An array of selector parts.
     */
    splitSelectors: function(selector) {
      const result = [];
      let current = '';
      let depth = 0;

      for (let i = 0; i < selector.length; i++) {
        const char = selector[i];

        if (char === '[' || char === '(') {
          depth++;
        }
        else if (char === ']' || char === ')') {
          depth = Math.max(depth - 1, 0);
        }

        if (char === ',' && depth === 0) {
          if (current.trim()) {
            result.push(current.trim());
          }
          current = '';
        }
        else {
          current += char;
        }
      }

      if (current.trim()) {
        result.push(current.trim());
      }

      return result;
    },

    /**
     * Parses the selector to handle :visible manually.
     *
     * @param {string} selector
     *   A CSS selector string that may contain :visible.
     * @return {Array}
     *   An array of elements that match the selector and are visible (if :visible is present).
     */
    getVisibleElements: function(context, selector) {
      const selectors = this.splitSelectors(selector);
      let elements = [];

      selectors.forEach(s => {
        const hasVisibleFilter = s.includes(':visible');
        const cleanSelector = s.replace(':visible', '').trim();

        if (!cleanSelector) {
          return;
        }

        let selectedElements;
        try {
          selectedElements = Array.from(context.querySelectorAll(cleanSelector));
        } catch (error) {
          return;
        }

        if (hasVisibleFilter) {
          selectedElements = selectedElements.filter(this.isVisible);
        }

        elements = elements.concat(selectedElements);
      });

      return elements;
    },

    /**
     * Retrieves the elements that should be converted into Chosen instances.
     *
     * @param {HTMLElement|Document} context
     *   A DOM Element or Document to use as context.
     * @param {string} [selector]
     *   A selector to use, defaults to the default selector in the settings.
     */
    getElements: function(context, selector) {
      context = context || document;
      let elements = this.getVisibleElements(context, selector || this.settings.selector);

      // Remove elements that should not be converted into Chosen.
      elements = elements.filter(function(element) {
        return this.filterElements(element);
      }.bind(this));

      // Add elements that have explicitly opted in to Chosen.
      const optedInElements = Array.from(context.querySelectorAll(this.settings.optedInSelector));

      // Combine elements and opted-in elements, avoiding duplicates.
      optedInElements.forEach(function(element) {
        if (!elements.includes(element)) {
          elements.push(element);
        }
      });

      return elements;
    },

    /**
     * Retrieves options used to create a Chosen instance based on an element.
     *
     * @param {HTMLElement} element
     *   The element to process.
     *
     * @return {Object}
     *   The options object used to instantiate a Chosen instance with.
     */
    getElementOptions: function(element) {
      const options = Object.assign({}, this.settings.options, this.getElementOverriddenOptions(element));
      let dimension;
      let width;

      // The width default option is considered the minimum width, so this
      // must be evaluated for every option.
      if (this.settings.minimum_width > 0) {
        // Given we need to manage settings as both percentage and pixel widths,
        // we need to handle width calculations separately.
        if (this.settings.use_relative_width) {
          dimension = '%';
          const elementWidth = element.getBoundingClientRect().width;
          const parentWidth = element.parentNode.getBoundingClientRect().width;
          width = ((elementWidth / parentWidth) * 100).toPrecision(5);
        } else {
          dimension = 'px';
          width = element.getBoundingClientRect().width;
        }

        if (width < this.settings.minimum_width) {
          options.width = this.settings.minimum_width + dimension;
        } else {
          options.width = width + dimension;
        }
      }

      // Some field widgets have cardinality, so we must respect that.
      // @see \Drupal\chosen\ChosenFormRender::preRenderSelect()
      const cardinality = element.dataset.cardinality;
      if (element.hasAttribute('multiple') && cardinality) {
        options.max_selected_options = parseInt(cardinality);
      }

      return options;
    },

    /**
     * Retrieves element-specific options used to override global defaults.
     *
     * @param {HTMLElement} element
     *   The element to process.
     *
     * @return {Object}
     *   The options object with per-element overrides.
     */
    getElementOverriddenOptions: function(element) {
      const overriddenOptions = {};

      const noResultsText = element.getAttribute('data-no_results_text');
      if (noResultsText) {
        overriddenOptions.no_results_text = noResultsText;
      }

      const searchContains = element.getAttribute('data-search_contains');
      if (searchContains === '1') {
        overriddenOptions.search_contains = true;
      }
      else if (searchContains === '2') {
        overriddenOptions.search_contains = false;
      }

      const createOption = element.getAttribute('data-create_option');
      if (createOption === 'true') {
        overriddenOptions.create_option = true;
      }

      return overriddenOptions;
    },

    /**
     * Retrieves the settings passed from Drupal.
     *
     * @param {Object} [settings]
     *   Passed Drupal settings object, if any.
     */
    getSettings: function(settings) {
      // Deep merge the settings
      return this.deepMerge({}, this.settings, (settings && settings.chosen) || drupalSettings.chosen || {});
    },

    /**
     * Deep merges multiple objects into the first object.
     * This function is used instead of Object.assign to handle nested objects.
     *
     * @param {Object} target
     *   The target object to merge properties into.
     * @param {...Object} sources
     *   The source objects from which to copy properties.
     * @return {Object}
     *   The target object.
     */
    deepMerge: function(target, ...sources) {
      if (!sources.length) return target;
      const source = sources.shift();

      if (typeof target !== 'object' || typeof source !== 'object') {
        return target;
      }

      for (const key in source) {
        if (source.hasOwnProperty(key)) {
          if (typeof source[key] === 'object') {
            if (!target[key]) Object.assign(target, { [key]: {} });
            this.deepMerge(target[key], source[key]);
          } else {
            Object.assign(target, { [key]: source[key] });
          }
        }
      }

      return this.deepMerge(target, ...sources);
    },

  };

})(Drupal, drupalSettings, once);
