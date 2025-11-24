(function() {
  class SelectParser {
    constructor(options) {
      this.options_index = 0;
      this.parsed = [];
      this.copy_data_attributes = options.copy_data_attributes || false;
    }

    add_node(child) {
      if (child.nodeName.toUpperCase() === "OPTGROUP") {
        this.add_group(child);
      } else {
        this.add_option(child);
      }
    }

    add_group(group) {
      const group_position = this.parsed.length;
      this.parsed.push({
        array_index: group_position,
        group: true,
        label: group.label,
        title: group.title ? group.title : undefined,
        children: 0,
        disabled: group.disabled,
        hidden: group.hidden,
        classes: group.className
      });
      const options = Array.from(group.childNodes);
      options.forEach(option => this.add_option(option, group_position, group.disabled));
    }

    add_option(option, group_position, group_disabled) {
      if (option.nodeName.toUpperCase() === "OPTION") {
        if (option.text !== "") {
          if (group_position != null) {
            this.parsed[group_position].children += 1;
          }
          this.parsed.push({
            options_index: this.options_index,
            value: option.value,
            text: option.text,
            html: option.innerHTML.trim(),
            title: option.title ? option.title : undefined,
            selected: option.selected,
            disabled: group_disabled === true ? group_disabled : option.disabled,
            hidden: option.hidden,
            group_array_index: group_position,
            group_label: group_position != null ? this.parsed[group_position].label : null,
            classes: option.className,
            style: option.style.cssText,
            data: this.parse_data_attributes(option)
          });
        } else {
          this.parsed.push({
            options_index: this.options_index,
            empty: true,
            data: this.parse_data_attributes(option)
          });
        }
        this.options_index += 1;
      }
    }

    parse_data_attributes(option) {
      const dataAttr = {
        'data-option-array-index': this.parsed.length,
        'data-value': option.value
      };
      if (this.copy_data_attributes && option) {
        Array.from(option.attributes).forEach(attr => {
          const attrName = attr.nodeName;
          if (/data-.*/.test(attrName)) {
            dataAttr[attrName] = attr.nodeValue;
          }
        });
      }
      return dataAttr;
    }

    static select_to_array(select, options) {
      const parser = new SelectParser(options);
      const children = Array.from(select.childNodes);
      children.forEach(child => parser.add_node(child));
      return parser.parsed;
    }
  }

  class AbstractChosen {
    constructor(form_field, options1 = {}) {
      this.label_click_handler = this.label_click_handler.bind(this);
      this.form_field = form_field;
      this.options = options1;
      if (!AbstractChosen.browser_is_supported(options1)) {
        return;
      }
      this.is_multiple = this.form_field.multiple;
      this.can_select_by_group = this.form_field.getAttribute('select-by-group') !== null;
      this.set_default_text();
      this.set_default_values();
      this.setup();
      this.set_up_html();
      this.register_observers();
      // instantiation done, fire ready
      this.on_ready();
    }

    set_default_values() {
      this.click_test_action = (evt) => {
        return this.test_active_click(evt);
      };
      this.activate_action = (evt) => {
        return this.activate_field(evt);
      };
      this.active_field = false;
      this.mouse_on_container = false;
      this.results_showing = false;
      this.result_highlighted = null;
      this.is_rtl = this.options.rtl || /\bchosen-rtl\b/.test(this.form_field.className);
      this.allow_single_deselect = (this.options.allow_single_deselect != null) && (this.form_field.options[0] != null) && this.form_field.options[0].text === "" ? this.options.allow_single_deselect : false;
      this.disable_search_threshold = this.options.disable_search_threshold || 0;
      this.disable_search = this.options.disable_search || false;
      this.enable_split_word_search = this.options.enable_split_word_search != null ? this.options.enable_split_word_search : true;
      this.group_search = this.options.group_search != null ? this.options.group_search : true;
      this.search_in_values = this.options.search_in_values || false;
      this.search_contains = this.options.search_contains || false;
      this.single_backstroke_delete = this.options.single_backstroke_delete != null ? this.options.single_backstroke_delete : true;
      this.max_selected_options = this.options.max_selected_options || Number.POSITIVE_INFINITY;
      this.inherit_select_classes = this.options.inherit_select_classes || false;
      this.inherit_option_classes = this.options.inherit_option_classes || false;
      this.display_selected_options = this.options.display_selected_options != null ? this.options.display_selected_options : true;
      this.display_disabled_options = this.options.display_disabled_options != null ? this.options.display_disabled_options : true;
      this.parser_config = this.options.parser_config || {};
      this.include_group_label_in_selected = this.options.include_group_label_in_selected || false;
      this.max_shown_results = this.options.max_shown_results || Number.POSITIVE_INFINITY;
      this.case_sensitive_search = this.options.case_sensitive_search || false;
      this.hide_results_on_select = this.options.hide_results_on_select != null ? this.options.hide_results_on_select : true;
      this.create_option = this.options.create_option || false;
      this.persistent_create_option = this.options.persistent_create_option || false;
      this.skip_no_results = this.options.skip_no_results || false;
    }

    set_default_text() {
      if (this.form_field.getAttribute("data-placeholder")) {
        this.default_text = this.form_field.getAttribute("data-placeholder");
      } else if (this.is_multiple) {
        this.default_text = this.options.placeholder_text_multiple || this.options.placeholder_text || AbstractChosen.default_multiple_text;
      } else {
        this.default_text = this.options.placeholder_text_single || this.options.placeholder_text || AbstractChosen.default_single_text;
      }
      this.default_text = this.escape_html(this.default_text);
      this.results_none_found = this.form_field.getAttribute("data-no_results_text") || this.options.no_results_text || AbstractChosen.default_no_result_text;
      this.create_option_text = this.form_field.getAttribute("data-create_option_text") || this.options.create_option_text || AbstractChosen.default_create_option_text;
    }

    choice_label(item) {
      if (this.include_group_label_in_selected && (item.group_label != null)) {
        return `<b class='group-name'>${this.escape_html(item.group_label)}</b>${item.html}`;
      } else {
        return item.html;
      }
    }

    mouse_enter() {
      this.mouse_on_container = true;
    }

    mouse_leave() {
      this.mouse_on_container = false;
    }

    input_focus(evt) {
      if (this.is_multiple) {
        if (!this.active_field) {
          setTimeout(() => {
            this.container_mousedown();
          }, 50);
        }
      } else {
        if (!this.active_field) {
          this.activate_field();
        }
      }
    }

    input_blur(evt) {
      if (!this.mouse_on_container) {
        this.active_field = false;
        setTimeout(() => {
          this.blur_test();
        }, 100);
      }
    }

    label_click_handler(evt) {
      if (this.is_multiple) {
        this.container_mousedown(evt);
      } else {
        this.activate_field();
      }
    }

    results_option_build(options) {
      let content = '';
      let shown_results = 0;
      for (let data of this.results_data) {
        let data_content = '';
        if (data.group) {
          data_content = this.result_add_group(data);
        } else {
          data_content = this.result_add_option(data);
        }
        if (data_content !== '') {
          shown_results++;
          content += data_content;
        }
        if (options != null ? options.first : undefined) {
          if (data.selected && this.is_multiple) {
            this.choice_build(data);
          } else if (data.selected && !this.is_multiple) {
            this.single_set_selected_text(this.choice_label(data));
          }
        }
        if (shown_results >= this.max_shown_results) {
          break;
        }
      }
      return content;
    }

    result_add_option(option) {
      if (!option.search_match) {
        return '';
      }
      if (!this.include_option_in_results(option)) {
        return '';
      }
      const classes = [];
      if (!option.disabled && !(option.selected && this.is_multiple)) {
        classes.push("active-result");
      }
      if (option.disabled && !(option.selected && this.is_multiple)) {
        classes.push("disabled-result");
      }
      if (option.selected) {
        classes.push("result-selected");
      }
      if (option.group_array_index != null) {
        classes.push("group-option");
      }
      if (option.classes !== "") {
        classes.push(option.classes);
      }
      const option_el = document.createElement("li");
      option_el.className = classes.join(" ");
      if (option.style) {
        option_el.style.cssText = option.style;
      }
      for (let attrName in option.data) {
        if (option.data.hasOwnProperty(attrName)) {
          option_el.setAttribute(attrName, option.data[attrName]);
        }
      }
      option_el.setAttribute("role", "option");
      option_el.innerHTML = option.highlighted_html || option.html;
      option_el.id = `${this.form_field.id}-chosen-search-result-${option.data['data-option-array-index']}`;
      if (option.title) {
        option_el.title = option.title;
      }
      return option_el.outerHTML;
    }

    result_add_group(group) {
      if (!(group.search_match || group.group_match)) {
        return '';
      }
      if (!(group.active_options > 0)) {
        return '';
      }
      const classes = [];
      classes.push("group-result");
      if (group.classes) {
        classes.push(group.classes);
      }
      const group_el = document.createElement("li");
      group_el.className = classes.join(" ");
      group_el.innerHTML = group.highlighted_html || this.escape_html(group.label);
      if (group.title) {
        group_el.title = group.title;
      }
      return group_el.outerHTML;
    }

    append_option(option) {
      this.select_append_option(option);
    }

    results_update_field() {
      this.set_default_text();
      if (!this.is_multiple) {
        this.results_reset_cleanup();
      }
      this.result_clear_highlight();
      this.results_build();
      if (this.results_showing) {
        this.winnow_results();
      }
    }

    reset_single_select_options() {
      for (let result of this.results_data) {
        if (result.selected) {
          result.selected = false;
        }
      }
    }

    results_toggle() {
      if (this.results_showing) {
        this.results_hide();
      } else {
        this.results_show();
      }
    }

    results_search(evt) {
      if (this.results_showing) {
        this.winnow_results();
      } else {
        this.results_show();
      }
      const event = new CustomEvent("chosen:search", { detail: { chosen: this } });
      this.form_field.dispatchEvent(event);
    }

    winnow_results(options) {
      this.no_results_clear();
      let results = 0;
      let exact_result = false;
      let match_value = false;
      const query = this.get_search_text();
      const escaped_query = query.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&");
      const regex = this.get_search_regex(escaped_query);
      const exact_regex = new RegExp(`^${escaped_query}$`);
      const highlight_regex = this.get_highlight_regex(escaped_query);

      for (let option of this.results_data) {
        option.search_match = false;
        let results_group = null;
        let search_match = null;
        option.highlighted_html = '';
        if (this.include_option_in_results(option)) {
          if (option.group) {
            option.group_match = false;
            option.active_options = 0;
          }
          if ((option.group_array_index != null) && this.results_data[option.group_array_index]) {
            results_group = this.results_data[option.group_array_index];
            if (results_group.active_options === 0 && results_group.search_match) {
              results += 1;
            }
            results_group.active_options += 1;
          }
          let text = option.group ? option.label : option.text;
          if (!(option.group && !this.group_search)) {
            search_match = this.search_string_match(text, regex);
            option.search_match = search_match != null;
            if (!option.search_match && this.search_in_values) {
              option.search_match = this.search_string_match(option.value, regex);
              match_value = true;
            }
            if (option.search_match && !option.group) {
              results += 1;
            }
            exact_result = exact_result || exact_regex.test(option.html);
            if (option.search_match) {
              if (query.length && !match_value) {
                let startpos = search_match.index;
                let prefix = text.slice(0, startpos);
                let fix = text.slice(startpos, startpos + query.length);
                let suffix = text.slice(startpos + query.length);
                option.highlighted_html = `${this.escape_html(prefix)}<em>${this.escape_html(fix)}</em>${this.escape_html(suffix)}`;
              }
              if (results_group != null) {
                results_group.group_match = true;
              }
            } else if ((option.group_array_index != null) && this.results_data[option.group_array_index].search_match) {
              option.search_match = true;
            }
          }
        }
      }
      this.result_clear_highlight();
      if (results < 1 && query.length) {
        this.update_results_content("");
        this.fire_search_updated(query);
        if (!(this.create_option && this.skip_no_results)) {
          this.no_results(query);
        }
      } else {
        this.update_results_content(this.results_option_build());
        this.fire_search_updated(query);
        if (!(options != null ? options.skip_highlight : undefined)) {
          this.winnow_results_set_highlight();
        }
      }
      if (this.create_option && (results < 1 || (!exact_result && this.persistent_create_option)) && query.length) {
        this.show_create_option(query);
      }
    }

    get_search_regex(escaped_search_string) {
      let regex_string = this.search_contains ? escaped_search_string : `(^|\\s|\\b)${escaped_search_string}[^\\s]*`;
      if (!(this.enable_split_word_search || this.search_contains)) {
        regex_string = `^${regex_string}`;
      }
      const regex_flag = this.case_sensitive_search ? "" : "i";
      return new RegExp(regex_string, regex_flag);
    }

    get_highlight_regex(escaped_search_string) {
      const regex_anchor = this.search_contains ? "" : "\\b";
      const regex_flag = this.case_sensitive_search ? "" : "i";
      return new RegExp(regex_anchor + escaped_search_string, regex_flag);
    }

    get_list_special_char() {
      const chars = [];
      chars.push({ val: "ae", let: "(ä|æ|ǽ)" });
      chars.push({ val: "oe", let: "(ö|œ)" });
      chars.push({ val: "ue", let: "(ü)" });
      chars.push({ val: "Ae", let: "(Ä)" });
      chars.push({ val: "Ue", let: "(Ü)" });
      chars.push({ val: "Oe", let: "(Ö)" });
      chars.push({ val: "AE", let: "(Æ|Ǽ)" });
      chars.push({ val: "ss", let: "(ß)" });
      chars.push({ val: "IJ", let: "(Ĳ)" });
      chars.push({ val: "ij", let: "(ĳ)" });
      chars.push({ val: "OE", let: "(Œ)" });
      chars.push({ val: "A", let: "(À|Á|Â|Ã|Ä|Å|Ǻ|Ā|Ă|Ą|Ǎ)" });
      chars.push({ val: "a", let: "(à|á|â|ã|å|ǻ|ā|ă|ą|ǎ|ª)" });
      chars.push({ val: "C", let: "(Ç|Ć|Ĉ|Ċ|Č)" });
      chars.push({ val: "c", let: "(ç|ć|ĉ|ċ|č)" });
      chars.push({ val: "D", let: "(Ð|Ď|Đ)" });
      chars.push({ val: "d", let: "(ð|ď|đ)" });
      chars.push({ val: "E", let: "(È|É|Ê|Ë|Ē|Ĕ|Ė|Ę|Ě)" });
      chars.push({ val: "e", let: "(è|é|ê|ë|ē|ĕ|ė|ę|ě)" });
      chars.push({ val: "G", let: "(Ĝ|Ğ|Ġ|Ģ)" });
      chars.push({ val: "g", let: "(ĝ|ğ|ġ|ģ)" });
      chars.push({ val: "H", let: "(Ĥ|Ħ)" });
      chars.push({ val: "h", let: "(ĥ|ħ)" });
      chars.push({ val: "I", let: "(Ì|Í|Î|Ï|Ĩ|Ī|Ĭ|Ǐ|Į|İ)" });
      chars.push({ val: "i", let: "(ì|í|î|ï|ĩ|ī|ĭ|ǐ|į|ı)" });
      chars.push({ val: "J", let: "(Ĵ)" });
      chars.push({ val: "j", let: "(ĵ)" });
      chars.push({ val: "K", let: "(Ķ)" });
      chars.push({ val: "k", let: "(ķ)" });
      chars.push({ val: "L", let: "(Ĺ|Ļ|Ľ|Ŀ|Ł)" });
      chars.push({ val: "l", let: "(ĺ|ļ|ľ|ŀ|ł)" });
      chars.push({ val: "N", let: "(Ñ|Ń|Ņ|Ň)" });
      chars.push({ val: "n", let: "(ñ|ń|ņ|ň|ŉ)" });
      chars.push({ val: "O", let: "(Ò|Ó|Ô|Õ|Ō|Ŏ|Ǒ|Ő|Ơ|Ø|Ǿ)" });
      chars.push({ val: "o", let: "(ò|ó|ô|õ|ō|ŏ|ǒ|ő|ơ|ø|ǿ|º)" });
      chars.push({ val: "R", let: "(Ŕ|Ŗ|Ř)" });
      chars.push({ val: "r", let: "(ŕ|ŗ|ř)" });
      chars.push({ val: "S", let: "(Ś|Ŝ|Ş|Š)" });
      chars.push({ val: "s", let: "(ś|ŝ|ş|š|ſ)" });
      chars.push({ val: "T", let: "(Ţ|Ť|Ŧ)" });
      chars.push({ val: "t", let: "(ţ|ť|ŧ)" });
      chars.push({ val: "U", let: "(Ù|Ú|Û|Ũ|Ū|Ŭ|Ů|Ű|Ų|Ư|Ǔ|Ǖ|Ǘ|Ǚ|Ǜ)" });
      chars.push({ val: "u", let: "(ù|ú|û|ũ|ū|ŭ|ů|ű|ų|ư|ǔ|ǖ|ǘ|ǚ|ǜ)" });
      chars.push({ val: "Y", let: "(Ý|Ÿ|Ŷ)" });
      chars.push({ val: "y", let: "(ý|ÿ|ŷ)" });
      chars.push({ val: "W", let: "(Ŵ)" });
      chars.push({ val: "w", let: "(ŵ)" });
      chars.push({ val: "Z", let: "(Ź|Ż|Ž)" });
      chars.push({ val: "z", let: "(ź|ż|ž)" });
      chars.push({ val: "f", let: "(ƒ)" });
      return chars;
    }

    escape_special_char(str) {
      const specialChars = this.get_list_special_char();
      for (let special of specialChars) {
        str = str.replace(new RegExp(special.let, "g"), special.val);
      }
      return str;
    }

    search_string_match(search_string, regex) {
      let match = regex.exec(search_string);
      if (!this.case_sensitive_search && (match != null)) {
        match = regex.exec(this.escape_special_char(search_string));
      }
      if (!this.search_contains && (match != null ? match[1] : undefined)) {
        match.index += 1;
      }
      return match;
    }

    choices_count() {
      if (this.selected_option_count != null) {
        return this.selected_option_count;
      }
      this.selected_option_count = 0;
      for (let option of this.form_field.options) {
        if (option.selected) {
          this.selected_option_count += 1;
        }
      }
      return this.selected_option_count;
    }

    choices_click(evt) {
      evt.preventDefault();
      this.activate_field();
      if (!(this.results_showing || this.is_disabled)) {
        this.results_show();
      }
    }

    mousedown_checker(evt) {
      evt = evt || window.event;
      let mousedown_type;
      if (!evt.which && evt.button !== undefined) {
        evt.which = (evt.button & 1 ? 1 : (evt.button & 2 ? 3 : (evt.button & 4 ? 2 : 0)));
      }
      switch (evt.which) {
        case 1:
          mousedown_type = 'left';
          break;
        case 2:
          mousedown_type = 'middle';
          break;
        case 3:
          mousedown_type = 'right';
          break;
        default:
          mousedown_type = 'other';
      }
      return mousedown_type;
    }

    keydown_checker(evt) {
      const stroke = evt.which != null ? evt.which : evt.keyCode;
      this.search_field_scale();
      if (stroke !== 8 && this.pending_backstroke) {
        this.clear_backstroke();
      }
      switch (stroke) {
        case 8: // backspace
          this.backstroke_length = this.get_search_field_value().length;
          break;
        case 9: // tab
          if (this.results_showing && !this.is_multiple) {
            this.result_select(evt);
          }
          this.mouse_on_container = false;
          break;
        case 13: // enter
          if (this.results_showing) {
            evt.preventDefault();
          }
          break;
        case 27: // escape
          if (this.results_showing) {
            evt.preventDefault();
          }
          break;
        case 32: // space
          if (this.disable_search) {
            evt.preventDefault();
          }
          break;
        case 38: // up arrow
          evt.preventDefault();
          this.keyup_arrow();
          break;
        case 40: // down arrow
          evt.preventDefault();
          this.keydown_arrow();
          break;
      }
    }

    keyup_checker(evt) {
      const stroke = evt.which != null ? evt.which : evt.keyCode;
      this.search_field_scale();
      switch (stroke) {
        case 8: // backspace
          if (this.is_multiple && this.backstroke_length < 1 && this.choices_count() > 0) {
            this.keydown_backstroke();
          } else if (!this.pending_backstroke) {
            this.result_clear_highlight();
            this.results_search();
          }
          break;
        case 13: // enter
          evt.preventDefault();
          if (this.results_showing) {
            this.result_select(evt);
          }
          break;
        case 27: // escape
          if (this.results_showing) {
            this.results_hide();
          }
          break;
        case 9:
        case 16:
        case 17:
        case 18:
        case 38:
        case 40:
        case 91:
          break;
        default:
          // don't do anything on these keys
          this.results_search();
          break;
      }
    }

    clipboard_event_checker(evt) {
      if (this.is_disabled) {
        return;
      }
      setTimeout(() => {
        this.results_search();
      }, 50);
    }

    container_width() {
      if (this.options.width != null) {
        return this.options.width;
      }
      if (this.form_field.offsetWidth > 0) {
        return `${this.form_field.offsetWidth}px`;
      }
      return "auto";
    }

    include_option_in_results(option) {
      if (this.is_multiple && (!this.display_selected_options && option.selected)) {
        return false;
      }
      if (!this.display_disabled_options && option.disabled) {
        return false;
      }
      if (option.empty) {
        return false;
      }
      if (option.hidden) {
        return false;
      }
      if ((option.group_array_index != null) && this.results_data[option.group_array_index].hidden) {
        return false;
      }
      return true;
    }

    search_results_touchstart(evt) {
      this.touch_started = true;
      this.search_results_mouseover(evt);
    }

    search_results_touchmove(evt) {
      this.touch_started = false;
      this.search_results_mouseout(evt);
    }

    search_results_touchend(evt) {
      if (this.touch_started) {
        this.search_results_mouseup(evt);
      }
    }

    get_single_html() {
      return `<a class="chosen-single chosen-default">
  <span>${this.default_text}</span>
  <div aria-label="Show options"><b aria-hidden="true"></b></div>
</a>
<div class="chosen-drop">
  <div class="chosen-search">
    <input class="chosen-search-input" type="text" autocomplete="off" role="combobox" aria-expanded="false" aria-haspopup="true" aria-autocomplete="list" autocomplete="off" />
  </div>
  <ul class="chosen-results" role="listbox"></ul>
</div>`;
    }

    get_multi_html() {
      return `<ul class="chosen-choices">
  <li class="search-field">
    <input class="chosen-search-input" type="text" autocomplete="off" role="combobox" placeholder="${this.default_text}" aria-expanded="false" aria-haspopup="true" aria-autocomplete="list" />
  </li>
</ul>
<div class="chosen-drop">
  <ul class="chosen-results" role="listbox"></ul>
</div>`;
    }

    get_no_results_html(terms) {
      return `<li class="no-results">
  ${this.results_none_found} <span>${this.escape_html(terms)}</span>
</li>`;
    }

    get_option_html({ value, text }) {
      return `<option value="${value}" selected>${text}</option>`;
    }

    get_create_option_html(terms) {
      return `<li class="create-option active-result" role="option"><a>${this.create_option_text}</a> <span>${this.escape_html(terms)}</span></li>`;
    }

    static browser_is_supported(options) {
      const userAgent = window.navigator.userAgent;

      const isiOS = /iP(od|hone)/i.test(userAgent);
      const isAndroid = /Android.*Mobile/i.test(userAgent);
      const isOtherMobile = /IEMobile/i.test(userAgent) || /Windows Phone/i.test(userAgent) || /BlackBerry/i.test(userAgent) || /BB10/i.test(userAgent);

      if (options && options.allow_mobile) {
        if (isiOS || isAndroid) {
          return true;
        } else if (isOtherMobile) {
          return false;
        }
      }

      if (isiOS || isAndroid || isOtherMobile) {
        return false;
      }

      return true;
    }

    escape_html(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
  }

  // Setting default texts
  AbstractChosen.default_multiple_text = "Select Some Options";
  AbstractChosen.default_single_text = "Select an Option";
  AbstractChosen.default_no_result_text = "No results for:";
  AbstractChosen.default_create_option_text = "Add Option:";
  AbstractChosen.default_remove_item_text = "Remove selection";

  class Chosen extends AbstractChosen {
    setup() {
      this.current_selectedIndex = this.form_field.selectedIndex;
    }

    set_up_html() {
      let container_classes = ["chosen-container"];
      container_classes.push("chosen-container-" + (this.is_multiple ? "multi" : "single"));
      if (this.inherit_select_classes && this.form_field.className) {
        container_classes.push(this.form_field.className);
      }
      if (this.is_rtl) {
        container_classes.push("chosen-rtl");
      }
      const container_props = {
        'class': container_classes.join(' '),
        'title': this.form_field.title
      };
      if (this.form_field.id.length) {
        container_props.id = this.form_field.id.replace(/[^\w]/g, '_') + "_chosen";
      }
      this.container = document.createElement('div');
      for (let prop in container_props) {
        this.container.setAttribute(prop, container_props[prop]);
      }
      this.container.style.width = this.container_width();
      if (this.is_multiple) {
        this.container.innerHTML = this.get_multi_html();
      } else {
        this.container.innerHTML = this.get_single_html();
      }
      this.form_field.style.position = 'absolute';
      this.form_field.style.opacity = 0;
      this.form_field.style.display = 'none';
      this.form_field.parentNode.insertBefore(this.container, this.form_field.nextSibling);
      this.dropdown = this.container.querySelector('div.chosen-drop');
      this.search_field = this.container.querySelector('input');
      this.search_results = this.container.querySelector('ul.chosen-results');
      this.search_results.setAttribute('id', `${this.form_field.id}-chosen-search-results`);
      this.search_field_scale();
      if (this.is_multiple) {
        this.search_choices = this.container.querySelector('ul.chosen-choices');
        this.search_container = this.container.querySelector('li.search-field');
      } else {
        this.search_container = this.container.querySelector('div.chosen-search');
        this.selected_item = this.container.querySelector('.chosen-single');
      }
      this.set_aria_labels();
      this.results_build();
      this.set_tab_index();
      this.set_label_behavior();
    }

    on_ready() {
      const event = new CustomEvent("chosen:ready", { detail: { chosen: this } });
      this.form_field.dispatchEvent(event);
    }

    register_observers() {
      this.container.addEventListener('touchstart', evt => this.container_mousedown(evt));
      this.container.addEventListener('touchend', evt => this.container_mouseup(evt));
      this.container.addEventListener('mousedown', evt => this.container_mousedown(evt));
      this.container.addEventListener('mouseup', evt => this.container_mouseup(evt));
      this.container.addEventListener('mouseenter', evt => this.mouse_enter(evt));
      this.container.addEventListener('mouseleave', evt => this.mouse_leave(evt));

      this.search_results.addEventListener('mouseup', evt => this.search_results_mouseup(evt));
      this.search_results.addEventListener('mouseover', evt => this.search_results_mouseover(evt));
      this.search_results.addEventListener('mouseout', evt => this.search_results_mouseout(evt));
      this.search_results.addEventListener('mousewheel', evt => this.search_results_mousewheel(evt));
      this.search_results.addEventListener('DOMMouseScroll', evt => this.search_results_mousewheel(evt));
      this.search_results.addEventListener('touchstart', evt => this.search_results_touchstart(evt));
      this.search_results.addEventListener('touchmove', evt => this.search_results_touchmove(evt));
      this.search_results.addEventListener('touchend', evt => this.search_results_touchend(evt));

      this.form_field.addEventListener("chosen:updated", evt => this.results_update_field(evt));
      this.form_field.addEventListener("chosen:activate", evt => this.activate_field(evt));
      this.form_field.addEventListener("chosen:open", evt => this.container_mousedown(evt));
      this.form_field.addEventListener("chosen:close", evt => this.close_field(evt));

      this.search_field.addEventListener('blur', evt => this.input_blur(evt));
      this.search_field.addEventListener('keyup', evt => this.keyup_checker(evt));
      this.search_field.addEventListener('keydown', evt => this.keydown_checker(evt));
      this.search_field.addEventListener('focus', evt => this.input_focus(evt));
      this.search_field.addEventListener('cut', evt => this.clipboard_event_checker(evt));
      this.search_field.addEventListener('paste', evt => this.clipboard_event_checker(evt));

      if (this.is_multiple) {
        this.search_choices.addEventListener('click', evt => this.choices_click(evt));
      } else {
        this.container.addEventListener('click', evt => evt.preventDefault());
      }
    }

    destroy() {
      const rootNode = this.container.getRootNode != null ? this.container.getRootNode() : this.container.ownerDocument;
      rootNode.removeEventListener('click', this.click_test_action);
      if (this.form_field_label) {
        this.form_field_label.removeEventListener('click', this.label_click_handler);
      }
      if (this.search_field.tabIndex) {
        this.form_field.tabIndex = this.search_field.tabIndex;
      }
      this.container.parentNode.removeChild(this.container);
      delete this.form_field.__chosen_instance;
      this.form_field.style.display = '';
    }

    set_aria_labels() {
      this.search_field.setAttribute("aria-owns", this.search_results.getAttribute("id"));
      if (this.form_field.getAttribute("aria-label")) {
        this.search_field.setAttribute("aria-label", this.form_field.getAttribute("aria-label"));
        if (this.form_field.getAttribute("aria-labelledby")) {
          this.search_field.setAttribute("aria-labelledby", this.form_field.getAttribute("aria-labelledby"));
        }
      } else if (this.form_field.labels && this.form_field.labels.length) {
        let labelledbyList = "";
        for (let i = 0; i < this.form_field.labels.length; i++) {
          let label = this.form_field.labels[i];
          if (label.id === "") {
            label.id = `${this.form_field.id}-chosen-label-${i}`;
          }
          labelledbyList += this.form_field.labels[i].id + " ";
        }
        this.search_field.setAttribute("aria-labelledby", labelledbyList);
      }
    }

    search_field_disabled() {
      this.is_disabled = this.form_field.disabled || this.form_field.closest('fieldset:disabled');
      if (this.is_disabled) {
        this.container.classList.add('chosen-disabled');
        this.search_field.disabled = true;
        if (!this.is_multiple) {
          this.selected_item.removeEventListener('focus', this.activate_field);
        }
        this.close_field();
      } else {
        this.container.classList.remove('chosen-disabled');
        this.search_field.disabled = false;
        if (!this.is_multiple) {
          this.selected_item.addEventListener('focus', this.activate_field.bind(this));
        }
      }
    }

    container_mousedown(evt) {
      if (this.is_disabled) {
        return;
      }
      if (evt && this.mousedown_checker(evt) === 'left') {
        if (evt && evt.type === "mousedown" && !this.results_showing) {
          evt.preventDefault();
        }
      }
      if (evt && (evt.type === 'mousedown' || evt.type === 'touchstart') && !this.results_showing) {
        evt.preventDefault();
      }
      if (!((evt != null) && evt.target.classList.contains("search-choice-close"))) {
        if (!this.active_field) {
          if (this.is_multiple) {
            this.search_field.value = "";
          }
          const rootNode = this.container.getRootNode != null ? this.container.getRootNode() : this.container.ownerDocument;
          rootNode.addEventListener('click', this.click_test_action);
          this.results_show();
        } else if (!this.is_multiple && evt && (evt.target === this.selected_item || evt.target.closest("a.chosen-single"))) {
          evt.preventDefault();
          this.results_toggle();
        }
        this.activate_field();
      }
    }

    container_mouseup(evt) {
      if (!this.is_disabled && this.allow_single_deselect && evt.target.classList.contains('search-choice-close')) {
        this.results_reset(evt);
      }
    }

    search_results_mousewheel(evt) {
      let delta;
      if (evt.wheelDelta) {
        delta = -evt.wheelDelta;
      } else {
        delta = evt.deltaY || evt.detail;
      }
      if (delta != null) {
        evt.preventDefault();
        if (evt.type === 'DOMMouseScroll') {
          delta = delta * 40;
        }
        this.search_results.scrollTop += delta;
      }
    }

    blur_test(evt) {
      if (!this.active_field && this.container.classList.contains("chosen-container-active")) {
        this.close_field();
      }
    }

    close_field() {
      const rootNode = this.container.getRootNode != null ? this.container.getRootNode() : this.container.ownerDocument;
      rootNode.removeEventListener("click", this.click_test_action);
      this.active_field = false;
      this.results_hide();
      this.search_field.setAttribute("aria-expanded", false);
      this.container.classList.remove("chosen-container-active");
      this.container.classList.remove("chosen-dropup");
      this.clear_backstroke();
      this.show_search_field_default();
      this.search_field_scale();
      this.search_field.dispatchEvent(new Event("blur"));
    }

    should_dropup() {
      const windowHeight = window.innerHeight;
      const dropdownTop = this.container.getBoundingClientRect().top + this.container.offsetHeight - window.pageYOffset;
      const totalHeight = this.dropdown.offsetHeight + dropdownTop;
      if (totalHeight > windowHeight) {
        return true;
      } else {
        return false;
      }
    }

    activate_field() {
      if (this.is_disabled) {
        return;
      }
      this.container.classList.add("chosen-container-active");
      if (this.should_dropup()) {
        this.container.classList.add("chosen-dropup");
      }
      this.active_field = true;
      this.search_field.value = this.search_field.value;
      this.search_results.setAttribute("aria-busy", false);
      this.search_field.focus();
    }

    test_active_click(evt) {
      const active_container = evt.target.closest('.chosen-container');
      if (this.mousedown_checker(evt) === 'left' && active_container && this.container === active_container) {
        this.active_field = true;
      } else {
        this.close_field();
      }
    }

    results_build() {
      this.parsing = true;
      this.selected_option_count = null;
      this.results_data = SelectParser.select_to_array(this.form_field, this.parser_config);
      if (this.is_multiple) {
        const choices = this.search_choices.querySelectorAll("li.search-choice");
        choices.forEach(choice => choice.remove());
      } else {
        this.single_set_selected_text();
        if (this.disable_search || this.form_field.options.length <= this.disable_search_threshold && !this.create_option) {
          this.search_field.readOnly = true;
          this.container.classList.add("chosen-container-single-nosearch");
        } else {
          this.search_field.readOnly = false;
          this.container.classList.remove("chosen-container-single-nosearch");
        }
      }
      this.update_results_content(this.results_option_build({ first: true }));
      this.search_field_disabled();
      this.show_search_field_default();
      this.search_field_scale();
      this.parsing = false;
    }

    update_results_content(content) {
      this.search_results.innerHTML = content;
    }

    fire_search_updated(search_term) {
      const event = new CustomEvent("chosen:search_updated", { detail: { chosen: this, search_term } });
      this.form_field.dispatchEvent(event);
    }

    result_do_highlight(el) {
      if (el) {
        this.result_clear_highlight();
        this.result_highlight = el;
        this.result_highlight.classList.add("highlighted");
        this.search_field.setAttribute("aria-activedescendant", this.result_highlight.getAttribute("id"));
        const maxHeight = parseInt(window.getComputedStyle(this.search_results).maxHeight, 10);
        const visible_top = this.search_results.scrollTop;
        const visible_bottom = maxHeight + visible_top;
        const high_top = this.result_highlight.offsetTop;
        const high_bottom = high_top + this.result_highlight.offsetHeight;
        if (high_bottom >= visible_bottom) {
          this.search_results.scrollTop = high_bottom - maxHeight;
        } else if (high_top < visible_top) {
          this.search_results.scrollTop = high_top;
        }
      }
    }

    result_clear_highlight() {
      if (this.result_highlight) {
        this.result_highlight.classList.remove("highlighted");
      }
      this.result_highlight = null;
    }

    results_show() {
      if (this.is_multiple && this.max_selected_options <= this.choices_count()) {
        const event = new CustomEvent("chosen:maxselected", { detail: { chosen: this } });
        this.form_field.dispatchEvent(event);
        return false;
      }
      if (this.should_dropup()) {
        this.container.classList.add("chosen-dropup");
      }
      this.container.classList.add("chosen-with-drop");
      const chosenSingleDiv = this.container.querySelector(".chosen-single div");
      if (chosenSingleDiv) {
        chosenSingleDiv.setAttribute("aria-label", "Hide options");
      }
      this.results_showing = true;
      this.search_field.setAttribute("aria-expanded", true);
      this.search_field.focus();
      this.search_field.value = this.get_search_field_value();
      this.winnow_results();
      const event = new CustomEvent("chosen:showing_dropdown", { detail: { chosen: this } });
      this.form_field.dispatchEvent(event);
    }

    results_hide() {
      if (this.results_showing) {
        this.result_clear_highlight();
        this.container.classList.remove("chosen-with-drop");
        this.container.classList.remove("chosen-dropup");
        const chosenSingleDiv = this.container.querySelector(".chosen-single div");
        if (chosenSingleDiv) {
          chosenSingleDiv.setAttribute("aria-label", "Show options");
        }
        const event = new CustomEvent("chosen:hiding_dropdown", { detail: { chosen: this } });
        this.form_field.dispatchEvent(event);
      }
      this.search_field.setAttribute("aria-expanded", false);
      this.results_showing = false;
    }

    set_tab_index() {
      if (this.form_field.tabIndex) {
        const ti = this.form_field.tabIndex;
        this.form_field.tabIndex = -1;
        this.search_field.tabIndex = ti;
      }
    }

    set_label_behavior() {
      this.form_field_label = this.form_field.closest("label");
      if (!this.form_field_label && this.form_field.id.length) {
        this.form_field_label = document.querySelector(`label[for='${this.form_field.id}']`);
      }
      if (this.form_field_label) {
        this.form_field_label.addEventListener('click', this.label_click_handler);
      }
    }

    set_search_field_placeholder() {
      if (this.is_multiple && this.choices_count() < 1) {
        this.search_field.setAttribute('placeholder', this.default_text);
      } else {
        this.search_field.setAttribute('placeholder', '');
      }
    }

    show_search_field_default() {
      this.search_field.value = '';
      this.set_search_field_placeholder();
      if (this.is_multiple && this.choices_count() < 1 && !this.active_field) {
        this.search_field.classList.add("default");
      } else {
        this.search_field.classList.remove("default");
      }
    }

    search_results_mouseup(evt) {
      if (this.mousedown_checker(evt) === 'left') {
        let target = evt.target.classList.contains("active-result") || evt.target.classList.contains("group-result") ? evt.target : evt.target.closest(".active-result");
        if (target) {
          this.result_highlight = target;
          this.result_select(evt);
          this.search_field.focus();
        }
      }
    }

    search_results_mouseover(evt) {
      let target = evt.target.classList.contains("active-result") ? evt.target : evt.target.closest(".active-result");
      if (target) {
        this.result_do_highlight(target);
      }
    }

    search_results_mouseout(evt) {
      if (evt.target.classList.contains("active-result") || evt.target.closest('.active-result')) {
        this.result_clear_highlight();
      }
    }

    choice_build(item) {
      const choice = document.createElement('li');
      choice.className = 'search-choice';
      choice.setAttribute('data-value', item.value);
      choice.setAttribute('role', 'option');
      choice.innerHTML = `<span>${this.choice_label(item)}</span>`;
      if (item.disabled) {
        choice.classList.add('search-choice-disabled');
      } else {
        const close_link = document.createElement('button');
        close_link.type = 'button';
        close_link.tabIndex = -1;
        close_link.className = 'search-choice-close';
        close_link.setAttribute('data-option-array-index', item.data['data-option-array-index']);

        const span = document.createElement('span');
        span.className = 'visually-hidden focusable';
        span.textContent = AbstractChosen.default_remove_item_text;
        close_link.appendChild(span);

        close_link.addEventListener('click', evt => this.choice_destroy_link_click(evt));
        choice.appendChild(close_link);
      }
      if (this.inherit_option_classes && item.classes) {
        choice.classList.add(item.classes);
      }
      this.search_container.parentNode.insertBefore(choice, this.search_container);
    }

    choice_destroy_link_click(evt) {
      evt.preventDefault();
      evt.stopPropagation();
      if (!this.is_disabled) {
        this.choice_destroy(evt.target);
      }
    }

    choice_destroy(link) {
      if (this.result_deselect(link.getAttribute("data-option-array-index"))) {
        if (this.active_field) {
          this.search_field.focus();
        } else {
          this.show_search_field_default();
        }
        if (this.is_multiple && this.hide_results_on_select && this.choices_count() > 0 && this.get_search_field_value().length < 1) {
          this.results_hide();
        }
        link.closest('li').remove();
        this.set_search_field_placeholder();
        this.search_field_scale();
      }
    }

    results_reset() {
      this.reset_single_select_options();
      this.form_field.options[0].selected = true;
      this.single_set_selected_text();
      this.show_search_field_default();
      this.results_reset_cleanup();
      this.trigger_form_field_change();
      if (this.active_field) {
        this.results_hide();
      }
    }

    results_reset_cleanup() {
      this.current_selectedIndex = this.form_field.selectedIndex;
      const close_button = this.selected_item.querySelector('.search-choice-close');
      if (close_button) {
        close_button.remove();
      }
    }

    result_select(evt) {
      if (evt.target.classList.contains("group-result")) {
        if (!this.can_select_by_group) {
          return;
        }
        let next = evt.target.nextElementSibling;
        while (next && !next.classList.contains("group-result")) {
          if (next.classList.contains("active-result")) {
            const array_index = next.getAttribute("data-option-array-index");
            let is_chosen = false;
            const choices = this.search_choices.querySelectorAll('.search-choice-close');
            choices.forEach(choice => {
              if (choice.getAttribute("data-option-array-index") === array_index) {
                is_chosen = true;
              }
            });
            if (!is_chosen) {
              this.result_highlight = next;
              evt.target = next;
              evt.selected = true;
              this.result_select(evt);
            }
          }
          next = next.nextElementSibling;
        }
        return;
      }
      if (this.result_highlight) {
        const high = this.result_highlight;
        if (high.classList.contains("create-option")) {
          this.select_create_option(this.search_field.value);
          this.results_hide();
          return;
        }
        this.result_clear_highlight();
        if (this.is_multiple && this.max_selected_options <= this.choices_count()) {
          const event = new CustomEvent("chosen:maxselected", { detail: { chosen: this } });
          this.form_field.dispatchEvent(event);
          return false;
        }
        if (this.is_multiple) {
          high.classList.remove("active-result");
        } else {
          this.reset_single_select_options();
        }
        high.classList.add("result-selected");
        const item = this.results_data[high.getAttribute("data-option-array-index")];
        item.selected = true;
        this.form_field.options[item.options_index].selected = true;
        this.selected_option_count = null;
        if (this.is_multiple) {
          this.choice_build(item);
        } else {
          this.single_set_selected_text(this.choice_label(item));
        }
        if (this.is_multiple && (!this.hide_results_on_select || (evt.metaKey || evt.ctrlKey))) {
          if (evt.metaKey || evt.ctrlKey) {
            this.winnow_results({ skip_highlight: true });
          } else {
            this.search_field.value = "";
            this.winnow_results();
          }
        } else {
          this.results_hide();
          this.show_search_field_default();
        }
        if (this.is_multiple || this.form_field.selectedIndex !== this.current_selectedIndex) {
          this.trigger_form_field_change({ selected: this.form_field.options[item.options_index].value });
        }
        this.current_selectedIndex = this.form_field.selectedIndex;
        evt.preventDefault();
        this.search_field_scale();
      }
    }

    single_set_selected_text(text = this.default_text) {
      if (text === this.default_text) {
        this.selected_item.classList.add("chosen-default");
      } else {
        this.single_deselect_control_build();
        this.selected_item.classList.remove("chosen-default");
      }
      this.selected_item.querySelector("span").innerHTML = text;
    }

    result_deselect(pos) {
      const result_data = this.results_data[pos];
      if (!this.form_field.options[result_data.options_index].disabled) {
        result_data.selected = false;
        this.form_field.options[result_data.options_index].selected = false;
        this.selected_option_count = null;
        this.result_clear_highlight();
        if (this.results_showing) {
          this.winnow_results();
        }
        this.trigger_form_field_change({ deselected: this.form_field.options[result_data.options_index].value });
        this.search_field_scale();
        return true;
      } else {
        return false;
      }
    }

    single_deselect_control_build() {
      if (!this.allow_single_deselect) {
        return;
      }
      if (!this.selected_item.querySelector('.search-choice-close')) {
        const close_button = document.createElement('button');
        close_button.type = 'button';
        close_button.tabIndex = -1;
        close_button.className = 'search-choice-close';
        this.selected_item.querySelector('span').after(close_button);
      }
      this.selected_item.classList.add('chosen-single-with-deselect');
    }

    get_search_field_value() {
      return this.search_field.value;
    }

    get_search_text() {
      return this.get_search_field_value().trim();
    }

    winnow_results_set_highlight() {
      let do_high;
      const selected_results = !this.is_multiple ? this.search_results.querySelector(".result-selected.active-result") : null;
      do_high = selected_results ? selected_results : this.search_results.querySelector(".active-result");
      if (do_high != null) {
        this.result_do_highlight(do_high);
      }
    }

    no_results(terms) {
      const no_results_html = this.get_no_results_html(terms);
      this.search_results.insertAdjacentHTML('beforeend', no_results_html);
      const event = new CustomEvent("chosen:no_results", { detail: { chosen: this } });
      this.form_field.dispatchEvent(event);
    }

    show_create_option(terms) {
      const create_option_html = this.get_create_option_html(terms);
      this.search_results.insertAdjacentHTML('beforeend', create_option_html);
    }

    create_option_clear() {
      const create_option = this.search_results.querySelector(".create-option");
      if (create_option) {
        create_option.remove();
      }
    }

    select_create_option(terms) {
      if (typeof this.create_option === 'function') {
        this.create_option.call(this, terms);
      } else {
        this.select_append_option({ value: terms, text: terms });
      }
    }

    select_append_option(options) {
      const option = this.get_option_html(options);
      this.form_field.insertAdjacentHTML('beforeend', option);
      const event = new Event("chosen:updated");
      this.form_field.dispatchEvent(event);
      const changeEvent = new Event("change");
      this.form_field.dispatchEvent(changeEvent);
      this.search_field.focus();
    }

    no_results_clear() {
      const no_results = this.search_results.querySelector(".no-results");
      if (no_results) {
        no_results.remove();
      }
    }

    keydown_arrow() {
      let next_sib;
      if (this.results_showing && this.result_highlight) {
        next_sib = this.result_highlight.nextElementSibling;
        while (next_sib && !next_sib.classList.contains("active-result")) {
          next_sib = next_sib.nextElementSibling;
        }
        if (next_sib) {
          this.result_do_highlight(next_sib);
        }
      } else if (this.results_showing && this.create_option) {
        const create_option = this.search_results.querySelector('.create-option');
        if (create_option) {
          this.result_do_highlight(create_option);
        }
      } else {
        this.results_show();
      }
    }

    keyup_arrow() {
      let prev_sib;
      if (!this.results_showing && !this.is_multiple) {
        this.results_show();
      } else if (this.result_highlight) {
        prev_sib = this.result_highlight.previousElementSibling;
        while (prev_sib && !prev_sib.classList.contains("active-result")) {
          prev_sib = prev_sib.previousElementSibling;
        }
        if (prev_sib) {
          this.result_do_highlight(prev_sib);
        } else {
          if (this.choices_count() > 0) {
            this.results_hide();
          }
          this.result_clear_highlight();
        }
      }
    }

    keydown_backstroke() {
      if (this.pending_backstroke) {
        this.choice_destroy(this.pending_backstroke.querySelector('.search-choice-close'));
        this.clear_backstroke();
      } else {
        const next_available_destroy = this.search_container.previousElementSibling;
        if (next_available_destroy && !next_available_destroy.classList.contains("search-choice-disabled")) {
          this.pending_backstroke = next_available_destroy;
          if (this.single_backstroke_delete) {
            this.keydown_backstroke();
          } else {
            this.pending_backstroke.classList.add("search-choice-focus");
          }
        }
      }
    }

    clear_backstroke() {
      if (this.pending_backstroke) {
        this.pending_backstroke.classList.remove("search-choice-focus");
      }
      this.pending_backstroke = null;
    }

    search_field_scale() {
      if (!this.is_multiple) {
        return;
      }

      const style_block = {
        position: 'absolute',
        left: '-1000px',
        top: '-1000px',
        whiteSpace: 'pre'
      };

      // Copy relevant styles from the search field
      const styles = ['fontSize', 'fontStyle', 'fontWeight', 'fontFamily', 'lineHeight', 'textTransform', 'letterSpacing'];
      styles.forEach(style => {
        style_block[style] = window.getComputedStyle(this.search_field)[style];
      });

      const div = document.createElement('div');
      Object.assign(div.style, style_block);
      div.textContent = this.get_search_field_value() || this.search_field.getAttribute('placeholder');
      document.body.appendChild(div);

      let width = div.offsetWidth + 25;
      div.remove();

      if (this.container.offsetWidth > 0) {
        width = Math.min(this.container.offsetWidth - 10, width);
      }

      this.search_field.style.width = `${width}px`;
    }

    trigger_form_field_change(extra) {
      const inputEvent = new Event('input');
      const changeEvent = new Event('change', extra);
      this.form_field.dispatchEvent(inputEvent);
      this.form_field.dispatchEvent(changeEvent);
    }
  }

  // Attach chosen method to HTMLElement prototype
  HTMLElement.prototype.chosen = function(options) {
    if (!AbstractChosen.browser_is_supported(options)) {
      return this;
    }
    if (options === 'destroy') {
      if (this.__chosen_instance instanceof Chosen) {
        this.__chosen_instance.destroy();
      }
      return;
    }
    if (!(this.__chosen_instance instanceof Chosen)) {
      this.__chosen_instance = new Chosen(this, options);
    }
  };

}).call(this);
