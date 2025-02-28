/**
 * @file
 * JQuery plugin to filter out elements based on user input.
 */

(($) => {
  const now = () => new Date().getTime() || Date.now;

  function debounce(func, wait, immediate) {
    let timeout;
    let args;
    let context;
    let timestamp;
    let result;

    const later = () => {
      const last = now() - timestamp;

      if (last < wait && last >= 0) {
        timeout = setTimeout(later, wait - last);
      } else {
        timeout = null;
        if (!immediate) {
          result = func.apply(context, args);
          // eslint-disable-next-line no-multi-assign
          args = context = null;
        }
      }
    };

    // eslint-disable-next-line func-names
    return function () {
      context = this;
      // eslint-disable-next-line prefer-rest-params
      args = arguments;
      timestamp = now();
      const callNow = immediate && !timeout;
      if (!timeout) {
        timeout = setTimeout(later, wait);
      }
      if (callNow) {
        result = func.apply(context, args);
        // eslint-disable-next-line no-multi-assign
        args = context = null;
      }

      return result;
    };
  }

  function explode(string) {
    return string.match(
      /([a-zA-Z]+:(\w+|"[^"]+")*)|\w+|"[^"]+"|[\u0590-\u05FF]+/g,
    );
  }

  function preventEnterKey(e) {
    if (e.which === 13) {
      e.preventDefault();
      e.stopPropagation();
    }
  }

  // eslint-disable-next-line func-names
  const Winnow = function (element, selector, options) {
    const self = this;

    self.element = element;
    self.selector = selector;
    self.text = '';
    self.queries = [];
    self.results = [];
    self.state = {};

    self.options = $.extend(
      {
        delay: 500,
        striping: false,
        selector: '',
        textSelector: null,
        emptyMessage: '',
        rules: [],
        buildIndex: [],
        additionalOperators: {},
      },
      $.fn.winnow.defaults,
      options,
    );
    if (self.options.wrapper === undefined) {
      self.options.wrapper = $(self.selector).parent();
    }

    self.element.wrap('<div class="winnow-input"></div>');
    self.element.on({
      keyup: debounce(() => {
        const value = self.element.val();
        if (!value || explode(value).pop().slice(-1) !== ':') {
          // Only filter if we aren't using the operator autocomplete.
          self.filter();
        }
      }, self.options.delay),
      keydown: preventEnterKey,
    });
    self.element.on({
      search() {
        if (self.element.val() === '') {
          self.clearFilter();
        }
      },
    });

    // Autocomplete operators. When last query is ":", return list of available
    // operators except "text".
    if (typeof self.element.autocomplete === 'function') {
      const operators = Object.keys(self.getOperators());
      const source = [];
      for (let i = 0; i < operators.length; i++) {
        const operator = operators[i];
        if (operator !== 'text') {
          source.push({
            label: operator,
            value: `${operator}:`,
          });
        }
      }

      self.element.autocomplete({
        search(event) {
          if (explode(event.target.value).pop() !== ':') {
            return false;
          }
        },
        source(request, response) {
          return response(source);
        },
        select(event, ui) {
          const terms = explode(event.target.value);
          // Remove the current input.
          terms.pop();
          // Add the selected item.
          terms.push(ui.item.value);
          event.target.value = terms.join(' ');
          // Return false to tell jQuery UI that we've filled in the value
          // already.
          return false;
        },
        focus() {
          return false;
        },
      });
    }

    self.element.data('winnow', self);
  };

  // eslint-disable-next-line func-names
  Winnow.prototype.setQueries = function (string) {
    const self = this;
    const strings = explode(string);

    self.text = string;
    self.queries = [];

    // eslint-disable-next-line no-restricted-syntax
    for (const i in strings) {
      if (strings.hasOwnProperty(i)) {
        const query = { operator: 'text', string: strings[i] };
        const operators = self.getOperators();

        if (query.string.indexOf(':') > 0) {
          const parts = query.string.split(':', 2);
          const operator = parts.shift();
          if (operators[operator] !== undefined) {
            query.operator = operator;
            query.string = parts.shift();
          }
        }

        if (query.string.charAt(0) === '"') {
          // Remove wrapping double quotes.
          query.string = query.string.replace(/^"|"$/g, '');
        }

        query.string = query.string.toLowerCase();

        self.queries.push(query);
      }
    }
  };

  // eslint-disable-next-line func-names
  Winnow.prototype.buildIndex = function () {
    const self = this;
    this.index = [];

    // eslint-disable-next-line func-names
    $(self.selector, self.wrapper).each(function (i) {
      const text = self.options.textSelector
        ? $(self.options.textSelector, this).text()
        : $(this).text();
      let item = {
        key: i,
        element: $(this),
        text: text.toLowerCase(),
      };

      // eslint-disable-next-line guard-for-in,no-restricted-syntax
      for (const j in self.options.buildIndex) {
        item = $.extend(self.options.buildIndex[j].apply(this, [item]), item);
      }

      $(this).data('winnowIndex', i);
      self.index.push(item);
    });

    return self.trigger('finishIndexing', [self]);
  };

  // eslint-disable-next-line func-names
  Winnow.prototype.bind = function () {
    // eslint-disable-next-line prefer-rest-params
    const args = arguments;
    args[0] = `winnow:${args[0]}`;

    // eslint-disable-next-line prefer-spread
    return this.element.bind.apply(this.element, args);
  };

  // eslint-disable-next-line func-names
  Winnow.prototype.trigger = function () {
    // eslint-disable-next-line prefer-rest-params
    const args = arguments;
    args[0] = `winnow:${args[0]}`;
    // eslint-disable-next-line prefer-spread
    return this.element.trigger.apply(this.element, args);
  };

  // eslint-disable-next-line func-names
  Winnow.prototype.filter = function () {
    const self = this;

    self.results = [];
    self.setQueries(self.element.val());

    if (self.index === undefined) {
      self.buildIndex();
    }

    self.trigger('start');

    // eslint-disable-next-line func-names
    $.each(self.index, function (key, item) {
      const $item = item.element;
      let operatorMatch = true;

      let result;
      if (self.text !== '') {
        operatorMatch = false;
        for (let i = 0; i < self.queries.length; i++) {
          const query = self.queries[i];
          const operators = self.getOperators();

          if (operators[query.operator] !== undefined) {
            result = operators[query.operator].apply($item, [
              query.string,
              item,
            ]);
            if (result) {
              operatorMatch = true;
              break;
            }
          }
        }
      }

      if (operatorMatch && self.processRules(item) !== false) {
        // Item is a match.
        $item.show();
        self.results.push(item);
        return true;
      }

      // By reaching here, the $item is not a match, so we hide it.
      $item.hide();
    });

    self.trigger('finish', [self.results]);

    if (self.options.striping) {
      self.stripe();
    }

    if (self.options.emptyMessage) {
      if (self.results.length > 0) {
        self.options.wrapper.children('.winnow-no-results').remove();
      } else if (!self.options.wrapper.children('.winnow-no-results').length) {
        self.options.wrapper.append(
          $('<p class="winnow-no-results"></p>').text(
            self.options.emptyMessage,
          ),
        );
      }
    }
  };

  // eslint-disable-next-line func-names
  Winnow.prototype.getOperators = function () {
    return $.extend(
      {},
      {
        text(string, item) {
          if (item.text.indexOf(string) >= 0) {
            return true;
          }
        },
      },
      this.options.additionalOperators,
    );
  };

  // eslint-disable-next-line func-names
  Winnow.prototype.processRules = function (item) {
    const self = this;
    const $item = item.element;
    let result = true;

    if (self.options.rules.length > 0) {
      // eslint-disable-next-line guard-for-in,no-restricted-syntax
      for (const i in self.options.rules) {
        result = self.options.rules[i].apply($item, [item]);
        if (result === false) {
          break;
        }
      }
    }

    return result;
  };

  // eslint-disable-next-line func-names
  Winnow.prototype.stripe = function () {
    const flip = { even: 'odd', odd: 'even' };
    let stripe = 'odd';

    // eslint-disable-next-line func-names
    $.each(this.index, function (key, item) {
      if (!item.element.is(':visible')) {
        item.element.removeClass('odd even').addClass(stripe);
        stripe = flip[stripe];
      }
    });
  };

  // eslint-disable-next-line func-names
  Winnow.prototype.clearFilter = function () {
    this.element.val('');
    this.filter();
    this.element.focus();
  };

  // eslint-disable-next-line func-names
  $.fn.winnow = function (selector, options) {
    const $input = this.not('.winnow-processed').addClass('winnow-processed');

    // eslint-disable-next-line func-names
    $input.each(function () {
      // eslint-disable-next-line no-new
      new Winnow($input, selector, options);
    });

    return this;
  };

  $.fn.winnow.defaults = {};
})(jQuery);
