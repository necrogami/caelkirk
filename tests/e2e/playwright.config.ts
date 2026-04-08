import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  timeout: 30000,
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        browserName: 'chromium',
        // When running in WSL2 against Windows Brave, set CDP_ENDPOINT:
        // CDP_ENDPOINT=ws://172.19.64.1:9333/devtools/browser/{id} npx playwright test
        ...(process.env.CDP_ENDPOINT
          ? { cdpUrl: process.env.CDP_ENDPOINT }
          : { launchOptions: { headless: true } }),
      },
    },
  ],
});
