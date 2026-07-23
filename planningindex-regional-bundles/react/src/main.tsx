import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.tsx';
import './index.css';

createRoot(document.getElementById('pirb-checkout-root')!).render(
  <StrictMode>
    <App />
  </StrictMode>
);
