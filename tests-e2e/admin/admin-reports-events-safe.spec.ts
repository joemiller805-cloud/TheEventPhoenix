import { authStatePath } from '../shared/auth-utils';
import { mainContent, openAdminRoute } from '../shared/admin-utils';
import { startE2eMonitor } from '../shared/e2e-monitor';
import { expect, test } from '../test';

test.describe('Admin reports and events safe coverage', () => {
  test.use({ storageState: authStatePath });

  test('loads the Account Reports landing page', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'acctReports');

    await expect(page.locator(mainContent).getByRole('heading', { name: 'Account Reports' })).toBeVisible();
    await expect(page.locator(mainContent)).toContainText('Email History');
    await expect(page.locator(mainContent)).toContainText('Registrations');
    await expect(page.locator(mainContent)).toContainText('Staff');

    await monitor.assertClean();
  });

  test('loads key read-only Account Reports pages', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    const reports = [
      {
        route: 'email_history',
        text: 'Email History',
        searchPlaceholder: 'Quick Search'
      },
      {
        route: 'rpt_report_users',
        text: 'User Report',
        searchLabel: 'Quick Search'
      },
      {
        route: 'rpt_registrations',
        text: 'Event Registrations',
        searchPlaceholder: 'Quick Search'
      },
      {
        route: 'rpt_outstanding_balances',
        text: 'Outstanding Balances',
        searchPlaceholder: 'Quick Search'
      }
    ];

    for (const report of reports) {
      await openAdminRoute(page, report.route);
      await expect(page.locator(mainContent)).toContainText(report.text);

      if (report.searchPlaceholder) {
        await expect(page.locator(mainContent).getByPlaceholder(report.searchPlaceholder).first()).toBeVisible();
      }

      if (report.searchLabel) {
        await expect(page.locator(mainContent).getByText(report.searchLabel).first()).toBeVisible();
      }
    }

    await monitor.assertClean();
  });

  test('can use safe report controls without mutating data', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'rpt_registrations');
    await expect(page.locator(mainContent)).toContainText('Event Registrations');

    await page.getByPlaceholder('Quick Search').fill('zzzz-no-results-for-playwright');
    await expect(page.locator(mainContent)).toContainText(/No Records Found|Records/i);

    await page.locator('button[ng-click="showEvents()"]').click();
    const eventDialog = page.locator('#eventDialog');
    await expect(eventDialog).toBeVisible();
    await expect(eventDialog).toContainText('Events');
    await eventDialog.getByRole('button', { name: 'Done' }).click();
    await expect(eventDialog).toBeHidden();

    await monitor.assertClean();
  });

  test('loads Event Management and can open/cancel Copy Event when available', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'event_management');

    await expect(page.locator(mainContent).getByRole('heading', { name: 'Manage Events' })).toBeVisible();
    await expect(page.locator(mainContent)).toContainText('Registration Window');

    await page.getByLabel('Archived').check();
    await expect(page.locator('input[name="arch"][value="1"]')).toBeChecked();
    await page.getByLabel('Active').check();
    await expect(page.locator('input[name="arch"][value="0"]')).toBeChecked();

    const copyButtons = page.locator(mainContent).getByRole('button', { name: 'Copy' });
    if (await copyButtons.count()) {
      await copyButtons.first().click();

      const dialog = page.locator('#copyEventDialog');
      await expect(dialog).toBeVisible();
      await expect(dialog).toContainText('Copy Event');
      await expect(dialog.locator('input[ng-model="newEvent.name"]')).toBeVisible();
      await expect(dialog.locator('input[ng-model="newEvent.slug"]')).toBeVisible();

      await dialog.getByRole('button', { name: 'Cancel' }).click();
      await expect(dialog).toBeHidden();
    }

    await monitor.assertClean();
  });
});
