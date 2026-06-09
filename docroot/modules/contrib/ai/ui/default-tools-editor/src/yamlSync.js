import yaml from "js-yaml";

function uniqueKey(candidate, usedKeys) {
  let key = candidate || "unnamed";
  if (!usedKeys.has(key)) {
    usedKeys.add(key);
    return key;
  }
  let i = 1;
  while (usedKeys.has(`${key}_${i}`)) i++;
  key = `${key}_${i}`;
  usedKeys.add(key);
  return key;
}

function slugify(s) {
  return (s || "").trim().toLowerCase().replace(/\s+/g, "_");
}

export function yamlToTools(yamlStr) {
  if (!yamlStr || !yamlStr.trim()) return [];
  const parsed = yaml.load(yamlStr);
  if (!parsed || typeof parsed !== "object") return [];

  return Object.values(parsed).map((value) => ({
    label: value.label ?? "",
    description: value.description ?? "",
    tool: value.tool ?? "",
    parameters: Object.entries(value.parameters ?? {}).map(([paramKey, v]) => ({
      paramKey,
      paramValue: Array.isArray(v) ? v.join(",") : String(v ?? ""),
    })),
  }));
}

/**
 * Convert the tools array back to a YAML string (keyed object form).
 */
export function toolsToYaml(tools) {
  if (!tools || tools.length === 0) return "";

  const obj = {};
  const usedToolKeys = new Set();
  for (const t of tools) {
    const usedParamKeys = new Set();
    const params = Object.fromEntries(
      (t.parameters ?? []).map((p) => {
        const raw = p.paramValue ?? "";
        const value = raw.includes(",")
          ? raw.split(",").map((s) => s.trim()).filter((s) => s !== "")
          : raw;
        return [uniqueKey(p.paramKey || "param", usedParamKeys), value];
      })
    );

    obj[uniqueKey(slugify(t.label) || "unnamed_tool", usedToolKeys)] = {
      label: t.label || "",
      description: t.description || "",
      tool: t.tool || "",
      ...(Object.keys(params).length > 0 && { parameters: params }),
    };
  }

  return yaml.dump(obj, { lineWidth: -1, quotingType: "'", forceQuotes: true });
}