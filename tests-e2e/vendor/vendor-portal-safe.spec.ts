import type { Page } from '@playwright/test';
import { expect, test } from '../test';
import { requireVendorLoginConfig, vendorLoginPassword, vendorLoginUsername } from '../shared/auth-utils';
import { vendorLoginPath } from '../shared/e2e-config';
import { startE2eMonitor } from '../shared/e2e-monitor';
import { waitForKnownBlockingUi } from '../shared/ui-settle';

const sponsorHomePath = /\/sponsor\/home\.php/;

const loginAsVendor = async (page: Page) => {
  requireVendorLoginConfig();

  await page.goto(vendorLoginPath);
  await expect(page.getByRole('heading', { name: 'Vendor Login' })).toBeVisible();

  await page.locator('input[ng-model="username"]').fill(vendorLoginUsername);
  await page.locator('input[ng-model="pass"]').fill(vendorLoginPassword);
  await page.getByRole('button', { name: 'Log In' }).click();

  await page.waitForURL(sponsorHomePath, { timeout: 15000 });
  await expect(page.getByRole('link', { name: /Vendor Profile/i })).toBeVisible();
  await waitForKnownBlockingUi(page);
};

const gotoVendorTab = async (page: Page, tabName: string, hash: string) => {
  await page.getByRole('link', { name: tabName, exact: true }).click();
  await expect(page).toHaveURL(new RegExp(`/sponsor/home\\.php${hash.replace('/', '\\/')}$`));
  await waitForKnownBlockingUi(page);
};

test.describe('Vendor portal safe coverage', () => {
  test('vendor can open each visible portal tab without browser or backend errors', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await loginAsVendor(page);

    const tabs = [
      {
        text: 'Vendor Profile',
        hash: '#/',
        assert: async () => {
          await expect(page.locator('input[ng-model="vendor.name"]')).toHaveValue(/\S+/, { timeout: 15000 });
          await expect(page.locator('input[ng-model="vendor.email"]')).toHaveValue(/\S+/);
        }
      },
      {
        text: 'Staff',
        hash: '#/staff',
        assert: async () => {
          await expect(page.getByRole('button', { name: /New Staff Member/i })).toBeVisible();
          await expect(page.locator('table.table.striped.scrollable')).toBeVisible();
        }
      },
      {
        text: 'Events',
        hash: '#/events',
        assert: async () => {
          await expect(page.locator('#cart')).toContainText(/Cart Empty|Event/i);
          await expect(page.locator('table.eventTable').first()).toBeVisible({ timeout: 15000 });
        }
      },
      {
        text: 'Event Staff',
        hash: '#/eventStaff',
        assert: async () => {
          await expect(page.locator('table.eventTable').first()).toBeVisible({ timeout: 15000 });
          await expect(page.locator('.staffLabel').first()).toBeVisible();
        }
      },
      {
        text: 'Order History',
        hash: '#/orders',
        assert: async () => {
          await expect(page.locator('body')).toContainText(/Order Total|You do not have any orders\.|Due/i, {
            timeout: 15000
          });
          const hasOrderTable = (await page.locator('table.orderTable').count()) > 0;
          const hasNoOrders = await page.getByText('You do not have any orders.').isVisible().catch(() => false);
          expect(hasOrderTable || hasNoOrders).toBe(true);
        }
      },
      {
        text: 'Invoice',
        hash: '#/invoice',
        assert: async () => {
          await expect(page.locator('body')).toContainText(/Conference Invoice|There are no orders with an outstanding balance/i);
        }
      },
      {
        text: 'Propose a New Course',
        hash: '#/courseRequest',
        assert: async () => {
          await expect(page.getByRole('heading', { name: 'Propose a New Course' })).toBeVisible({ timeout: 15000 });
          await expect(page.locator('form[name="courseRequestForm"]')).toBeVisible();
        }
      }
    ];

    for (const tab of tabs) {
      await gotoVendorTab(page, tab.text, tab.hash);
      await tab.assert();
    }

    await monitor.assertClean();
  });

  test('opens and cancels vendor password reset without updating anything', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await loginAsVendor(page);

    await expect(page.locator('input[ng-model="vendor.name"]')).toHaveValue(/\S+/, { timeout: 15000 });

    const resetButton = page.getByRole('button', { name: 'Reset Password' });
    await expect(resetButton).toBeVisible();
    await resetButton.click();

    const dialog = page.locator('#pwResetDialog');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('Reset Password');
    await expect(dialog.locator('input[ng-model="currentPassword"]')).toBeVisible();
    await expect(dialog.locator('input[ng-model="newPassword"]')).toBeVisible();
    await expect(dialog.locator('input[ng-model="newPasswordConfirm"]')).toBeVisible();

    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeHidden();

    await monitor.assertClean();
  });

  test('opens and cancels the new vendor staff dialog without saving', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await loginAsVendor(page);
    await gotoVendorTab(page, 'Staff', '#/staff');

    const newStaffButton = page.getByRole('button', { name: /New Staff Member/i });
    await expect(newStaffButton).toBeVisible({ timeout: 15000 });
    await expect(newStaffButton).toBeEnabled();
    await newStaffButton.click();

    const dialog = page.locator('#staffDialog');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('Add Staff Member');
    await expect(dialog.locator('input[ng-model="selectedStaff.first_name"]')).toBeVisible();
    await expect(dialog.locator('input[ng-model="selectedStaff.last_name"]')).toBeVisible();
    await expect(dialog.locator('input[ng-model="selectedStaff.email"]')).toBeVisible();

    await dialog.locator('input[ng-model="selectedStaff.first_name"]').fill('Playwright');
    await dialog.locator('input[ng-model="selectedStaff.last_name"]').fill('Unsaved Vendor Staff');
    await dialog.locator('input[ng-model="selectedStaff.email"]').fill('playwright-vendor-staff@example.com');
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeHidden();

    await monitor.assertClean();
  });

  test('opens attendee list dialog for a vendor event when available and closes it safely', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await loginAsVendor(page);
    await gotoVendorTab(page, 'Events', '#/events');
    await expect(page.locator('table.eventTable').first()).toBeVisible({ timeout: 15000 });

    const attendeeListButtons = page.getByRole('button', { name: 'Attendee List' });
    test.skip((await attendeeListButtons.count()) === 0, 'The configured vendor has no event attendee list available.');

    await attendeeListButtons.first().click();

    const dialog = page.locator('#attendeeDialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('#attendeesTable')).toBeVisible();
    await dialog.getByRole('button', { name: 'Done' }).click();
    await expect(dialog).toBeHidden();

    await monitor.assertClean();
  });
});
