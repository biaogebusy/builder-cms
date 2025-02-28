/**
 * @file:
 * Converts textfield to an Autocomplete Deluxe widget.
 */

(($, Drupal, once, drupalSettings, Sortable) => {
  Drupal.autocomplete_deluxe = Drupal.autocomplete_deluxe || {};

  Drupal.behaviors.autocomplete_deluxe = {
    attach(context) {
      const autocompleteSettings = drupalSettings.autocomplete_deluxe;

      const $elements = $(
        once(
          "attachAutocompleteDeluxe",
          "input.autocomplete-deluxe-form",
          context
        )
      );

      $elements.each(function () {
        if (autocompleteSettings[$(this).attr("id")].multiple === true) {
          new Drupal.autocomplete_deluxe.MultipleWidget(
            this,
            autocompleteSettings[$(this).attr("id")]
          );
        } else {
          new Drupal.autocomplete_deluxe.SingleWidget(
            autocompleteSettings[$(this).attr("id")]
          );
        }
      });
    },
  };

  /**
   * Autogrow plugin which auto resizes the input of the multiple value.
   *
   * http://stackoverflow.com/questions/931207/is-there-a-jquery-autogrow-plugin-for-text-fields
   *
   * @param {Object}
   *   An object.
   * @return {Object}
   *   The auto grow input.
   */
  $.fn.autoGrowInput = function(o) {
    o = $.extend(
      {
        maxWidth: 1000,
        minWidth: 0,
        comfortZone: 70
      },
      o
    );

    this.filter("input:text").each(function() {
      let minWidth = o.minWidth || $(this).width(),
        val = "",
        input = $(this),
        testSubject = $("<tester/>").css({
          position: "absolute",
          top: -9999,
          left: -9999,
          width: "auto",
          fontSize: input.css("fontSize"),
          fontFamily: input.css("fontFamily"),
          fontWeight: input.css("fontWeight"),
          letterSpacing: input.css("letterSpacing"),
          whiteSpace: "nowrap"
        }),
        check = function() {
          if (val === (val = input.val())) {
            return;
          }

          // Enter new content into testSubject.
          const escaped = val
            .replace(/&/g, "&amp;")
            .replace(/\s/g, "&nbsp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
          testSubject.html(escaped);

          // Calculate new width + whether to change.
          const testerWidth = testSubject.width(),
            newWidth =
              testerWidth + o.comfortZone >= minWidth
                ? testerWidth + o.comfortZone
                : minWidth,
            currentWidth = input.width(),
            isValidWidthChange =
              (newWidth < currentWidth && newWidth >= minWidth) ||
              (newWidth > minWidth && newWidth < o.maxWidth);

          // Animate width
          if (isValidWidthChange) {
            input.width(newWidth);
          }
        };

      testSubject.insertAfter(input);

      $(this).bind("keyup keydown blur update", check);
    });

    return this;
  };

  /**
   * If there is no result this label will be shown.
   * @type {{label: string, value: string}}
   */
  Drupal.autocomplete_deluxe.empty = {
    label: "- " + Drupal.t("None") + " -",
    value: ""
  };

  /**
   * EscapeRegex function from jquery autocomplete, is not included in Drupal.
   */
  Drupal.autocomplete_deluxe.escapeRegex = function(value) {
    return value.replace(/[-[\]{}()*+?.,\\^$|#\s]/gi, "\\$&");
  };

  /**
   * Filter function from jquery autocomplete, is not included in Drupal.
   */
  Drupal.autocomplete_deluxe.filter = function(array, term) {
    const matcher = new RegExp(
      Drupal.autocomplete_deluxe.escapeRegex(term),
      "i"
    );
    return $.grep(array, function(value) {
      return matcher.test(value.label || value.value || value);
    });
  };

  Drupal.autocomplete_deluxe.Widget = function() {};

  /**
   * Url for the callback.
   */
  Drupal.autocomplete_deluxe.Widget.prototype.uri = null;

  /**
   * Allows widgets to filter terms.
   * @param term
   *   A term that should be accepted or not.
   * @return {Boolean}
   *   true if the term should be accepted.
   */
  Drupal.autocomplete_deluxe.Widget.prototype.acceptTerm = function(term) {
    return true;
  };

  Drupal.autocomplete_deluxe.Widget.prototype.init = function(settings) {
    if (navigator.appVersion.indexOf("MSIE 6.") !== -1) {
      return;
    }

    this.id = settings.input_id;
    this.jqObject = $("#" + this.id);

    this.uri = settings.uri;
    this.multiple = settings.multiple;
    this.required = settings.required;
    this.limit = settings.limit;
    this.synonyms =
      typeof settings.use_synonyms === "undefined"
        ? false
        : settings.use_synonyms;
    this.not_found_message =
      typeof settings.use_synonyms === "undefined"
        ? Drupal.t("The entity '@term' will be added.")
        : settings.not_found_message;
    this.not_found_message_allow =
      typeof settings.not_found_message_allow === "undefined"
        ? false
        : settings.not_found_message_allow;
    this.new_terms =
      typeof settings.new_terms === "undefined" ? false : settings.new_terms;
    this.no_empty_message =
      typeof settings.no_empty_message === "undefined"
        ? Drupal.t(
            "No terms could be found. Please type in order to add a new term."
          )
        : settings.no_empty_message;

    this.wrapper = '""';

    if (typeof settings.delimiter === "undefined") {
      this.delimiter = true;
    } else {
      this.delimiter = settings.delimiter.charCodeAt(0);
    }

    this.items = {};

    const self = this;
    let parent = this.jqObject.parent();
    const parents_parent = this.jqObject.parent().parent();

    parents_parent.append(this.jqObject);
    parent.remove();
    parent = parents_parent;

    const generateValues = function(data, term) {
      const result = new Array();
      for (const terms in data) {
        if (self.acceptTerm(terms)) {
          result.push({
            label: data[terms],
            value: terms
          });
        }
      }

      // If there are no results and new terms OR not found message can be
      // displayed, push the result, so the menu can be shown.
      if (
        $.isEmptyObject(result) &&
        (self.new_terms || self.not_found_message_allow)
      ) {
        if (term !== " ") {
          result.push({
            label: Drupal.formatString(self.not_found_message, {
              "@term": term
            }),
            value: term,
            newTerm: true
          });
        } else {
          result.push({
            label: self.no_empty_message,
            noTerms: true
          });
        }
      }
      return result;
    };

    const cache = {};
    let lastXhr = null;

    this.source = function(request, response) {
      let { term } = request;
      if (term in cache) {
        response(generateValues(cache[term], term));
        return;
      }

      // Some server collapse two slashes if the term is empty, so insert at
      // least a whitespace. This whitespace will later on be trimmed in the
      // autocomplete callback.
      if (!term) {
        term = " ";
      }
      request.synonyms = self.synonyms;
      const url = Drupal.url(settings.uri + "?q=" + term);
      lastXhr = $.getJSON(url, request, function(data, status, xhr) {
        cache[term] = data;
        if (xhr === lastXhr) {
          // Filter already selected items from the response data.
          if (typeof self.valueForm !== 'undefined') {
            var currentValues = self.valueForm.val().split('"');
            const dataArray = Object.entries(data);
            const dataFiltered = dataArray.filter(([key, value]) => {
              return ! currentValues.includes(value);
            });
            data = Object.fromEntries(dataFiltered);
          }

          response(generateValues(data, term));
        }
      });
    };

    this.jqObject.autocomplete({
      source: this.source,
      minLength: settings.min_length
    });

    const { jqObject } = this;

    const autocompleteDataKey =
      typeof this.jqObject.data("autocomplete") === "object"
        ? "item.autocomplete"
        : "ui-autocomplete";

    const throbber = $(
      '<div class="autocomplete-deluxe-throbber autocomplete-deluxe-closed">&nbsp;</div>'
    ).insertAfter(jqObject);

    this.jqObject.bind("autocompletesearch", function(event, ui) {
      throbber.removeClass("autocomplete-deluxe-closed");
      throbber.addClass("autocomplete-deluxe-open");
    });

    this.jqObject.bind("autocompleteresponse", function(event, ui) {
      throbber.addClass("autocomplete-deluxe-closed");
      throbber.removeClass("autocomplete-deluxe-open");
      // If no results found, show a message and prevent selecting it as a tag.
      if (
        !drupalSettings.autocomplete_deluxe[this.id].new_terms &&
        typeof ui.item !== "undefined" &&
        ui.item.newTerm
      ) {
        const uiWidgetContent = $(".ui-widget-content");
        uiWidgetContent.css("pointer-events", "");
        if (!ui.content.length) {
          ui.content[0] = {
            label: Drupal.t("No results found"),
            value: ""
          };
          uiWidgetContent.css("pointer-events", "none");
        }
      }
    });

    // Monkey patch the _renderItem function jquery so we can highlight the
    // text, that we already entered.
    $.ui.autocomplete.prototype._renderItem = function(ul, item) {
      let t = item.label;
      if (this.term !== "") {
        const escapedValue = Drupal.autocomplete_deluxe.escapeRegex(this.term);
        const re = new RegExp(
          '()*""' + escapedValue + '""|' + escapedValue + "()*",
          "gi"
        );
        let t = item.label.replace(
          re,
          "<span class='autocomplete-deluxe-highlight-char'>$&</span>"
        );
      }

      return $("<li></li>")
        .data(autocompleteDataKey, item)
        .append("<a>" + t + "</a>")
        .appendTo(ul);
    };
  };

  Drupal.autocomplete_deluxe.Widget.prototype.generateValues = function(data) {
    const result = new Array();
    for (const index in data) {
      result.push(data[index]);
    }
    return result;
  };

  /**
   * Generates a single selecting widget.
   */
  Drupal.autocomplete_deluxe.SingleWidget = function(settings) {
    this.init(settings);
    this.setup();
    this.jqObject.addClass("autocomplete-deluxe-form-single");
  };

  Drupal.autocomplete_deluxe.SingleWidget.prototype = new Drupal.autocomplete_deluxe.Widget();

  Drupal.autocomplete_deluxe.SingleWidget.prototype.setup = function() {
    const { jqObject } = this;
    const parent = jqObject.parent();

    parent.mousedown(function() {
      if (parent.hasClass("autocomplete-deluxe-single-open")) {
        jqObject.autocomplete("close");
      } else {
        jqObject.autocomplete("search", "");
      }
    });
  };

  /**
   * Creates a multiple selecting widget.
   */
  Drupal.autocomplete_deluxe.MultipleWidget = function(input, settings) {
    this.init(settings);
    this.setup();
  };

  Drupal.autocomplete_deluxe.MultipleWidget.prototype = new Drupal.autocomplete_deluxe.Widget();
  Drupal.autocomplete_deluxe.MultipleWidget.prototype.items = new Object();

  Drupal.autocomplete_deluxe.MultipleWidget.prototype.acceptTerm = function(
    term
  ) {
    // Accept only terms, that are not in our items list.
    return !(term in this.items);
  };

  Drupal.autocomplete_deluxe.MultipleWidget.Item = function(widget, item) {
    if (item.newTerm === true) {
      item.label = item.value;
    } else if (item.noTerms === true) {
      return;
    }

    this.value = item.value;
    this.element = $(
      '<span class="autocomplete-deluxe-item">' + item.label + "</span>"
    );
    this.widget = widget;
    this.item = item;
    const self = this;

    const close = $(
      '<a class="autocomplete-deluxe-item-delete" href="javascript:void(0)"></a>'
    ).appendTo(this.element);
    // Use single quotes because of the double quote encoded stuff.
    // .. then to make this work for single quotes in names, like O'Brian, enocde '.
    const encodedVal = this.value.replace("'", "&#039;");
    const input = $(
      '<input type="hidden" value=\'' + encodedVal + "'/>"
    ).appendTo(this.element);

    close.mousedown(function() {
      self.remove(item);
      const value_input = self.widget.jqObject
        .parents(".autocomplete-deluxe-container")
        .next()
        .find("input");
      value_input.trigger("change");
    });
  };

  Drupal.autocomplete_deluxe.MultipleWidget.Item.prototype.remove = function() {
    this.element.remove();
    const values = this.widget.valueForm.val();
    const escapedValue = Drupal.autocomplete_deluxe.escapeRegex(
      this.item.value
    );
    const regex = new RegExp('()*""' + escapedValue + '""()*', "gi");
    this.widget.valueForm.val(values.replace(regex, ""));
    delete this.widget.items[this.value];
  };

  Drupal.autocomplete_deluxe.MultipleWidget.prototype.setup = function() {
    const { jqObject } = this;
    const parent = jqObject.parents(".autocomplete-deluxe-container");
    const value_container = parent.next();
    const value_input = value_container.find("input");
    const { items } = this;
    const self = this;
    this.valueForm = value_input;

    // Order values based on the UI. Usually called after a manual sort.
    this.orderValues = function() {
      const items = [];
      parent
        .find(".autocomplete-deluxe-item input")
        .each(function(index, value) {
          items[index] = $(value).val();
        });

      value_input.val('""' + items.join('"" ""') + '""');
      value_input.trigger("change");
    };

    Sortable.create(parent.get(0), {
      draggable: ".autocomplete-deluxe-item",
      onEnd: function onEnd() {
        self.orderValues();
      }
    });

    // Override the resize function, so that the suggestion list doesn't resizes
    // all the time.
    const autocompleteDataKey =
      typeof this.jqObject.data("autocomplete") === "object"
        ? "autocomplete"
        : "ui-autocomplete";

    jqObject.data(autocompleteDataKey)._resizeMenu = function() {};

    jqObject.show();

    value_input.hide();

    // Add the default values to the box.
    let defaultValues = value_input.val();
    defaultValues = $.trim(defaultValues);
    defaultValues = defaultValues.substr(2, defaultValues.length - 4);
    defaultValues = defaultValues.split(/"" +""/);

    for (const index in defaultValues) {
      const value = defaultValues[index];
      if (value !== "") {
        // If a terms is encoded in double quotes, then the label should have
        // no double quotes.
        const label =
          value.match(/["][\w|\s|\D|]*["]/gi) !== null
            ? value.substr(1, value.length - 2)
            : value;
        let itemInit = {
          label: Drupal.checkPlain(label),
          value: value
        };
        let item = new Drupal.autocomplete_deluxe.MultipleWidget.Item(
          self,
          itemInit
        );
        item.element.insertBefore(jqObject);
        items[item.value] = item;
      }
    }

    jqObject.addClass("autocomplete-deluxe-multiple");
    parent.addClass("autocomplete-deluxe-multiple");

    // Adds a value to the list.
    this.addValue = function(ui_item) {
      const item = new Drupal.autocomplete_deluxe.MultipleWidget.Item(
        self,
        ui_item
      );
      item.element.insertBefore(jqObject);
      items[ui_item.value] = item;
      const new_value = " " + self.wrapper + ui_item.value + self.wrapper;
      const values = value_input.val();
      value_input.val(values + new_value);
      jqObject.val("");
    };

    parent.mouseup(function() {
      jqObject.autocomplete("search", "");
      jqObject.focus();
    });

    jqObject.bind("autocompleteselect", function(event, ui) {
      const allow_new_terms =
        drupalSettings.autocomplete_deluxe[this.id].new_terms;
      // If new terms are not allowed to be added as per the field widget
      // settings, do not continue to process and add that value.
      if (!allow_new_terms && ui.item.newTerm) {
        $(this).val("");
        return;
      }
      self.addValue(ui.item);
      jqObject.width(25);
      // Return false to prevent setting the last term as value for the jqObject.
      return false;
    });

    jqObject.bind("autocompletechange", function(event, ui) {
      jqObject.val("");
    });

    jqObject.blur(function() {
      const lastElement = jqObject
        .parent()
        .children(".autocomplete-deluxe-item")
        .last();
      lastElement.removeClass("autocomplete-deluxe-item-focus");
    });

    let clear = false;

    jqObject.keypress(function(event) {
      let value = jqObject.val();
      // If a comma was entered and there is none or more then one comma, or the
      // enter key was entered, then enter the new term.
      if (
        (event.which === self.delimiter && value.split('"').length - 1 !== 1) ||
        (event.which === 13 && jqObject.val() !== "")
      ) {
        const allow_new_terms =
          drupalSettings.autocomplete_deluxe[this.id].new_terms;
        // If new terms are not allowed to be added as per the field widget
        // settings, do not continue to process and add that value.
        if (!allow_new_terms) {
          $(this).val("");
          return;
        }

        value = value.substr(0, value.length);
        if (typeof self.items[value] === "undefined" && value !== "") {
          const ui_item = {
            label: value,
            value: value
          };
          self.addValue(ui_item);
        }
        clear = true;
        if (event.which === 13) {
          return false;
        }
      }

      // If the Backspace key was hit and the input is empty.
      if (event.which === 8 && value === "") {
        const lastElement = jqObject
          .parent()
          .children(".autocomplete-deluxe-item")
          .last();
        // then mark the last item for deletion or deleted it if already marked.
        if (lastElement.hasClass("autocomplete-deluxe-item-focus")) {
          let value = lastElement.children("input").val();
          self.items[value].remove(self.items[value]);
          jqObject.autocomplete("search", "");
        } else {
          lastElement.addClass("autocomplete-deluxe-item-focus");
        }
      } else {
        // Remove the focus class if any other key was hit.
        const lastElement = jqObject
          .parent()
          .children(".autocomplete-deluxe-item")
          .last();
        lastElement.removeClass("autocomplete-deluxe-item-focus");
      }
    });

    jqObject.autoGrowInput({
      comfortZone: 50,
      minWidth: 10,
      maxWidth: 460
    });

    jqObject.keyup(function() {
      if (clear) {
        // Trigger the search, so it display the values for an empty string.
        jqObject.autocomplete("search", "");
        jqObject.val("");
        clear = false;
        // Return false to prevent entering the last character.
        return false;
      }
    });
  };
})(jQuery, Drupal, once, drupalSettings, Sortable);
