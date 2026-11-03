// Vite and Vitest configuration for the React TypeScript dashboard.
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
export default defineConfig({ plugins: [react()], test: { environment: 'jsdom' } });
