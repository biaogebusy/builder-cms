import { useEffect, useRef, useState } from "react";
import {
  BlockTypeSelect,
  CreateLink,
  linkPlugin,
  codeMirrorPlugin,
  InsertCodeBlock,
  InsertTable,
  ListsToggle,
  MDXEditor,
  codeBlockPlugin,
  headingsPlugin,
  linkDialogPlugin,
  listsPlugin,
  quotePlugin,
  tablePlugin,
  thematicBreakPlugin,
  diffSourcePlugin,
  DiffSourceToggleWrapper,
  UndoRedo,
  BoldItalicUnderlineToggles,
  toolbarPlugin,
} from "@mdxeditor/editor";
import { typeaheadPlugin } from "@mdxeditor/typeahead-plugin";

import "@mdxeditor/typeahead-plugin/styles.css";
import { TypeaheadEditor } from "../utils/typeahead";
import {
  getTypeToTrigger,
  markdownDirectivesToPlain,
} from "../utils/typeaheadUtils";

import "@mdxeditor/editor/style.css";
import { TypeaheadMenuItem } from "../utils/typeahead";

function getTypeaheadConfigs(variables) {
  const config = variables.map((variable) => {
    return {
      type: variable.name,
      trigger: variable.trigger,
      Editor: TypeaheadEditor,
      searchCallback: async (query) => {
        return variable.values.filter((token) =>
          token.value.toLowerCase().includes(query.toLowerCase()),
        );
      },
      renderMenuItem: (token) => <TypeaheadMenuItem item={token} />,
      convertToId: (token) => token.value,
      maxResults: variable.values.length,
    }
  })

  return config;
}

function Editor({
  initialValue,
  onChange,
  variables = [],
  onRef,
}) {
  const editorRef = useRef(null);
  const [markdown, setMarkdown] = useState(initialValue);
  
  useEffect(() => {
    if (onRef && editorRef.current) {
      onRef(editorRef.current);
    }
  }, [onRef]);

  function handleChange(value) {
    setMarkdown(value);
    if (onChange) {
      onChange(
        markdownDirectivesToPlain(
          value,
          getTypeToTrigger(getTypeaheadConfigs(variables)),
        ),
      );
    }
  }

  return (
    <div
      style={{ display: "flex", flexDirection: "column", gap: 16, padding: 16 }}
    >
      <MDXEditor
        ref={editorRef}
        markdown={markdown}
        onChange={handleChange}
        plugins={[
          headingsPlugin(),
          listsPlugin(),
          quotePlugin(),
          linkPlugin(),
          diffSourcePlugin({
            viewMode: "rich-text",
            diffMarkdown: initialValue,
          }),
          thematicBreakPlugin(),
          linkDialogPlugin(),
          tablePlugin(),
          codeBlockPlugin({ defaultCodeBlockLanguage: "javascript" }),
          codeMirrorPlugin({
            codeBlockLanguages: {
              js: "JavaScript",
              javascript: "JavaScript",
              jsx: "JSX",
              ts: "TypeScript",
              typescript: "TypeScript",
              tsx: "TSX",
              css: "CSS",
              html: "HTML",
              json: "JSON",
              python: "Python",
              py: "Python",
              bash: "Bash",
              sh: "Shell",
              sql: "SQL",
              markdown: "Markdown",
              md: "Markdown",
              "": "Plain Text",
            },
          }),
          toolbarPlugin({
            toolbarContents: () => (
              <DiffSourceToggleWrapper>
                <UndoRedo />
                <BoldItalicUnderlineToggles />
                <BlockTypeSelect />
                <ListsToggle />
                <CreateLink />
                <InsertTable />
                <InsertCodeBlock />
              </DiffSourceToggleWrapper>
            ),
          }),
          variables.length > 0 && typeaheadPlugin({
            configs: getTypeaheadConfigs(variables),
          }),
        ]}
      />
    </div>
  );
}

export default Editor;
