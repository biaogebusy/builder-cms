import Editor from "./components/Editor";

const demoContent = `
  # Markdown Editor
  Start typing here...

  ## Features

  - **Bold**, *italic*, and other formatting
  - Lists and checkboxes
  - Code blocks with syntax highlighting
  - Tables
  - Headings
  - Blockquotes
  - Links
  - Tables
  - Undo / redo
  - Diff / Source mode

  Type [ or { to see autocomplete suggestions.
`;
export default function Demo() {
  const variables = [
    {
      name: "tokens",
      trigger: "[",
      values: [
        {
          value: "[current-user:account-name]",
          displayValue: "Current user: Account name",
          description:
            "This variable is required. Prompt validation will check it.",
        },
        {
          value: "[node:body]",
          displayValue: "Node: body",
          description: "",
        },
        {
          value: "[node:title]",
          displayValue: "Node: title",
          description: "",
        },
        {
          value: "[node:url]",
          displayValue: "Node: url",
          description: "",
        },
      ],
    },
    {
      name: "variables",
      trigger: "{{",
      values: [
        {
          value: "{{ placeholder }}",
          displayValue: "Placeholder",
          description: "",
        },
        {
          value: "{{ another-placeholder }}",
          displayValue: "Another placeholder",
          description: "",
        },
        {
          value: "{{ third-placeholder }}",
          displayValue: "Third placeholder",
          description:
            "This variable is required. Prompt validation will check it.",
        },
      ],
    },
  ];

  return <Editor initialValue={demoContent} variables={variables} />;
}
