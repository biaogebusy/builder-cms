import { useState } from "react";

const cloneTool = (source) => ({
  ...source,
  parameters: source.parameters.map((p) => ({ ...p })),
});

export default function ToolItem({ tool, onChange, onRemove, defaultOpen = false, availableTools = [] }) {
  const [draft, setDraft] = useState(null);
  const [open, setOpen] = useState(defaultOpen);

  const current = draft ?? tool;

  const currentToolDef = availableTools.find((t) => t.id === current.tool);
  const availableParams = currentToolDef?.parameters ?? [];

  const updateDraft = (updater) => {
    setDraft((prev) => {
      const base = prev ?? cloneTool(tool);
      return updater(base);
    });
  };

  const update = (field, value) => {
    updateDraft((base) => ({ ...base, [field]: value }));
  };

  const updateParam = (idx, field, value) => {
    updateDraft((base) => ({
      ...base,
      parameters: base.parameters.map((parameter, i) =>
        i === idx ? { ...parameter, [field]: value } : parameter,
      ),
    }));
  };

  const addParam = () => {
    updateDraft((base) => ({
      ...base,
      parameters: [...base.parameters, { paramKey: "", paramValue: "" }],
    }));
  };

  const removeParam = (idx) => {
    updateDraft((base) => ({
      ...base,
      parameters: base.parameters.filter((_, i) => i !== idx),
    }));
  };

  const handleSave = () => {
    if (draft) {
      onChange(draft);
      setDraft(null);
    }
  };

  const handleDiscard = () => {
    setDraft(null);
  };

  return (
    <details
      className="claro-details"
      open={open}
      onToggle={(e) => setOpen(e.currentTarget.open)}
    >
      <summary className="claro-details__summary">
        <span className="tool-item__title">{tool.label || "New Tool"}</span>
        {tool.tool && (
          <span className="ai-pill light">{tool.tool}</span>
        )}
        {draft && (
          <span className="ai-pill warning">{window.Drupal.t('Unsaved')}</span>
        )}
        <button
          type="button"
          className="tool-item__remove ai-icon-button ai-icon--trash"
          onClick={(e) => { e.preventDefault(); onRemove(); }}
        >
          <span className="visually-hidden">{window.Drupal.t('Remove tool')}</span>
        </button>
      </summary>

      <div className="claro-details__wrapper details-wrapper">
      <div className="tool-item__fields">
        <label className="tool-item__field">
          <span className="form-item__label">{window.Drupal.t('Tool')}</span>
          <input
            type="text"
            list="ai-tools-datalist"
            value={current.tool}
            onChange={(e) => update("tool", e.target.value)}
            className="form-element"
            placeholder={window.Drupal.t("Type to search tools…")}
          />
        </label>

        <label className="tool-item__field">
          <span className="form-item__label">{window.Drupal.t('Label')}</span>
          <input
            type="text"
            value={current.label}
            onChange={(e) => update("label", e.target.value)}
            className="form-element"
          />
          <span className="form-item__description">{window.Drupal.t('A short name the LLM uses to identify this tool.')}</span>
        </label>

        <label className="tool-item__field tool-item__field--full">
          <span className="form-item__label">{window.Drupal.t('Expected tool output')}</span>
          <textarea
            value={current.description}
            onChange={(e) => update("description", e.target.value)}
            className="form-element"
            rows={3}
          />
          <span className="form-item__description">{window.Drupal.t('Describe the data the LLM will receive from this tool call.')}</span>
        </label>
      </div>

      <div className="tool-item__params">
        <div className="tool-item__params-header">
          <div className="tool-item__params-heading">
            <span className="form-item__label">{window.Drupal.t('Parameters')}</span>
            <span className="tool-item__token-hint">
              {window.Drupal.t('You can use tokens for parameter values. Comma-separated values are converted to an array.')}
            </span>
          </div>
          <button type="button" className="button button--small" onClick={addParam}>
            + {window.Drupal.t('Add parameter')}
          </button>
        </div>
        {current.parameters.map((param, i) => {
          const matchedParam = availableParams.find((p) => p.name === param.paramKey);
          const valueHelp = matchedParam?.description?.trim() || '';
          return (
          <div key={i} className="tool-item__param-row">
            <label className="tool-item__param-label">
              <span className="form-item__label">{window.Drupal.t('Name')}</span>
              <select
                className="tool-item__param-key form-element"
                value={param.paramKey}
                onChange={(e) => updateParam(i, "paramKey", e.target.value)}
              >
                <option value="">{window.Drupal.t('- Select parameter -')}</option>
                {availableParams.map((p) => (
                  <option key={p.name} value={p.name}>
                    {p.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="tool-item__param-label">
              <span className="form-item__label">{window.Drupal.t('Value')}</span>
              <input
                type="text"
                className="tool-item__param-value form-element"
                value={param.paramValue}
                onChange={(e) => updateParam(i, "paramValue", e.target.value)}
              />
              <span className="form-item__description">{valueHelp}</span>
            </label>
            <button
              type="button"
              className="tool-item__param-remove"
              onClick={() => removeParam(i)}
            >
              &times;
            </button>
          </div>
          );
        })}
      </div>

      {draft && (
        <div>
          <button
            type="button"
            className="button button--small button--primary"
            onClick={handleSave}
          >
            {window.Drupal.t('Save')}
          </button>
          <button
            type="button"
            className="button button--small button--danger"
            onClick={handleDiscard}
          >
            {window.Drupal.t('Discard')}
          </button>
        </div>
      )}
      </div>
    </details>
  );
}