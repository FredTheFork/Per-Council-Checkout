import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.tsx';
import './index.css';

createRoot(document.getElementById('planning-checkout-root')!).render(
  <StrictMode>
    <App />
  </StrictMode>
);
