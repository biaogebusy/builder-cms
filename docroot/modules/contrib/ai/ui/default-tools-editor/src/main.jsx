import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import ToolsEditor from "./ToolsEditor";

import "./index.css";

const textarea = document.querySelector('[data-default-tools-editor]');

if (textarea) {
  const mountEl = document.createElement("div");
  mountEl.setAttribute("data-ai-agents-default-tools-editor-root", "true");

  textarea.insertAdjacentElement("beforebegin", mountEl);

  createRoot(mountEl).render(
    <StrictMode>
      <ToolsEditor textarea={textarea} />
    </StrictMode>,
  );
}