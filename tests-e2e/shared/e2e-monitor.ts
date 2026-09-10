import type { Page, TestInfo } from '@playwright/test';
import { expect } from '../test';

type E2eMonitorOptions = {
  attachmentName?: string;
  dataAccessPattern?: RegExp;
  ignoredConsoleErrorPattern?: RegExp;
};

const defaultIgnoredConsoleErrorPatterns = [
  /^Failed to load resource: the server responded with a status of 404 \(Not Found\)$/,
  /^Permissions policy violation: Geolocation access has been blocked because of a permissions policy applied to the current document\./
];

export const shouldIgnoreConsoleError = (
  text: string,
  ignoredConsoleErrorPattern?: RegExp
) => {
  const ignoredPatterns = ignoredConsoleErrorPattern
    ? [ignoredConsoleErrorPattern]
    : defaultIgnoredConsoleErrorPatterns;

  return ignoredPatterns.some((pattern) => pattern.test(text));
};

export const startE2eMonitor = (
  page: Page,
  testInfo: TestInfo,
  {
    attachmentName = 'e2e-monitor-issues',
    dataAccessPattern = /\/data_access\//i,
    ignoredConsoleErrorPattern
  }: E2eMonitorOptions = {}
) => {
  const issues: string[] = [];

  page.on('console', (msg) => {
    if (msg.type() !== 'error') return;
    if (shouldIgnoreConsoleError(msg.text(), ignoredConsoleErrorPattern)) return;
    issues.push(`[console.error] ${msg.text()}`);
  });

  page.on('pageerror', (error) => {
    issues.push(`[pageerror] ${error.message}`);
  });

  page.on('response', (response) => {
    if (!dataAccessPattern.test(response.url())) return;
    if (response.status() < 400) return;
    issues.push(`[${response.status()}] ${response.url()}`);
  });

  return {
    assertClean: async () => {
      if (!issues.length) return;

      const report = issues.join('\n');
      await testInfo.attach(attachmentName, {
        body: report,
        contentType: 'text/plain'
      });
      expect(issues, report).toEqual([]);
    }
  };
};
