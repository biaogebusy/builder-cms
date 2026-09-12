import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import Editor from "./components/Editor";
import Demo from "./Demo";

import "./index.css";

window.Drupal.behaviors.aiMdxEditor = {
  attach: function (context, settings) {
    const textareas = context.querySelectorAll("textarea[data-mdxeditor]");

    if (textareas.length === 0) {
      // No textareas found, check for #mdxeditor-demo element for demo mode
      const rootElement = context.getElementById("mdxeditor-demo");
      if (rootElement) {
        createRoot(rootElement).render(
          <StrictMode>
            <Demo />
          </StrictMode>,
        );
      }
      return;
    }

    textareas.forEach((textarea, index) => {
      if (textarea.getAttribute("data-mdxeditor-initialized") === "true") {
        return;
      }

      textarea.setAttribute("data-mdxeditor-initialized", "true");

      const initialValue = textarea.value || "";
      const mdxeditorId = textarea.getAttribute('data-mdxeditor');

      const wrapper = document.createElement("div");
      wrapper.className = "mdxeditor-wrapper";
      wrapper.id = `mdxeditor-wrapper-${index}`;

      // Insert wrapper before textarea
      textarea.parentNode.insertBefore(wrapper, textarea);

      // Hide the original textarea
      textarea.style.display = "none";

      const handleChange = (markdown) => {
        textarea.value = markdown;

        textarea.dispatchEvent(new Event("input", { bubbles: true }));
        textarea.dispatchEvent(new Event("change", { bubbles: true }));
      };

      const mdxeditorSettings = settings?.mdxeditor?.[mdxeditorId];

      const variables = mdxeditorSettings?.plugins?.typeaheadPlugin?.types || [];

      const editorMethods = { current: null };

      createRoot(wrapper).render(
        <StrictMode>
          <Editor
            initialValue={initialValue}
            onChange={handleChange}
            variables={variables}
            onRef={(ref) => { editorMethods.current = ref; }}
          />
        </StrictMode>,
      );

      textarea.addEventListener("drupal:mdx-fill", function (event) {
        const content = event?.detail?.content;
        if (editorMethods.current && typeof content === "string") {
          editorMethods.current.setMarkdown(content);
          textarea.value = content;
          textarea.dispatchEvent(new Event("input", { bubbles: true }));
          textarea.dispatchEvent(new Event("change", { bubbles: true }));
        }
      });
    });
  }
}
