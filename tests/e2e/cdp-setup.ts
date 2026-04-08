import { test as base, chromium } from '@playwright/test';
import { execSync } from 'child_process';

function getCdpEndpoint(): string {
  if (process.env.CDP_ENDPOINT) return process.env.CDP_ENDPOINT;

  // Auto-launch Chrome if not running
  try {
    execSync('bash ./start-chrome.sh', { cwd: __dirname, timeout: 15000 });
  } catch {
    // May already be running, continue
  }

  // Get the WebSocket URL
  const versionJson = execSync(
    'curl -s --connect-timeout 2 http://localhost:9222/json/version',
    { timeout: 5000 },
  ).toString().trim();

  const parsed = JSON.parse(versionJson);
  return parsed.webSocketDebuggerUrl;
}

export const test = base.extend({
  page: async ({}, use) => {
    const cdpUrl = getCdpEndpoint();
    const browser = await chromium.connectOverCDP(cdpUrl);
    const context = await browser.newContext();
    const page = await context.newPage();

    const baseURL = process.env.BASE_URL || 'http://localhost:8000';
    const originalGoto = page.goto.bind(page);
    page.goto = (url: string, options?: any) => {
      const fullUrl = url.startsWith('/') ? baseURL + url : url;
      return originalGoto(fullUrl, options);
    };

    await use(page);
    await context.close();
  },
});

export { expect } from '@playwright/test';
