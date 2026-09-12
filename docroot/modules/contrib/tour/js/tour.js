/* global offsetFloating */
/**
 * @file
 * Attaches behaviors for the Tour module's toolbar tab.
 */
(($, Backbone, Shepherd, offsetFloating, Drupal, settings, document, once) => {
  const queryString = decodeURI(window.location.search);

  /**
   * Attaches the tour's toolbar tab behavior.
   *
   * It uses the query string for:
   * - tour: When ?tour=1 is present, the tour will start automatically after
   *   the page has loaded.
   * - tips: Pass ?tips=class in the url to filter the available tips to the
   *   subset which match the given class.
   *
   * @example
   * http://example.com/foo?tour=1&tips=bar
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attach tour functionality on `tour` events.
   */
  Drupal.behaviors.tour = {
    attach(context) {
      once('tour', 'body').forEach(() => {
        const model = new Drupal.tour.models.StateModel();
        // eslint-disable-next-line no-new
        new Drupal.tour.views.ToggleTourView({
          el: $(context).find('.js-tour-start-button, .js-tour-start-toolbar'),
          model,
        });

        // Initialization: check whether a tour is available on the current
        // page.
        if (settings._tour_internal) {
          model.set('tour', settings._tour_internal);
        }

        // Provide an API.
        Drupal.tour.setActive = function setActive(active = true) {
          model.set('isActive', active);
        };
        Drupal.tour.get = function get() {
          return Shepherd.activeTour;
        };
        Drupal.tour.getPromise = function getPromise(timeout = 2000) {
          const timerMillSecs = 10;
          return new Promise((resolve, reject) => {
            (function waitForTour(timeLeft) {
              if (Shepherd.activeTour) {
                resolve(Shepherd.activeTour);
              }
              if (timeLeft <= 0) {
                reject();
              }
              setTimeout(function waitForTourTimeout() {
                waitForTour(timeLeft - timerMillSecs);
              }, timerMillSecs);
            })(timeout);
          });
        };

        // Register events:
        model
          // Allow other scripts to respond to tour events.
          .on('change:isActive', (tourModel, isActive) => {
            $(document).trigger(
              isActive ? 'drupalTourStarted' : 'drupalTourStopped',
            );
          });

        // Start the tour immediately if toggled via query string.
        window.addEventListener('load', (event) => {
          if (/[?&]tour(=|$)/i.test(queryString)) {
            model.set('isActive', true);
          }
        });

        document.addEventListener('keydown', function handleKeydown(event) {
          if (event.altKey && event.code === 'KeyT') {
            model.set('isActive', true);
            event.preventDefault();
            event.stopPropagation();
          }
        });
      });
    },
  };

  /**
   * @namespace
   */
  Drupal.tour = Drupal.tour || {
    /**
     * @namespace Drupal.tour.models
     */
    models: {},

    /**
     * @namespace Drupal.tour.views
     */
    views: {},
  };

  /**
   * Backbone Model for tours.
   *
   * @constructor
   *
   * @augments Backbone.Model
   */
  Drupal.tour.models.StateModel = Backbone.Model.extend(
    /** @lends Drupal.tour.models.StateModel# */ {
      /**
       * @type {object}
       */
      defaults: /** @lends Drupal.tour.models.StateModel# */ {
        /**
         * Indicates whether the Drupal root window has a tour.
         *
         * @type {Array}
         */
        tour: [],

        /**
         * Indicates whether the tour is currently running.
         *
         * @type {boolean}
         */
        isActive: false,

        /**
         * Indicates which tour is the active one (necessary to cleanly stop).
         *
         * @type {Array}
         */
        activeTour: [],
      },
    },
  );

  Drupal.tour.views.ToggleTourView = Backbone.View.extend(
    /** @lends Drupal.tour.views.ToggleTourView# */ {
      /**
       * @type {object}
       */
      events: { click: 'onClick' },

      /**
       * Handles edit mode toggle interactions.
       *
       * @constructs
       *
       * @augments Backbone.View
       */
      initialize() {
        this.listenTo(this.model, 'change:tour change:isActive', this.render);
        this.listenTo(this.model, 'change:isActive', this.toggleTour);
      },

      /**
       * {@inheritdoc}
       *
       * @return {Drupal.tour.views.ToggleTourView}
       *   The `ToggleTourView` view.
       */
      render() {
        const adminCloseButton = document.getElementsByClassName(
          'admin-toolbar__close-button',
        );
        if (
          adminCloseButton.length > 0 &&
          document.documentElement.clientWidth < 1024
        ) {
          adminCloseButton[0].click();
        }
        // Render the state.
        const isActive = this.model.get('isActive');
        this.$el.each(function toggleElement(index, element) {
          // eslint-disable-next-line no-unused-vars
          const toggleButton =
            $(element).prop('tagName') === 'BUTTON' ||
            $(element).attr('role') === 'button'
              ? $(element)
              : $(element).find('button, [role="button"]');
          $(element).toggleClass('is-active', isActive);
        });
        return this;
      },

      /**
       * Model change handler; starts or stops the tour.
       */
      toggleTour() {
        if (this.model.get('isActive')) {
          this._removeIrrelevantTourItems(this._getTour());
          const tourItems = this.model.get('tour');
          const that = this;

          if (tourItems.length) {
            const mediaQuery = window.matchMedia(
              '(prefers-reduced-motion: reduce)',
            );
            if (mediaQuery.matches) {
              settings.tourShepherdConfig.defaultStepOptions.scrollTo.behavior =
                'auto';
            }

            const shepherdTour = new Shepherd.Tour(settings.tourShepherdConfig);
            shepherdTour.on('cancel', () => {
              if (that.el && typeof that.el.focus === 'function') {
                that.el.focus();
              }
              that.model.set('isActive', false);
            });
            shepherdTour.on('complete', () => {
              that.model.set('isActive', false);
            });

            tourItems.forEach((tourStepConfig, index) => {
              const actionButtons = [
                Drupal.tour.nextButton(shepherdTour, tourStepConfig),
              ];

              if (index > 0) {
                actionButtons.unshift(Drupal.tour.prevButton(shepherdTour));
              }

              // Add recap URL to step config if enabled.
              if (settings.tourRecap?.enabled && settings.tourRecap?.urls) {
                const recapUrls = Object.values(settings.tourRecap.urls);
                if (recapUrls.length > 0) {
                  tourStepConfig.recapUrl = recapUrls[0];
                }
              }
              // Create the configuration for a given tour step by using values
              // defined in TourViewBuilder.
              // @see \Drupal\tour\TourViewBuilder::viewMultiple()
              const tourItemOptions = {
                id: tourStepConfig.id,
                title: tourStepConfig.title
                  ? Drupal.checkPlain(tourStepConfig.title)
                  : null,
                text: () => Drupal.theme('tourItemContent', tourStepConfig),
                attachTo: tourStepConfig.attachTo,
                buttons: actionButtons,
                classes: tourStepConfig.classes,
                index,
                floatingUIOptions: {
                  middleware: [offsetFloating({ mainAxis: 20, crossAxis: 0 })],
                },
              };

              tourItemOptions.when = {
                show() {
                  const nextButton =
                    shepherdTour.currentStep?.el?.querySelector?.(
                      'footer button',
                    );

                  if (nextButton && typeof nextButton.focus === 'function') {
                    nextButton.focus();
                  }
                },
              };

              const step = shepherdTour.addStep(tourItemOptions);
              step.on('before-show', function beforeShow() {
                const selector = step.options.attachTo.element;
                // eslint-disable-next-line no-jquery/no-is
                if (selector && !$(selector).is(':visible')) {
                  const details = $(selector).parents('details');
                  if (details) {
                    const id = details.attr('id');
                    const link = $('a[href="#'.concat(id, '"]'));
                    if (link.length) {
                      link.click();
                    } else {
                      details.find('summary').click();
                    }
                  }
                }
              });

              // @todo remove when fixed upstream.
              step.on('show', function onShow() {
                const shepherdElement = document.querySelectorAll(
                  '.shepherd-element.shepherd-enabled',
                );
                if (shepherdElement) {
                  shepherdElement.forEach((element) =>
                    element.setAttribute('aria-modal', 'true'),
                  );
                }
              });
            });
            shepherdTour.start();
            this.model.set({ isActive: true, activeTour: shepherdTour });
          }
        } else {
          this.model.get('activeTour').cancel();
          this.model.set({ isActive: false, activeTour: [] });
        }
      },

      /**
       * Toolbar tab click event handler; toggles isActive.
       *
       * @param {jQuery.Event} event
       *   The click event.
       */
      onClick(event) {
        this.model.set('isActive', !this.model.get('isActive'));
        event.preventDefault();
        event.stopPropagation();
      },

      /**
       * Gets the tour.
       *
       * @return {array}
       *   An array of Shepherd tour item objects.
       */
      _getTour() {
        return this.model.get('tour');
      },

      /**
       * Removes tour items for elements that don't have matching page elements.
       *
       * Or that are explicitly filtered out via the 'tips' query string.
       *
       * @example
       * <caption>This will filter out tips that do not have a matching
       * page element or don't have the "bar" class.</caption>
       * http://example.com/foo?tips=bar
       *
       * @param {Object[]} tourItems
       *   An array containing tour Step config objects.
       *   The object properties relevant to this function:
       *   - classes {string}: A string of classes to be added to the tour step
       *     when rendered.
       *   - selector {string}: The selector a tour step is associated with.
       */
      _removeIrrelevantTourItems(tourItems) {
        const tips = /tips=([^&]+)/.exec(queryString);
        const filteredTour = tourItems.filter((tourItem) => {
          // If the query parameter 'tips' is set, remove all tips that don't
          // have the matching class. The `tourItem` variable is a step config
          // object, and the 'classes' property is a ShepherdJS Step() config
          // option that provides a string.
          if (
            tips &&
            tourItem.hasOwnProperty('classes') &&
            tourItem.classes.indexOf(tips[1]) === -1
          ) {
            return false;
          }

          // If a selector is configured but there isn't a matching element,
          // return false.
          return !(
            tourItem.selector && !document.querySelector(tourItem.selector)
          );
        });

        // If there are tours filtered, we'll have to update model.
        if (tourItems.length !== filteredTour.length) {
          filteredTour.forEach((filteredTourItem, filteredTourItemId) => {
            filteredTour[filteredTourItemId].counter = Drupal.t(
              '!tour_item of !total',
              {
                '!tour_item': filteredTourItemId + 1,
                '!total': filteredTour.length,
              },
            );

            if (filteredTourItemId === filteredTour.length - 1) {
              filteredTour[filteredTourItemId].cancelText =
                Drupal.t('End tour');
            }
          });
          this.model.set('tour', filteredTour);
        }
      },
    },
  );

  /**
   * Provides an object that will become the tour item's 'previous' button.
   *
   * Similar to a theme function, themes can override this function to
   * customize
   * the resulting button. Unlike a theme function, it returns an object
   * instead
   * of a string, which is why it is not part of Drupal.theme.
   *
   * @param {Tour} shepherdTour
   *  A class representing a Shepherd site tour.
   *
   * @return {{classes: string, action: string, text: string, secondary:
   *   boolean}} An object structured in the manner Shepherd requires to create
   *   the
   *    'previous' button.
   *
   * @see https://shepherdjs.dev/docs/Tour.html
   * @see \Drupal\tour\TourViewBuilder::viewMultiple()
   * @see https://shepherdjs.dev/docs/Step.html
   */
  Drupal.tour.prevButton = (shepherdTour) => {
    return {
      classes: 'button button--secondary',
      action: shepherdTour.back,
      text: Drupal.t('Previous'),
      secondary: true,
    };
  };

  /**
   * Provides an object that will become the tour item's 'next' button.
   *
   * Similar to a theme function, themes can override this function to customize
   * the resulting button. Unlike a theme function, it returns an object instead
   * of a string, which is why it is not part of Drupal.theme.
   *
   * @param {Tour} shepherdTour
   *  A class representing a Shepherd site tour.
   * @param {Object} tourStepConfig
   *   An object generated in TourViewBuilder used for creating the options
   *   passed to `Tour.addStep(options)`.
   *   Contains the following properties:
   *   - id {string}: The tour tip ID specified by its config
   *   - selector {string|null}: The selector of the element the tour step is
   *     attaching to.
   *   - counter {string}: A string indicating which tour step this is out of
   *     how many total steps.
   *   - attachTo {Object} This is directly mapped to the `attachTo` Step()
   *     option. It has two properties:
   *     - element {string}: The selector of the element the step attaches to.
   *     - on {string}: a PopperJS compatible string to specify step position.
   *   - classes {string}: Will be added to the class attribute of the step.
   *   - body {string}: Markup that is mapped to the `text` Step() option. Will
   *     become the step content.
   *   - title {string}: is mapped to the `title` Step() option.
   *
   * @return {{classes: string, action: string, text: string}}
   *    An object structured in the manner Shepherd requires to create the
   *    'next' button.
   *
   * @see https://shepherdjs.dev/docs/Tour.html
   * @see \Drupal\tour\TourViewBuilder::viewMultiple()
   * @see https://shepherdjs.dev/docs/Step.html
   */
  Drupal.tour.nextButton = (shepherdTour, tourStepConfig) => {
    return {
      classes: 'button button--primary',
      text: tourStepConfig.cancelText
        ? tourStepConfig.cancelText
        : Drupal.t('Next'),
      action: tourStepConfig.cancelText
        ? shepherdTour.cancel
        : shepherdTour.next,
    };
  };

  /**
   * Theme function for tour item content.
   *
   * @param {Object} tourStepConfig
   *   An object generated in TourViewBuilder used for creating the options
   *   passed to `Tour.addStep(options)`.
   *   Contains the following properties:
   *   - id {string}: The tour tip ID specified by its config
   *   - selector {string|null}: The selector of the element the tour step is
   *     attaching to.
   *   - module {string}: The module providing the tip plugin used by this step.
   *   - counter {string}: A string indicating which tour step this is out of
   *     how many total steps.
   *   - attachTo {Object} This is directly mapped to the `attachTo` Step()
   *     option. It has two properties:
   *     - element {string}: The selector of the element the step attaches to.
   *     - on {string}: a PopperJS compatible string to specify step position.
   *   - classes {string}: Will be added to the class attribute of the step.
   *   - body {string}: Markup that is mapped to the `text` Step() option. Will
   *     become the step content.
   *   - title {string}: is mapped to the `title` Step() option.
   *
   * @return {string}
   *   The tour item content markup.
   *
   * @see \Drupal\tour\TourViewBuilder::viewMultiple()
   * @see https://shepherdjs.dev/docs/Step.html
   */
  Drupal.theme.tourItemContent = (tourStepConfig) => {
    let recapLink = '';
    if (tourStepConfig.recapUrl) {
      const linkText =
        drupalSettings.tourRecap?.buttonText || Drupal.t('View steps');
      recapLink = ` | <a href="${tourStepConfig.recapUrl}" target="_blank" class="tour-recap-link">${linkText}</a>`;
    }
    return `${tourStepConfig.body}<div class="tour-progress">${tourStepConfig.counter}${recapLink}</div>`;
  };
})(
  jQuery,
  Backbone,
  Shepherd,
  offsetFloating,
  Drupal,
  drupalSettings,
  document,
  once,
);
