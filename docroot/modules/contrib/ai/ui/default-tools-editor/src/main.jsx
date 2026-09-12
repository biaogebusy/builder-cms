import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import ToolsEditor from "./ToolsEditor";

import "./index.css";

const { Drupal, once } = window;

// Track the root mounted for each textarea so it can be torn down again when
// Drupal detaches that part of the page.
const mountedEditors = new WeakMap();

/**
 * Mounts the tools editor on every default information tools textarea.
 *
 * This has to run as a behavior rather than on module evaluation: an AJAX
 * rebuild of the agent form replaces the textarea with a brand new element, and
 * a module body only ever executes once, so a mount done at module scope is
 * lost as soon as the form is rebuilt.
 */
Drupal.behaviors.aiDefaultToolsEditor = {
  attach(context) {
    once(
      "ai-default-tools-editor",
      "[data-default-tools-editor]",
      context,
    ).forEach((textarea) => {
      const mountEl = document.createElement("div");
      mountEl.setAttribute("data-ai-agents-default-tools-editor-root", "true");

      textarea.insertAdjacentElement("beforebegin", mountEl);

      const root = createRoot(mountEl);
      mountedEditors.set(textarea, { root, mountEl });

      root.render(
        <StrictMode>
          <ToolsEditor textarea={textarea} />
        </StrictMode>,
      );
    });
  },

  detach(context, settings, trigger) {
    if (trigger !== "unload") {
      return;
    }

    once
      .remove("ai-default-tools-editor", "[data-default-tools-editor]", context)
      .forEach((textarea) => {
        const mounted = mountedEditors.get(textarea);

        if (!mounted) {
          return;
        }

        mountedEditors.delete(textarea);
        mounted.root.unmount();
        mounted.mountEl.remove();
      });
  },
};
