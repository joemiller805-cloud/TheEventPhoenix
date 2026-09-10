import { test as base, expect } from '@playwright/test';
import { shouldIgnoreConsoleError } from './shared/e2e-monitor';

export { expect };

export const test = base.extend({
  monitorBrowserErrors: [
    async ({ page }, use, testInfo) => {
      const browserIssues: string[] = [];

      page.on('console', (msg) => {
        if (msg.type() !== 'error') return;
        if (shouldIgnoreConsoleError(msg.text())) return;
        browserIssues.push(`[console.error] ${msg.text()}`);
      });

      page.on('pageerror', (error) => {
        browserIssues.push(`[pageerror] ${error.message}`);
      });

      await use(undefined);

      if (!browserIssues.length) return;

      const report = browserIssues.join('\n');
      console.warn(`Browser issues in "${testInfo.title}":\n${report}`);
      await testInfo.attach('browser-issues', {
        body: report,
        contentType: 'text/plain'
      });
    },
    { auto: true }
  ]
});
