import type { Page } from '@playwright/test';
import { authStatePath } from '../shared/auth-utils';
import { mainContent, openAdminRoute } from '../shared/admin-utils';
import { startE2eMonitor } from '../shared/e2e-monitor';
import { expect, test } from '../test';

const gridRoot = '[er-grid-widget]';

const openManageRegistrations = async (page: Page) => {
  await openAdminRoute(page, 'account_registrations');

  await expect(page.locator(mainContent).getByRole('heading', { name: 'Registrations' })).toBeVisible();
  await expect(page.locator(`${gridRoot} table`)).toBeVisible();
};

const getRowCheckboxes = (page: Page) =>
  page.locator(`${gridRoot} tbody tr input[type="checkbox"]`);

const getGridRowCount = async (page: Page) =>
  page.evaluate((selector) => {
    const target = document.querySelector(selector);
    if (!target || !(window as any).angular) return -1;

    const scope =
      (window as any).angular.element(target).isolateScope?.() ||
      (window as any).angular.element(target).scope?.();

    return Array.isArray(scope?.filteredData) ? scope.filteredData.length : -1;
  }, gridRoot);

const waitForGridData = async (page: Page) => {
  await page.waitForFunction((selector) => {
    const target = document.querySelector(selector);
    if (!target || !(window as any).angular) return false;

    const scope =
      (window as any).angular.element(target).isolateScope?.() ||
      (window as any).angular.element(target).scope?.();

    return Array.isArray(scope?.filteredData);
  }, gridRoot);
};

const ensureRegistrationsAvailable = async (page: Page, minRows: number) => {
  await waitForGridData(page);

  const eventSelect = page.locator('select[ng-model="selectedEvent"]');
  const options = await eventSelect.locator('option').evaluateAll((nodes) =>
    nodes
      .map((node) => ({
        value: (node as HTMLOptionElement).value,
        text: (node.textContent || '').trim()
      }))
      .filter((option) => option.value !== undefined && option.value !== null)
  );

  const preferredValues = ['recent', 'upcomingAndRecent', '', 'upcoming'];
  const specificEventValues = options
    .map((option) => option.value)
    .filter((value) => value && !preferredValues.includes(value) && !Number.isNaN(Number(value)));

  const candidateValues = [...preferredValues, ...specificEventValues];
  const attempted = new Set<string>();

  for (const value of candidateValues) {
    if (attempted.has(value)) continue;
    attempted.add(value);

    await eventSelect.selectOption(value);
    await waitForGridData(page);

    try {
      await page.waitForFunction(
        ({ selector, minRows: targetRows }) => {
          const target = document.querySelector(selector);
          if (!target || !(window as any).angular) return false;

          const scope =
            (window as any).angular.element(target).isolateScope?.() ||
            (window as any).angular.element(target).scope?.();

          return Array.isArray(scope?.filteredData) && scope.filteredData.length >= targetRows;
        },
        { selector: gridRoot, minRows },
        { timeout: 4000 }
      );
    } catch {}

    if ((await getGridRowCount(page)) >= minRows) return;
  }

  test.skip(true, `Manage Registrations needs at least ${minRows} registration row(s) in the current test data.`);
};

const clearSelectedRows = async (page: Page) => {
  const rowCheckboxes = getRowCheckboxes(page);
  const count = await rowCheckboxes.count();

  for (let index = 0; index < count; index += 1) {
    const checkbox = rowCheckboxes.nth(index);
    if (await checkbox.isChecked().catch(() => false)) {
      await checkbox.uncheck();
    }
  }
};

const selectRows = async (page: Page, count: number) => {
  await ensureRegistrationsAvailable(page, count);

  await clearSelectedRows(page);

  for (let index = 0; index < count; index += 1) {
    await getRowCheckboxes(page).nth(index).check();
  }
};

test.describe('Admin Manage Registrations safe coverage', () => {
  test.use({ storageState: authStatePath });

  test('selecting one registration enables single-record actions and opens payments safely', async ({
    page
  }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openManageRegistrations(page);
    await selectRows(page, 1);

    await expect(page.getByRole('button', { name: /New payment/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /View payments/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Check In/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Edit Registration/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Edit Schedule/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Invoice/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Certificate/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Delete/i })).toBeEnabled();

    await page.getByRole('button', { name: /View payments/i }).click();

    const paymentsDialog = page.locator('#paymentsDialog');
    await expect(paymentsDialog).toBeVisible();
    await expect(paymentsDialog.getByRole('columnheader', { name: 'Date' })).toBeVisible();
    await expect(paymentsDialog.getByRole('columnheader', { name: 'Method' })).toBeVisible();
    await expect(paymentsDialog.getByRole('columnheader', { name: 'Amount' })).toBeVisible();
    await expect(paymentsDialog.getByRole('button', { name: 'Done' })).toBeVisible();
    await paymentsDialog.getByRole('button', { name: 'Done' }).click();
    await expect(paymentsDialog).toBeHidden();

    await monitor.assertClean();
  });

  test('selecting one registration opens and cancels New payment safely', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openManageRegistrations(page);
    await selectRows(page, 1);

    await page.getByRole('button', { name: /New payment/i }).click();

    const paymentDialog = page.locator('#paymentDiv');
    await expect(paymentDialog).toBeVisible();
    await expect(paymentDialog).toContainText('Enter Payment(s)');
    await expect(paymentDialog.locator('select[ng-model="registration.payment_type"]').first()).toBeVisible();
    await expect(paymentDialog.locator('input[name="amount"]').first()).toBeVisible();

    await paymentDialog.locator('select[ng-model="registration.payment_type"]').first().selectOption('Check');
    await paymentDialog.locator('input[name="amount"]').first().fill('25.00');
    await paymentDialog.locator('input[ng-model="registration.ref_nbr"]').first().fill('PW-UNSAVED');
    await paymentDialog.locator('textarea[ng-model="registration.pymtNote"]').first().fill('Playwright unsaved payment note');

    await paymentDialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(paymentDialog).toBeHidden();

    await monitor.assertClean();
  });

  test('selecting one registration opens and cancels Check In safely', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openManageRegistrations(page);
    await selectRows(page, 1);

    await page.getByRole('button', { name: /Check In/i }).click();

    const checkinDialog = page.locator('#checkinDialog');
    await expect(checkinDialog).toBeVisible();
    await expect(checkinDialog).toContainText('Confirmation');
    await expect(checkinDialog).toContainText('Registration Fee');
    await expect(checkinDialog.getByRole('button', { name: 'Cancel' })).toBeVisible();

    await checkinDialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(checkinDialog).toBeHidden();

    await monitor.assertClean();
  });

  test('selecting multiple registrations updates action availability correctly', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openManageRegistrations(page);
    await selectRows(page, 2);

    await expect(page.getByRole('button', { name: /New payment/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Invoice/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Certificate/i })).toBeEnabled();

    await expect(page.getByRole('button', { name: /View payments/i })).toBeDisabled();
    await expect(page.getByRole('button', { name: /Check In/i })).toBeDisabled();
    await expect(page.getByRole('button', { name: /Edit Registration/i })).toBeDisabled();
    await expect(page.getByRole('button', { name: /Edit Schedule/i })).toBeDisabled();
    await expect(page.getByRole('button', { name: /Delete/i })).toBeDisabled();

    await monitor.assertClean();
  });
});
