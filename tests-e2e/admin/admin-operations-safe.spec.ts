import { authStatePath } from '../shared/auth-utils';
import { mainContent, openAdminRoute } from '../shared/admin-utils';
import { startE2eMonitor } from '../shared/e2e-monitor';
import { expect, test } from '../test';

test.describe('Admin operations safe coverage', () => {
  test.use({ storageState: authStatePath });

  test('loads All Registrations and exercises safe filters', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'account_registrations');

    await expect(page.locator(mainContent).getByRole('heading', { name: 'Registrations' })).toBeVisible();
    await expect(page.locator(mainContent).getByText('Event(s)')).toBeVisible();
    await expect(page.locator(mainContent).getByText(/Active:/)).toBeVisible();

    await expect(page.getByRole('button', { name: /New payment/i })).toBeDisabled();
    await expect(page.getByRole('button', { name: /View payments/i })).toBeDisabled();
    await expect(page.getByRole('button', { name: /Check In/i })).toBeDisabled();
    await expect(page.getByRole('button', { name: /Edit Registration/i })).toBeDisabled();

    await page.getByLabel('Include Cancelled').check();
    await expect(page.getByLabel('Include Cancelled')).toBeChecked();
    await page.getByLabel('Include Cancelled').uncheck();
    await expect(page.getByLabel('Include Cancelled')).not.toBeChecked();

    const eventSelect = page.locator('select[ng-model="selectedEvent"]');
    await eventSelect.selectOption('upcomingAndRecent');
    await expect(eventSelect).toHaveValue('upcomingAndRecent');

    await monitor.assertClean();
  });

  test('opens and cancels the Staff email dialog without sending', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'users');

    await expect(page.locator(mainContent).getByRole('heading', { name: 'Manage Staff' })).toBeVisible();

    await page.getByRole('button', { name: /Email User\(s\)/i }).click();

    const emailDialog = page.locator('#emailDialog');
    await expect(emailDialog).toBeVisible();
    await expect(emailDialog).toContainText('Email Event Staff');
    await expect(emailDialog.getByText('Recipients:')).toBeVisible();
    await expect(emailDialog.getByText('Subject:')).toBeVisible();
    await expect(emailDialog.getByText('Body:')).toBeVisible();

    await emailDialog.getByRole('button', { name: 'Select All' }).click();
    await emailDialog.getByRole('button', { name: 'Clear All' }).click();
    await emailDialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(emailDialog).toBeHidden();

    await monitor.assertClean();
  });

  test('opens and cancels the New Vendor dialog without saving', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'vendor_management');

    await expect(page.locator(mainContent).getByRole('heading', { name: 'Vendors' })).toBeVisible();
    await expect(page.getByPlaceholder('Quick Search')).toBeVisible();

    await page.getByRole('button', { name: /New Vendor/i }).click();

    const editDialog = page.locator('#editDialog');
    await expect(editDialog).toBeVisible();
    await expect(editDialog).toContainText('Add Vendor');
    await expect(editDialog.locator('input[ng-model="editVendor.name"]')).toBeVisible();
    await expect(editDialog.locator('input[ng-model="editVendor.email"]')).toBeVisible();

    await editDialog.locator('input[ng-model="editVendor.name"]').fill('Playwright Unsaved Vendor');
    await editDialog.locator('input[ng-model="editVendor.email"]').fill('playwright-unsaved@example.com');
    await editDialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(editDialog).toBeHidden();

    await page.reload();
    await expect(page.locator(mainContent)).not.toContainText('Playwright Unsaved Vendor');

    await monitor.assertClean();
  });

  test('exercises Vendor Management filters without saving', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'vendor_management');

    await expect(page.locator(mainContent).getByRole('heading', { name: 'Vendors' })).toBeVisible();

    await page.getByPlaceholder('Quick Search').fill('zzzz-no-results-for-playwright');
    await expect(page.getByPlaceholder('Quick Search')).toHaveValue('zzzz-no-results-for-playwright');

    await page.getByLabel('Archived').check();
    await expect(page.locator('input[name="status"][value="1"]')).toBeChecked();
    await page.getByLabel('Active').check();
    await expect(page.locator('input[name="status"][value="0"]')).toBeChecked();

    await monitor.assertClean();
  });
});
