import type { Page, TestInfo } from '@playwright/test';
import { attendeeConfirmation, requireAttendeeLoginConfig } from '../shared/auth-utils';
import {
  publicEventName,
  publicEventNamePattern,
  publicEventPathPattern,
  publicEventRegisterPathPattern,
  publicEventsPath,
  publicEventsPathPattern
} from '../shared/e2e-config';
import { shouldIgnoreConsoleError } from '../shared/e2e-monitor';
import { expect, test } from '../test';
import { waitForKnownBlockingUi } from '../shared/ui-settle';

const monitorBrowserIssues = (page: Page, browserIssues: string[]) => {
  page.on('console', (msg) => {
    if (msg.type() !== 'error') return;
    if (shouldIgnoreConsoleError(msg.text())) return;
    browserIssues.push(`[console.error] ${msg.text()}`);
  });

  page.on('pageerror', (error) => {
    browserIssues.push(`[pageerror] ${error.message}`);
  });
};

const assertNoBrowserIssues = async (browserIssues: string[], testInfo: TestInfo) => {
  if (!browserIssues.length) return;

  const report = browserIssues.join('\n');
  await testInfo.attach('attendee-browser-issues', {
    body: report,
    contentType: 'text/plain'
  });
  expect(browserIssues, report).toEqual([]);
};

const expectPageHasInformation = async (page: Page) => {
  await expect(page.locator('body')).toContainText(new RegExp(`PSUG Events|${publicEventNamePattern.source}|Welcome|Courses Offered|Event Documents|Order Summary|Schedule|Registration`, 'i'));
  const visibleTables = page.locator('table:visible');
  const visiblePanels = page.locator('.main-content:visible, .col-md-9:visible, .wrapper:visible');
  const tableCount = await visibleTables.count();
  const panelCount = await visiblePanels.count();
  expect(tableCount + panelCount).toBeGreaterThan(0);
};

const expectNavigationStayedWithinLink = async (page: Page, href: string) => {
  const expected = new URL(href);
  const current = new URL(page.url());
  const stayedOnRequestedPage =
    current.href === expected.href ||
    current.href.startsWith(`${expected.href}/`);

  expect(
    stayedOnRequestedPage,
    `Expected navigation to stay on ${expected.href} or a canonical child page, but landed on ${current.href}`
  ).toBe(true);
};

const waitForLoginModel = async (page: Page, confirmation: string) => {
  await page.waitForFunction((expectedConfirmation) => {
    const input = document.querySelector('input[ng-model="loginConfirmation"]');
    if (!input || !(window as any).angular) return false;

    const scope = (window as any).angular.element(input).scope();
    return scope?.loginConfirmation === expectedConfirmation;
  }, confirmation);
};

const loginFromSidebar = async (page: Page) => {
  const loginBox = page.locator('#attendeeLoginDiv');
  const loginInput = page.locator('input[ng-model="loginConfirmation"]');
  const invalidConfirmationAlert = loginBox.locator('[role="alert"]');

  for (let attempt = 0; attempt < 2; attempt += 1) {
    if (await loginBox.isHidden().catch(() => false)) return;

    await loginInput.fill(attendeeConfirmation);
    await expect(loginInput).toHaveValue(attendeeConfirmation);
    await waitForLoginModel(page, attendeeConfirmation);
    await loginBox.getByRole('button', { name: 'Login' }).click();
    await waitForKnownBlockingUi(page);

    const loggedIn = await expect(loginBox).toBeHidden({ timeout: 7500 }).then(
      () => true,
      () => false
    );
    if (loggedIn) return;

    const invalidConfirmation = await invalidConfirmationAlert.isVisible().catch(() => false);
    expect(
      invalidConfirmation,
      `Attendee confirmation ${attendeeConfirmation} was rejected for ${publicEventName}. Update E2E_ATTENDEE_CONFIRMATION or the configured public event fixture.`
    ).toBe(false);
  }

  await expect(loginBox).toBeHidden({ timeout: 15000 });
};

test.describe('Attendee login coverage', () => {
  test(`attendee can log in from ${publicEventName} and open each visible left-nav page without browser errors`, async ({
    page,
    context
  }, testInfo) => {
    test.setTimeout(60_000);
    requireAttendeeLoginConfig();

    const browserIssues: string[] = [];
    monitorBrowserIssues(page, browserIssues);

    await page.goto(publicEventsPath);
    await expect(page).toHaveURL(publicEventsPathPattern);
    await waitForKnownBlockingUi(page);

    const testEventCard = page.locator('.card').filter({
      has: page.getByRole('heading', { name: publicEventNamePattern }),
      hasText: publicEventName
    }).first();
    await expect(testEventCard).toBeVisible();
    await testEventCard.getByRole('link', { name: /learn more/i }).click();

    await expect(page).toHaveURL(publicEventPathPattern);
    if (!/\/register(?:\/|$)/.test(page.url())) {
      await page.getByRole('link', { name: /^register$/i }).click();
    }

    await page.waitForURL(publicEventRegisterPathPattern);
    await expect(page.locator('#attendeeLoginDiv')).toBeVisible();
    await waitForKnownBlockingUi(page);

    await loginFromSidebar(page);
    await expect(page.locator('.evtSidebar')).toContainText(/Welcome/i);
    await waitForKnownBlockingUi(page);

    const linkData = await page.locator('.evtSidebar .list-group a.list-group-item').evaluateAll((nodes) =>
      nodes
        .filter((node) => {
          const el = node as HTMLElement;
          return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
        })
        .map((node) => ({
          href: (node as HTMLAnchorElement).href,
          text: (node.textContent || '').trim(),
          target: (node as HTMLAnchorElement).target || ''
        }))
        .filter((item) => item.text.length > 0)
    );

    expect(linkData.length).toBeGreaterThan(0);

    for (const link of linkData) {
      if (link.target === '_blank') {
        const popupPromise = context.waitForEvent('page');
        await page.evaluate((href) => window.open(href, '_blank'), link.href);
        const popup = await popupPromise;
        monitorBrowserIssues(popup, browserIssues);
        await popup.waitForLoadState('domcontentloaded');
        await waitForKnownBlockingUi(popup);
        await expectPageHasInformation(popup);
        await popup.close();
        continue;
      }

      await page.goto(link.href);
      await page.waitForLoadState('domcontentloaded');
      await waitForKnownBlockingUi(page);
      await expectNavigationStayedWithinLink(page, link.href);
      await expectPageHasInformation(page);
    }

    await assertNoBrowserIssues(browserIssues, testInfo);
  });
});
