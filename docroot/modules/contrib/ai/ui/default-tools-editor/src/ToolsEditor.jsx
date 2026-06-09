import { useState, useEffect, useCallback } from "react";
import { yamlToTools, toolsToYaml } from "./yamlSync";
import ToolItem from "./ToolItem";

const EMPTY_TOOL = {
  label: "",
  description: "",
  tool: "",
  parameters: [],
};

const withId = (tool) => tool._id ? tool : { ...tool, _id: crypto.randomUUID() };

export default function ToolsEditor({ textarea }) {
  const [initial] = useState(() => {
    try {
      return {
        tools: yamlToTools(textarea.value).map(withId),
        error: null,
        mode: "interactive",
      };
    } catch (e) {
      console.error(e.message);
      return {
        tools: [],
        error: window.Drupal.t('The saved YAML could not be parsed. Opened in YAML mode so you can review it. Check the console for details.'),
        mode: "yaml",
      };
    }
  });
  const [mode, setMode] = useState(initial.mode);
  const [tools, setTools] = useState(initial.tools);
  const [yamlError, setYamlError] = useState(initial.error);

  const [availableTools, setAvailableTools] = useState([]);

  const syncToTextarea = useCallback(
    (nextTools) => {
      textarea.value = toolsToYaml(nextTools);
      textarea.dispatchEvent(new Event("input", { bubbles: true }));
    },
    [textarea],
  );

  useEffect(() => {
    const raw = window.drupalSettings?.aiTools ?? [];
    const list = Array.isArray(raw) ? raw.filter((t) => t && t.id) : [];
    setAvailableTools(list);
  }, []);

  const updateTools = useCallback(
    (nextTools) => {
      setTools(nextTools);
      syncToTextarea(nextTools);
    },
    [syncToTextarea],
  );

  const handleToolChange =
    (index, updatedTool) => {
      updateTools(tools.map((t, i) => (i === index ? updatedTool : t)));
    };

  const handleRemove = 
    (index) => {
      updateTools(tools.filter((_, i) => i !== index));
    }

  const handleAdd = () => {
    updateTools([...tools, { ...EMPTY_TOOL, parameters: [], _new: true, _id: crypto.randomUUID() }]);
  }

  const handleSwitchMode =
    (newMode) => {
      if (newMode === "interactive") {
        try {
          setTools(yamlToTools(textarea.value).map(withId));
          setYamlError(null);
        } catch (e) {
          console.error(e.message);
          setYamlError(window.Drupal.t('Invalid YAML, cannot switch to interactive mode. Please check the console for details.'));
          return;
        }
      }
      setMode(newMode);
    };

  useEffect(() => {
    textarea.style.display = mode === "yaml" ? "" : "none";
  }, [mode, textarea]);

  return (
    <div className="tools-editor">
      <datalist id="ai-tools-datalist">
        {availableTools.map((t) => (
          <option key={t.id} value={t.id} />
        ))}
      </datalist>
      <div>
        <button
          type="button"
          className={`tools-editor__toggle ${mode === "yaml" ? "tools-editor__toggle--active" : ""}`}
          onClick={() => handleSwitchMode("yaml")}
        >
          YAML
        </button>
        <button
          type="button"
          className={`tools-editor__toggle ${mode === "interactive" ? "tools-editor__toggle--active" : ""}`}
          onClick={() => handleSwitchMode("interactive")}
        >
          {window.Drupal.t('Interactive')}
        </button>
      </div>

      {yamlError && (
        <p className="ai-font-size-s tools-editor__error">{yamlError}</p>
      )}

      {mode === "interactive" && (
        <div>
          {tools.length === 0 && (
            <p>{window.Drupal.t('No tools configured yet. Add one below.')}</p>
          )}
          {tools.map((tool, i) => (
            <ToolItem
              key={tool._id}
              tool={tool}
              availableTools={availableTools}
              defaultOpen={!!tool._new}
              onChange={(updated) => handleToolChange(i, updated)}
              onRemove={() => handleRemove(i)}
            />
          ))}
          <button
            type="button"
            className="tools-editor__add"
            onClick={handleAdd}
          >
            + {window.Drupal.t('Add Tool')}
          </button>
        </div>
      )}
    </div>
  );
}