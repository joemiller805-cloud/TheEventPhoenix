import type { Page } from '@playwright/test';
import { authStatePath } from '../shared/auth-utils';
import { mainContent, openAdminRoute } from '../shared/admin-utils';
import { publicEventName } from '../shared/e2e-config';
import { startE2eMonitor } from '../shared/e2e-monitor';
import { expect, test } from '../test';

const gridRoot = '[er-grid-widget]';

const getRowCheckboxes = (page: Page) =>
  page.locator(`${gridRoot} tbody tr input[type="checkbox"]`);

const getRenderedRowCount = async (page: Page) => page.locator(`${gridRoot} tbody tr`).count();

const waitForGridData = async (page: Page) =>
  expect(page.locator(`${gridRoot} tbody tr`).first()).toBeVisible();

const openTestEventRegistrations = async (page: Page) => {
  await openAdminRoute(page, 'event_management');
  await expect(page.locator(mainContent).getByRole('heading', { name: 'Manage Events' })).toBeVisible();

  const testEventLink = page
    .locator(`${mainContent} a[href*="#!/registrations/"]`)
    .filter({ hasText: publicEventName })
    .first();

  await expect(testEventLink).toBeVisible();
  const href = await testEventLink.getAttribute('href');
  expect(href).toBeTruthy();
  const escapedHref = href!.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  await testEventLink.click();
  await expect(page).toHaveURL(new RegExp(`admin\\.php${escapedHref}`));

  await expect(page.locator(mainContent)).toContainText('Manage Registrations');
  await expect(page.locator('#regbuttons')).toBeVisible();
  await waitForGridData(page);
  expect(await getRenderedRowCount(page)).toBeGreaterThanOrEqual(1);
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
  await waitForGridData(page);
  expect(await getRenderedRowCount(page)).toBeGreaterThanOrEqual(count);

  await clearSelectedRows(page);

  for (let index = 0; index < count; index += 1) {
    await getRowCheckboxes(page).nth(index).check();
  }
};

test.describe(`Admin ${publicEventName} registrations safe coverage`, () => {
  test.use({ storageState: authStatePath });

  test(`opens ${publicEventName} registrations from Event Management and exercises payment actions safely`, async ({
    page
  }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openTestEventRegistrations(page);
    await selectRows(page, 1);

    await expect(page.getByRole('button', { name: /New pymt/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /View pymts/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: 'Check In', exact: true })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Edit Reg/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Edit Schedule/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Invoice/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Certificate/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Badge/i })).toBeEnabled();

    await page.getByRole('button', { name: /View pymts/i }).click();

    const paymentsDialog = page.locator('#paymentsDialog');
    await expect(paymentsDialog).toBeVisible();
    await expect(paymentsDialog.getByRole('columnheader', { name: 'Date' })).toBeVisible();
    await expect(paymentsDialog.getByRole('columnheader', { name: 'Method' })).toBeVisible();
    await expect(paymentsDialog.getByRole('columnheader', { name: 'Amount' })).toBeVisible();
    await paymentsDialog.getByRole('button', { name: 'Done' }).click();
    await expect(paymentsDialog).toBeHidden();

    await monitor.assertClean();
  });

  test(`opens and cancels an unsaved ${publicEventName} payment cleanly`, async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openTestEventRegistrations(page);
    await selectRows(page, 1);

    await page.getByRole('button', { name: /New pymt/i }).click();

    const paymentDialog = page.locator('#pymtDialog');
    await expect(paymentDialog).toBeVisible();
    await expect(paymentDialog).toContainText('Enter Payment(s)');

    await paymentDialog.locator('select[ng-model="reg.payment_type"]').first().selectOption('Check');
    await paymentDialog.locator('input[name="amount"]').first().fill('25.00');
    await paymentDialog.locator('input[ng-model="reg.ref_nbr"]').first().fill('PW-EVENT-UNSAVED');
    await paymentDialog.locator('textarea[ng-model="reg.pymtNote"]').first().fill('Playwright unsaved event payment note');

    await paymentDialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(paymentDialog).toBeHidden();

    await monitor.assertClean();
  });

  test(`opens and cancels the ${publicEventName} registration dialog safely`, async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openTestEventRegistrations(page);

    await page.getByRole('button', { name: /New Reg/i }).click();

    const registrationDialog = page.locator('#registrationDialog');
    await expect(registrationDialog).toBeVisible();
    await expect(registrationDialog).toContainText('New Registration');
    await expect(registrationDialog.locator('input[ng-model="editReg.last_name"]')).toBeVisible();
    await expect(registrationDialog.locator('input[ng-model="editReg.first_name"]')).toBeVisible();
    await expect(registrationDialog.locator('select[ng-model="editReg.registration_typeid"]')).toBeVisible();
    await expect(registrationDialog.getByRole('button', { name: 'Add Registration' })).toBeDisabled();

    await registrationDialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(registrationDialog).toBeHidden();

    await monitor.assertClean();
  });

  test(`opens and cancels ${publicEventName} edit and check-in dialogs safely`, async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openTestEventRegistrations(page);
    await selectRows(page, 1);

    await page.getByRole('button', { name: /Edit Reg/i }).click();

    const registrationDialog = page.locator('#registrationDialog');
    await expect(registrationDialog).toBeVisible();
    await expect(registrationDialog).toContainText('Edit Registration');
    await expect(registrationDialog).toContainText('Switch Event');
    await expect(registrationDialog.locator('select[ng-model="switchEvent"]')).toBeVisible();
    await registrationDialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(registrationDialog).toBeHidden();

    await page.getByRole('button', { name: 'Check In', exact: true }).click();

    const checkinDialog = page.locator('#checkinDialog');
    await expect(checkinDialog).toBeVisible();
    await expect(checkinDialog).toContainText('Registration Fee');
    await expect(checkinDialog).toContainText('Balance');
    await checkinDialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(checkinDialog).toBeHidden();

    await monitor.assertClean();
  });

  test(`updates action availability correctly when multiple ${publicEventName} registrations are selected`, async ({
    page
  }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openTestEventRegistrations(page);
    await selectRows(page, 2);

    await expect(page.getByRole('button', { name: /New pymt/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Invoice/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Certificate/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: /Badge/i })).toBeEnabled();
    await expect(page.getByRole('button', { name: 'Check In', exact: true })).toBeEnabled();

    await expect(page.getByRole('button', { name: /View pymts/i })).toBeDisabled();
    await expect(page.getByRole('button', { name: /Edit Reg/i })).toBeDisabled();
    await expect(page.getByRole('button', { name: /Edit Schedule/i })).toBeDisabled();
    await expect(page.getByRole('button', { name: /Un-Check In/i })).toBeDisabled();

    await monitor.assertClean();
  });
});
