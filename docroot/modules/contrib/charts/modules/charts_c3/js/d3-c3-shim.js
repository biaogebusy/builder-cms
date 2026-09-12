/**
 * @file
 * D3 v6/v7 compatibility shim for C3.js.
 */

/* global d3 */
(function () {
  // Only add shims if d3 exists and is missing the methods.
  if (typeof d3 !== 'undefined') {
    // Add d3.set if missing.
    if (!d3.set) {
      d3.set = function (values) {
        const set = new Set(values);
        return {
          has(value) {
            return set.has(value);
          },
          add(value) {
            set.add(value);
            return this;
          },
          remove(value) {
            set.delete(value);
            return this;
          },
          clear() {
            set.clear();
            return this;
          },
          size() {
            return set.size;
          },
          empty() {
            return set.size === 0;
          },
          values() {
            return Array.from(set);
          },
        };
      };
    }

    // Add d3.mouse if missing (replaced by d3.pointer in v6+).
    if (!d3.mouse) {
      d3.mouse = function (node) {
        const event = d3.event || window.event;
        if (event) {
          return d3.pointer(event, node);
        }
        return [0, 0];
      };
    }
  }
})();
