// Renders typeahead nodes in the editor without duplicated trigger.
export function TypeaheadEditor({ node }) {
  const content = node.getContent()

  return <span>{content}</span>
}

export function TypeaheadMenuItem({ item }) {
  return (
    <div className='typeahead-item'>
      <span className="typeahead-item-title">{item.displayValue}</span>
      <span className="typeahead-item-description">{item.description}</span>
    </div>
  )
}
