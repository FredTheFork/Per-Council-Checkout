import { useState } from 'react';
import { FileText, Maximize2, X, ZoomIn, ZoomOut, ChevronLeft, ChevronRight } from 'lucide-react';
import type { PdfTemplate } from '@/types';

interface PdfPreviewProps {
  template: PdfTemplate | null;
}

const accentClasses: Record<string, { bg: string; text: string; ring: string; badge: string }> = {
  brand: { bg: 'bg-brand-50', text: 'text-brand-600', ring: 'ring-brand-500', badge: 'bg-brand-100 text-brand-700' },
  success: { bg: 'bg-success-50', text: 'text-success-600', ring: 'ring-success-500', badge: 'bg-success-100 text-success-700' },
  accent: { bg: 'bg-accent-50', text: 'text-accent-600', ring: 'ring-accent-500', badge: 'bg-accent-100 text-accent-700' },
  warning: { bg: 'bg-warning-50', text: 'text-warning-600', ring: 'ring-warning-500', badge: 'bg-warning-100 text-warning-700' },
};

export function PdfPreview({ template }: PdfPreviewProps) {
  const [zoom, setZoom] = useState(100);
  const [fullscreen, setFullscreen] = useState(false);

  if (!template) {
    return (
      <div className="flex h-full min-h-[400px] items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50">
        <div className="text-center">
          <FileText className="mx-auto mb-3 h-10 w-10 text-slate-300" />
          <p className="text-sm text-slate-400">Select a template to preview</p>
        </div>
      </div>
    );
  }

  const accent = accentClasses[template.accent] || accentClasses.brand;
  const hasPreview = template.previewUrl !== '';

  return (
    <>
      <div className="flex flex-col overflow-hidden rounded-2xl border-2 border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3">
          <div className="flex items-center gap-2">
            <FileText className={`h-4 w-4 ${accent.text}`} />
            <span className="text-sm font-semibold text-slate-700">{template.name}</span>
          </div>
          <div className="flex items-center gap-1">
            <button
              onClick={() => setZoom((z) => Math.max(50, z - 25))}
              className="rounded-md p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-600"
              aria-label="Zoom out"
            >
              <ZoomOut className="h-4 w-4" />
            </button>
            <span className="w-12 text-center text-xs font-medium text-slate-500">{zoom}%</span>
            <button
              onClick={() => setZoom((z) => Math.min(200, z + 25))}
              className="rounded-md p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-600"
              aria-label="Zoom in"
            >
              <ZoomIn className="h-4 w-4" />
            </button>
            <div className="mx-1 h-4 w-px bg-slate-200" />
            <button
              onClick={() => setFullscreen(true)}
              className="rounded-md p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-600"
              aria-label="Open fullscreen"
            >
              <Maximize2 className="h-4 w-4" />
            </button>
          </div>
        </div>

        <div className="relative flex min-h-[400px] items-center justify-center overflow-auto bg-slate-100 p-4">
          {hasPreview ? (
            <iframe
              src={`${template.previewUrl}#toolbar=0&navpanes=0&view=FitH`}
              title={`${template.name} preview`}
              className="rounded-lg bg-white shadow-lg transition-all"
              style={{
                width: `${zoom}%`,
                height: '100%',
                minHeight: '400px',
                border: 'none',
              }}
            />
          ) : (
            <div className="flex h-full min-h-[400px] w-full max-w-md flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-white py-16">
              <div className={`mb-4 flex h-16 w-16 items-center justify-center rounded-2xl ${accent.bg}`}>
                <FileText className={`h-8 w-8 ${accent.text}`} />
              </div>
              <p className="text-sm font-semibold text-slate-600">{template.name}</p>
              <p className="mt-1 text-xs text-slate-400">PDF preview will appear here</p>
              <div className="mt-4 rounded-md bg-slate-100 px-3 py-1.5">
                <span className="text-xs font-medium text-slate-400">Preview not yet available</span>
              </div>
            </div>
          )}
        </div>

        <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-4 py-2.5">
          <span className={`badge ${accent.badge}`}>{template.category}</span>
          <span className="text-xs text-slate-400">
            {template.included ? 'Included with subscription' : `+£${template.price.toFixed(2)}`}
          </span>
        </div>
      </div>

      {fullscreen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 backdrop-blur-sm" onClick={() => setFullscreen(false)}>
          <div className="relative flex h-full w-full max-w-5xl flex-col" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between bg-white px-4 py-3 shadow-sm">
              <div className="flex items-center gap-2">
                <FileText className={`h-5 w-5 ${accent.text}`} />
                <span className="font-display text-lg font-bold text-slate-900">{template.name}</span>
              </div>
              <button
                onClick={() => setFullscreen(false)}
                className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                aria-label="Close fullscreen"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="flex-1 overflow-auto bg-slate-200 p-6">
              {hasPreview ? (
                <iframe
                  src={`${template.previewUrl}#toolbar=1&navpanes=1&view=FitH`}
                  title={`${template.name} fullscreen preview`}
                  className="mx-auto rounded-lg bg-white shadow-2xl"
                  style={{ width: '90%', height: '100%', minHeight: '70vh', border: 'none' }}
                />
              ) : (
                <div className="flex h-full min-h-[60vh] flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-400 bg-white">
                  <div className={`mb-4 flex h-20 w-20 items-center justify-center rounded-2xl ${accent.bg}`}>
                    <FileText className={`h-10 w-10 ${accent.text}`} />
                  </div>
                  <p className="text-base font-semibold text-slate-600">{template.name}</p>
                  <p className="mt-1 text-sm text-slate-400">PDF preview will appear here</p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </>
  );
}

interface TemplateThumbnailsProps {
  templates: PdfTemplate[];
  selectedId: string | null;
  onSelect: (id: string) => void;
}

export function TemplateThumbnails({ templates, selectedId, onSelect }: TemplateThumbnailsProps) {
  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
      {templates.map((template) => {
        const isSelected = selectedId === template.id;
        const accent = accentClasses[template.accent] || accentClasses.brand;
        const hasThumb = template.thumbnailUrl !== '';

        return (
          <button
            key={template.id}
            onClick={() => onSelect(template.id)}
            className={`group relative flex flex-col overflow-hidden rounded-xl border-2 bg-white text-left transition-all duration-200 ${
              isSelected
                ? `border-transparent ring-2 ${accent.ring}`
                : 'border-slate-200 hover:border-slate-300 hover:shadow-sm'
            }`}
          >
            <div className={`relative flex h-24 items-center justify-center ${accent.bg}`}>
              {hasThumb ? (
                <img
                  src={template.thumbnailUrl}
                  alt={template.name}
                  className="h-full w-full object-cover object-top"
                />
              ) : (
                <FileText className={`h-8 w-8 ${accent.text} opacity-60`} />
              )}
              {isSelected && (
                <div className="absolute right-1.5 top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-brand-600 text-white shadow-sm">
                  <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
              )}
            </div>
            <div className="p-2">
              <p className="truncate text-xs font-semibold text-slate-700">{template.name}</p>
              <p className="truncate text-[10px] text-slate-400">{template.category}</p>
            </div>
          </button>
        );
      })}
    </div>
  );
}
