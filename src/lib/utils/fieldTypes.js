export const FIELD_TYPES = [
  { type: 'text',      label: 'Text',        icon: 'T' },
  { type: 'textarea',  label: 'Textarea',    icon: '¶' },
  { type: 'richtext',  label: 'Rich Text',   icon: 'R' },
  { type: 'number',    label: 'Number',      icon: '#' },
  { type: 'toggle',    label: 'Toggle',      icon: '◎' },
  { type: 'date',      label: 'Date',        icon: '📅', hideIcon: true },
  { type: 'select',    label: 'Select',      icon: '▾' },
  { type: 'checkbox',  label: 'Checkbox',    icon: '☑' },
  { type: 'media',     label: 'Media',       icon: '🖼', hideIcon: true },
  { type: 'relation',  label: 'Relation',    icon: '↔' },
  { type: 'color',     label: 'Color',       icon: '🎨', hideIcon: true },
  { type: 'code',      label: 'Code',        icon: '</>' },
  { type: 'repeater',  label: 'Repeater',    icon: '⊞' },
  { type: 'flexible',  label: 'Flexible',    icon: '⊟' },
];

export function fieldTypeLabel(type) {
  return FIELD_TYPES.find(f => f.type === type)?.label ?? type;
}

export function defaultFieldOptions(type) {
  switch (type) {
    case 'select':
    case 'checkbox': return { choices: [] };
    case 'relation': return { collection: '', multiple: false };
    case 'number':   return { min: null, max: null, step: null };
    case 'code':     return { language: 'html' };
    case 'repeater': return { fields: [] };
    default:         return {};
  }
}
