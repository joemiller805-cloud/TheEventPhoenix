import type { Page, TestInfo } from '@playwright/test';
import { expect, test } from '../test';
import {
  requireVendorLoginConfig,
  vendorLoginPassword,
  vendorLoginUsername
} from '../shared/auth-utils';
import { publicEventsPath, publicEventsPathPattern, vendorLoginPath } from '../shared/e2e-config';
import { shouldIgnoreConsoleError } from '../shared/e2e-monitor';
import { waitForKnownBlockingUi } from '../shared/ui-settle';

const monitorBrowserIssues = (page: Page) => {
  const browserIssues: string[] = [];

  page.on('console', (msg) => {
    if (msg.type() !== 'error') return;
    if (shouldIgnoreConsoleError(msg.text())) return;
    browserIssues.push(`[console.error] ${msg.text()}`);
  });

  page.on('pageerror', (error) => {
    browserIssues.push(`[pageerror] ${error.message}`);
  });

  return browserIssues;
};

const assertNoBrowserIssues = async (browserIssues: string[], testInfo: TestInfo) => {
  if (!browserIssues.length) return;

  const report = browserIssues.join('\n');
  await testInfo.attach('vendor-browser-issues', {
    body: report,
    contentType: 'text/plain'
  });
  expect(browserIssues, report).toEqual([]);
};

const gotoVendorTab = async (page: Page, tabName: string, hash: string) => {
  await page.getByRole('link', { name: tabName, exact: true }).click();
  await expect(page).toHaveURL(new RegExp(`/sponsor/home\\.php${hash.replace('/', '\\/')}$`));
  await waitForKnownBlockingUi(page);
};

test.describe('Vendor login coverage', () => {
  test('public events page shows a Vendors menu in the top navbar', async ({ page }, testInfo) => {
    const browserIssues = monitorBrowserIssues(page);

    await page.goto(publicEventsPath);
    await expect(page).toHaveURL(publicEventsPathPattern);
    await expect(page.getByRole('link', { name: /Vendors/i })).toBeVisible();

    await assertNoBrowserIssues(browserIssues, testInfo);
  });

  test('vendor can log in directly and see data across vendor tabs without browser errors', async ({
    page
  }, testInfo) => {
    requireVendorLoginConfig();
    const browserIssues = monitorBrowserIssues(page);

    await page.goto(vendorLoginPath);
    await expect(page.getByRole('heading', { name: 'Vendor Login' })).toBeVisible();

    await page.locator('input[ng-model="username"]').fill(vendorLoginUsername);
    await page.locator('input[ng-model="pass"]').fill(vendorLoginPassword);
    await page.getByRole('button', { name: 'Log In' }).click();

    await page.waitForURL(/\/sponsor\/home\.php/, { timeout: 15000 });
    await expect(page.getByRole('link', { name: /Test Vendor/i })).toBeVisible();
    await waitForKnownBlockingUi(page);

    await expect(page.locator('ul.nav-tabs')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Vendor Profile', exact: true })).toBeVisible();
    await expect(page.locator('input[ng-model="vendor.name"]')).toHaveValue(/\S+/, { timeout: 15000 });
    await expect(page.locator('input[ng-model="vendor.email"]')).toHaveValue(/\S+/);

    await gotoVendorTab(page, 'Staff', '#/staff');
    const staffRows = page.locator('table.table.striped.scrollable tr').filter({
      has: page.locator('td')
    });
    await expect(staffRows.first()).toBeVisible({ timeout: 15000 });
    expect(await staffRows.count()).toBeGreaterThan(0);

    await gotoVendorTab(page, 'Events', '#/events');
    const eventTables = page.locator('table.eventTable');
    await expect(eventTables.first()).toBeVisible({ timeout: 15000 });
    expect(await eventTables.count()).toBeGreaterThan(0);

    await gotoVendorTab(page, 'Event Staff', '#/eventStaff');
    const eventStaffTables = page.locator('table.eventTable');
    await expect(eventStaffTables.first()).toBeVisible({ timeout: 15000 });
    await expect(page.locator('.staffLabel').first()).toBeVisible();

    await gotoVendorTab(page, 'Order History', '#/orders');
    const orderTables = page.locator('table.orderTable');
    await expect(orderTables.first()).toBeVisible({ timeout: 15000 });
    expect(await orderTables.count()).toBeGreaterThan(0);

    await gotoVendorTab(page, 'Invoice', '#/invoice');
    await expect(page.getByText('Conference Invoice')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('.invoiceOrderTbl').first()).toBeVisible();

    await gotoVendorTab(page, 'Propose a New Course', '#/courseRequest');
    await expect(page.getByRole('heading', { name: 'Propose a New Course' })).toBeVisible({
      timeout: 15000
    });
    const courseStaffOptions = page.locator('label input.form-check-input[type="checkbox"]');
    await expect(courseStaffOptions.first()).toBeVisible();
    expect(await courseStaffOptions.count()).toBeGreaterThan(0);

    await assertNoBrowserIssues(browserIssues, testInfo);
  });
});
