import type { PdfTemplate } from '@/types';

export const templates: PdfTemplate[] = [
  {
    id: 'standard-planning',
    name: 'Standard Planning Proposal',
    description: 'A clean, professional template for standard planning applications. Includes all essential sections for a complete submission.',
    category: 'Planning Application',
    included: true,
    price: 0,
    accent: 'brand',
  },
  {
    id: 'detailed-design',
    name: 'Detailed Design & Access',
    description: 'Comprehensive design and access statement template with detailed sections covering design principles, access arrangements, and sustainability.',
    category: 'Design Statement',
    included: true,
    price: 0,
    accent: 'success',
  },
  {
    id: 'heritage-statement',
    name: 'Heritage Impact Statement',
    description: 'Specialised template for applications affecting listed buildings and conservation areas. Covers historical context and impact assessment.',
    category: 'Heritage',
    included: true,
    price: 0,
    accent: 'accent',
  },
  {
    id: 'planning-appeal',
    name: 'Planning Appeal Document',
    description: 'Structured template for planning appeals with clear argument sections, supporting evidence framework, and statement of grounds.',
    category: 'Appeal',
    included: true,
    price: 0,
    accent: 'warning',
  },
  {
    id: 'community-infra',
    name: 'Community Infrastructure Levy',
    description: 'Template for CIL-related submissions with calculation worksheets, liability assessment, and relief claim sections.',
    category: 'Infrastructure',
    included: true,
    price: 0,
    accent: 'brand',
  },
  {
    id: 'environmental-impact',
    name: 'Environmental Impact Assessment',
    description: 'Comprehensive EIA template covering screening, scoping, and full environmental statements with all required regulatory sections.',
    category: 'Environmental',
    included: true,
    price: 0,
    accent: 'success',
  },
];

export const getTemplateById = (id: string | null): PdfTemplate | null => {
  if (!id) return null;
  return templates.find((t) => t.id === id) || null;
};
