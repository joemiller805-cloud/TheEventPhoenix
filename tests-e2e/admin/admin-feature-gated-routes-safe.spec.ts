import { authStatePath } from '../shared/auth-utils';
import { mainContent, openAdminRoute } from '../shared/admin-utils';
import { startE2eMonitor } from '../shared/e2e-monitor';
import { expect, test } from '../test';

test.describe('Admin feature-gated routes safe coverage', () => {
  test.use({ storageState: authStatePath });

  test('loads optional Account Configuration feature routes', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    const routes = [
      {
        route: 'inventory_settings',
        expectedText: ['Locations', 'Conditions', 'Categories']
      },
      {
        route: 'survey_management',
        expectedText: ['Event Questions', 'Section Questions']
      },
      {
        route: 'season_passes',
        expectedText: ['Season Passes', 'New Pass']
      }
    ];

    for (const { route, expectedText } of routes) {
      await openAdminRoute(page, route);

      for (const text of expectedText) {
        await expect(page.locator(mainContent)).toContainText(text);
      }
    }

    await monitor.assertClean();
  });

  test('loads optional admin workflow feature routes', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    const routes = [
      {
        route: 'inventory_management',
        expectedText: ['Inventory', 'Show Retired', 'New Inventory Item(s)']
      },
      {
        route: 'expense_management',
        expectedText: ['Staff Expenses', 'Event(s)', 'New Expense']
      }
    ];

    for (const { route, expectedText } of routes) {
      await openAdminRoute(page, route);

      for (const text of expectedText) {
        await expect(page.locator(mainContent)).toContainText(text);
      }
    }

    await monitor.assertClean();
  });

  test('loads optional report feature routes', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    const routes = [
      {
        route: 'rpt_survey_responses',
        expectedText: ['Survey Responses', 'Quick Search']
      },
      {
        route: 'rpt_season_pass',
        expectedText: ['Season Pass Orders', 'All Passes', 'Last Name']
      },
      {
        route: 'acct_expense_rpt',
        expectedText: ['Staff', 'Due', 'Show Details']
      },
      {
        route: 'acct_expense_pymts',
        expectedText: ['Staff Expense Payments', 'Exp Category', 'TOTAL:']
      }
    ];

    for (const { route, expectedText } of routes) {
      await openAdminRoute(page, route);

      for (const text of expectedText) {
        await expect(page.locator(mainContent)).toContainText(text);
      }
    }

    await monitor.assertClean();
  });
});
