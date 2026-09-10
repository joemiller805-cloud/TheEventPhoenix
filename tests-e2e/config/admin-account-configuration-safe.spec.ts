import { expect, test } from '../test';
import type { Page } from '@playwright/test';
import { authStatePath } from '../shared/auth-utils';
import { adminShell, mainContent, openAdmin, openAdminRoute } from '../shared/admin-utils';
import { startE2eMonitor } from '../shared/e2e-monitor';

const waitForScopeProperty = async (page: Page, selector: string, propertyName: string) => {
  await page.waitForFunction(
    ({ selector: targetSelector, propertyName: targetProperty }) => {
      const target = document.querySelector(targetSelector);
      if (!target || !(window as any).angular) return false;
      return (window as any).angular.element(target).scope()?.[targetProperty] !== undefined;
    },
    { selector, propertyName }
  );
};

const waitForAngularRepeatCount = async (
  page: Page,
  scopeSelector: string,
  propertyExpression: string,
  repeatedSelector: string
) => {
  await page.waitForFunction(
    ({ scopeSelector: targetScopeSelector, propertyExpression: targetPropertyExpression, repeatedSelector: targetRepeatedSelector }) => {
      const target = document.querySelector(targetScopeSelector);
      if (!target || !(window as any).angular) return false;
      const scope = (window as any).angular.element(target).scope();
      const value = targetPropertyExpression
        .split('.')
        .reduce((current, key) => current?.[key], scope);
      if (!value) return false;
      return document.querySelectorAll(targetRepeatedSelector).length === value.length;
    },
    { scopeSelector, propertyExpression, repeatedSelector }
  );
};

test.describe('Admin Account Configuration coverage', () => {
  test.use({ storageState: authStatePath });

  test('shows Account Configuration menu items for a main admin', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdmin(page);

    const accountConfigToggle = page.locator(adminShell).getByText('Account Configuration').first();
    await expect(accountConfigToggle).toBeVisible();
    await accountConfigToggle.click();

    const accountConfigMenu = page.locator(`${adminShell} .dropdown-menu`).filter({
      has: page.getByRole('link', { name: 'Account Details' })
    });

    await expect(accountConfigMenu.getByRole('link', { name: 'Account Details' })).toBeVisible();
    await expect(accountConfigMenu.getByRole('link', { name: 'Attendee Messages' })).toBeVisible();
    await expect(accountConfigMenu.getByRole('link', { name: 'Attendee Menu' })).toBeVisible();
    await expect(accountConfigMenu.getByRole('link', { name: 'Event Categories' })).toBeVisible();
    await expect(accountConfigMenu.getByRole('link', { name: 'Features' })).toBeVisible();
    await expect(accountConfigMenu.getByRole('link', { name: 'Image Mgmt' })).toBeVisible();
    await expect(accountConfigMenu.getByRole('link', { name: 'QR Configuration' })).toBeVisible();
    await expect(accountConfigMenu.getByRole('link', { name: 'Registration Fields' })).toBeVisible();
    await expect(accountConfigMenu.getByRole('link', { name: 'Registration Types' })).toBeVisible();
    await expect(accountConfigMenu.getByRole('link', { name: 'Security Groups' })).toBeVisible();
    await expect(accountConfigMenu.getByRole('link', { name: 'Staff Request Messages' })).toBeVisible();
    await expect(accountConfigMenu.getByRole('link', { name: 'Vendor Settings' })).toBeVisible();

    await monitor.assertClean();
  });

  test('loads Account Details and message/image tabs', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    const tabs = [
      {
        route: '',
        heading: 'Account Details',
        expectedText: 'Contact Information'
      },
      {
        route: '#attendeeMsgs',
        heading: 'Attendee Messages',
        expectedText: 'Payment Messages'
      },
      {
        route: '#staffMsgs',
        heading: 'Staff Request Messages',
        expectedText: 'Event Participation Response Email Content'
      },
      {
        route: '#imgMgmt',
        heading: 'Image Management',
        expectedText: 'New Image'
      }
    ];

    for (const tab of tabs) {
      await openAdminRoute(page, tab.route);

      await expect(page.locator(mainContent).getByRole('heading', { name: tab.heading })).toBeVisible();
      await expect(page.locator(mainContent)).toContainText(tab.expectedText);
    }

    await monitor.assertClean();
  });

  test('loads core Account Configuration setup screens', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    const screens = [
      {
        route: 'attendee_menu',
        heading: 'Attendee Menu',
        expectedText: 'Menu Item'
      },
      {
        route: 'event_categories',
        heading: 'Event Categories',
        expectedText: 'New Event Category'
      },
      {
        route: 'acct_features',
        expectedText: 'Feature'
      },
      {
        route: 'qr_config',
        expectedText: 'QR Codes configured here are available to be used with attendee badges.'
      },
      {
        route: 'reg_fields',
        heading: 'Default Registration Fields',
        expectedText: 'Add a new field'
      },
      {
        route: 'acct_reg_types',
        expectedText: 'Registration types are associated with each event in the event settings.'
      },
      {
        route: 'security_groups',
        expectedText: 'New Security Group'
      },
      {
        route: 'document_management',
        heading: 'Document Management',
        expectedText: 'New Document'
      },
      {
        route: 'vendor_settings',
        heading: 'Vendor Invoice Message',
        expectedText: 'Vendor Categories'
      }
    ];

    for (const screen of screens) {
      await openAdminRoute(page, screen.route);

      if (screen.heading) {
        await expect(page.locator(mainContent).getByRole('heading', { name: screen.heading })).toBeVisible();
      }

      await expect(page.locator(mainContent)).toContainText(screen.expectedText);
    }

    await monitor.assertClean();
  });

  test('loads Document Management categories without browser errors', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'document_management');
    await expect(page.locator(mainContent).getByRole('heading', { name: 'Document Management' })).toBeVisible();

    for (const category of ['Account Documents', 'Event Documents', 'Course Documents', 'Vendor Documents', 'Video Documents']) {
      await page.locator(mainContent).getByText(category).click();
      await expect(page.locator(mainContent)).toContainText(category);
      await expect(page.locator(mainContent).getByPlaceholder('Document Search')).toBeVisible();
    }

    await monitor.assertClean();
  });

  test('loads Feature settings as read-only configuration coverage', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'acct_features');

    await expect(page.locator(mainContent)).toContainText('Feature');
    await expect(page.locator(mainContent)).toContainText('Document Management');
    await expect(page.locator(mainContent)).toContainText('Season Passes');
    await expect(page.locator(mainContent)).toContainText('Staff Event Participation Requests');
    await expect(page.locator(mainContent)).toContainText('Vendor Management');
    await expect(page.locator(mainContent).getByRole('button', { name: 'Save' })).toBeVisible();

    await monitor.assertClean();
  });

  test('opens and cancels the Security Groups dialog without saving', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'security_groups');

    await page.getByRole('button', { name: 'New Security Group' }).click();

    const dialog = page.locator('#editDialog');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('New Security Group');
    await expect(dialog).toContainText('Account Pages');
    await expect(dialog).toContainText('Account Reports');
    await expect(dialog).toContainText('Event Pages');
    await expect(dialog).toContainText('Event Reports');

    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeHidden();

    await monitor.assertClean();
  });

  test('can add a client-side Registration Field row without saving', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'reg_fields');
    await expect(page.locator(mainContent).getByRole('heading', { name: 'Default Registration Fields' })).toBeVisible();

    const addFieldButton = page.getByRole('button', { name: /Add a new field/i });
    await expect(addFieldButton).toBeEnabled();
    const fieldRows = page.locator('#regFieldsTbody tr:visible');
    const initialRowCount = await fieldRows.count();

    await addFieldButton.click();
    await expect(fieldRows).toHaveCount(initialRowCount + 1);
    await expect(fieldRows.last().locator('input[ng-model="field.label"]')).toBeVisible();
    await expect(fieldRows.last().locator('select[ng-model="field.type"]')).toHaveValue('t');

    await monitor.assertClean();
  });

  test('can open QR Configuration dialog and insert attendee info without saving', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'qr_config');

    await page.getByRole('button', { name: 'New QR' }).click();

    const dialog = page.locator('#newQrDialog');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('New QR Code');

    await dialog.locator('input[ng-model="editCode.name"]').fill('Playwright Unsaved QR');
    await dialog.locator('textarea[ng-model="editCode.content"]').fill('Badge');
    await dialog.locator('select[ng-model="selectedField"]').selectOption('first_name');

    await expect(dialog.locator('textarea[ng-model="editCode.content"]')).toHaveValue(/Badge \[first_name\]/);
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeHidden();

    await monitor.assertClean();
  });

  test('can add and remove an unsaved Event Category row', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'event_categories');

    const categoryRows = page.locator('tbody tr:visible');
    const initialRowCount = await categoryRows.count();

    await page.getByRole('button', { name: /New Event Category/i }).click();
    await expect
      .poll(async () => await categoryRows.count(), {
        message: 'Expected a new event category row to appear after clicking "New Event Category"'
      })
      .toBeGreaterThan(initialRowCount);
    const postAddRowCount = await categoryRows.count();
    await categoryRows.first().locator('input[ng-model="categories[$index]"]').fill('Playwright Unsaved Category');

    await categoryRows.first().locator('button').click();
    await expect
      .poll(async () => await categoryRows.count(), {
        message: 'Expected removing the unsaved event category row to reduce the visible row count'
      })
      .toBeLessThan(postAddRowCount);
    await expect(page.locator(mainContent)).not.toContainText('Playwright Unsaved Category');

    await monitor.assertClean();
  });

  test('can add and cancel an unsaved Account Registration Type', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'acct_reg_types');

    const regTypeRows = page.locator('#regTypesTbody tr:visible');
    const initialRowCount = await regTypeRows.count();

    await page.getByRole('button', { name: /Add Attendee Reg Type/i }).click();
    await expect
      .poll(async () => await regTypeRows.count(), {
        message: 'Expected a new attendee registration type row to appear after clicking "Add Attendee Reg Type"'
      })
      .toBeGreaterThan(initialRowCount);

    const newRow = regTypeRows.last();
    await expect(newRow.locator('input[ng-model="regType.name"]')).toBeVisible();
    await expect(newRow.locator('input[ng-model="regType.price"]')).toHaveValue('0.00');

    await page.getByRole('button', { name: 'Cancel' }).click();
    await page.waitForURL(/admin\.php#!\/acct_reg_types/);
    await expect(page.locator(mainContent)).toContainText('Registration types are associated with each event');

    await monitor.assertClean();
  });

  test('shows Attendee Menu rows and protects required menu items', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'attendee_menu');

    const menuRows = page.locator('tbody tr:visible');
    await expect(menuRows.filter({ hasText: 'Event Home' })).toBeVisible();
    await expect(menuRows.filter({ hasText: 'Register' })).toBeVisible();
    await expect(menuRows.filter({ hasText: 'Manage Registration' })).toBeVisible();

    await expect(menuRows.filter({ hasText: 'Register' }).locator('input[type="checkbox"]')).toHaveCount(0);
    await expect(menuRows.filter({ hasText: 'Manage Registration' }).locator('input[type="checkbox"]')).toHaveCount(0);

    await monitor.assertClean();
  });

  test('can add unsaved Vendor Settings rows and discard them by reloading', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'vendor_settings');
    await waitForScopeProperty(page, 'button[ng-click="addCategory()"]', 'categorySettings');
    await waitForScopeProperty(page, 'button[ng-click="addThreshold()"]', 'thresholds');
    await waitForScopeProperty(page, 'button[ng-click="addDicountCode()"]', 'discountCodes');

    const categoryInputs = page.locator('input[ng-model="categorySettings.categories[$index]"]');
    const discountRows = page.locator('tr').filter({ has: page.locator('input[ng-model="val.val"]') });
    const discountCodeRows = page.locator('tr').filter({ has: page.locator('input[ng-model="code.code"]') });

    await waitForAngularRepeatCount(
      page,
      'button[ng-click="addCategory()"]',
      'categorySettings.categories',
      'input[ng-model="categorySettings.categories[$index]"]'
    );
    await waitForAngularRepeatCount(page, 'button[ng-click="addThreshold()"]', 'thresholds', 'input[ng-model="val.val"]');
    await waitForAngularRepeatCount(page, 'button[ng-click="addDicountCode()"]', 'discountCodes', 'input[ng-model="code.code"]');

    const initialCategoryCount = await categoryInputs.count();
    const initialDiscountCount = await discountRows.count();
    const initialDiscountCodeCount = await discountCodeRows.count();

    await page.getByRole('button', { name: 'Add Category' }).click();
    await expect(categoryInputs).toHaveCount(initialCategoryCount + 1);
    await categoryInputs.last().fill('Playwright Unsaved Vendor Category');

    await page.getByRole('button', { name: 'Add Discount', exact: true }).click();
    await expect(discountRows).toHaveCount(initialDiscountCount + 1);

    await page.getByRole('button', { name: 'Add Discount Code' }).click();
    await expect(discountCodeRows).toHaveCount(initialDiscountCodeCount + 1);
    await discountCodeRows.last().locator('input[ng-model="code.code"]').fill('PW-UNSAVED');

    await page.reload();
    await expect(page).toHaveURL(/admin\.php#!\/vendor_settings/);
    await expect(page.locator(mainContent)).toContainText('Vendor Categories');
    await expect(page.locator(mainContent)).not.toContainText('Playwright Unsaved Vendor Category');
    await expect(page.locator(mainContent)).not.toContainText('PW-UNSAVED');

    await monitor.assertClean();
  });

  test('can open and cancel the Season Pass dialog without saving', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'season_passes');

    await page.getByRole('button', { name: 'New Pass' }).click();

    const dialog = page.locator('#editDialog');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('New Season Pass');
    await expect(dialog.locator('input[ng-model="selectedPass.name"]')).toBeVisible();
    await expect(dialog.locator('textarea[ng-model="selectedPass.description"]')).toBeVisible();
    await expect(dialog.locator('input[ng-model="selectedPass.price"]')).toBeVisible();
    await expect(dialog).toContainText('Included Events');

    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeHidden();

    await monitor.assertClean();
  });

  test('can open and cancel the Staff user dialog without saving', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, 'users');

    await page.getByRole('button', { name: /New User/i }).click();

    const dialog = page.locator('#editDialog');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('Add User');
    await expect(dialog.locator('input[ng-model="editUser.first_name"]')).toBeVisible();
    await expect(dialog.locator('input[ng-model="editUser.last_name"]')).toBeVisible();
    await expect(dialog.locator('input[ng-model="editUser.email"]')).toBeVisible();
    await expect(dialog).toContainText('Security Groups');

    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeHidden();

    await monitor.assertClean();
  });

  test('sanitizes invalid Account Details URL identifiers without saving', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openAdminRoute(page, '');

    const slugInput = page.locator('input[ng-model="account.slug"]');
    await expect(slugInput).toBeVisible();
    const originalSlug = await slugInput.inputValue();

    await slugInput.fill('bad slug!@#');
    await slugInput.blur();
    await expect(slugInput).toHaveValue('badslug');
    const invalidIdentifierDialog = page.locator('.ui-dialog:visible');
    await expect(invalidIdentifierDialog.locator('.ui-dialog-title')).toContainText('Invalid Identifier');
    await expect(invalidIdentifierDialog.locator('.ui-dialog-content')).toContainText(
      'No white-space or special characters allowed'
    );

    await page.keyboard.press('Escape');
    await slugInput.fill(originalSlug);
    await page.reload();
    await expect(page.locator('input[ng-model="account.slug"]')).toHaveValue(originalSlug);

    await monitor.assertClean();
  });
});
