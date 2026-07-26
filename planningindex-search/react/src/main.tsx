import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App.tsx'
import { SearchProvider } from './context/SearchContext.tsx'
import './index.css'

const rootEl = document.getElementById('pi-search-root')

if (rootEl) {
  createRoot(rootEl).render(
    <StrictMode>
      <SearchProvider>
        <App />
      </SearchProvider>
    </StrictMode>,
  )
} else {
  console.error('[PlanningIndexSearch] Root element #pi-search-root not found.')
}
