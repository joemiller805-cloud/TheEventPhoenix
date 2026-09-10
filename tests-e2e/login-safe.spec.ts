import { expect, test } from './test';
import { authStatePath, loginEmail, openLoginForAccount } from './shared/auth-utils';

test.describe('Admin login coverage', () => {
  test('rejects invalid credentials for the test account', async ({ page }) => {
    await openLoginForAccount(page);

    await page.locator('input[name="email"]').fill('e2e-invalid@example.com');
    await page.locator('input[name="pass"]').fill('definitely-not-the-right-password');
    await page.getByRole('button', { name: 'Login' }).click();

    await expect(page.locator('.alert.alert-danger')).toBeVisible();
    await expect(page.locator('.alert.alert-danger')).toContainText('Invalid login credentials');
    await expect(page).toHaveURL(/login\.php/);
  });

  test.describe('authenticated coverage', () => {
    test.use({ storageState: authStatePath });

    test('accepts the provided test credentials', async ({ page }) => {
      await page.goto('/admin.php');
      await page.waitForURL(/admin\.php|user_events\.php/, { timeout: 15000 });

      if (page.url().includes('/user_events.php')) {
        await expect(page.getByRole('heading', { name: 'Events' })).toBeVisible();
        await expect(page.getByText('Request Participation')).toBeVisible();
      } else {
        await expect(page.locator('admin-header')).toBeVisible();
        await expect(page.getByText('Event Mgmt')).toBeVisible();
      }
    });

    test('can navigate to Event Mgmt and open the first listed event', async ({ page }) => {
      await page.goto('/admin.php#!/event_management');

      await expect(page).toHaveURL(/admin\.php#!\/event_management/);
      await expect(page.getByRole('heading', { name: 'Manage Events' })).toBeVisible();
      await expect(page.locator('table.scrollable.striped tbody tr').first()).toBeVisible();

      const firstEventLink = page.locator('tbody tr td a').first();
      await expect(firstEventLink).toBeVisible();

      const firstEventName = (await firstEventLink.textContent())?.trim();
      const firstEventHref = await firstEventLink.getAttribute('href');
      await firstEventLink.click();

      await expect(page).toHaveURL(/admin\.php#!\/registrations\/\d+/);
      await expect(page.getByText('Manage Registrations')).toBeVisible();
      await expect(page.getByRole('button', { name: 'New Reg' })).toBeVisible();

      if (firstEventHref) {
        await expect(page).toHaveURL(new RegExp(firstEventHref.replace('#!/', 'admin\\.php#!/')));
      }

      if (firstEventName) {
        await expect(page.locator('body')).toContainText(firstEventName);
      }
    });

    test('can open the first registration and load its edit form', async ({ page }) => {
      await page.goto('/admin.php#!/event_management');

      await expect(page.getByRole('heading', { name: 'Manage Events' })).toBeVisible();
      await page.locator('tbody tr td a').first().click();

      await expect(page).toHaveURL(/admin\.php#!\/registrations\/\d+/);
      await expect(page.getByText('Manage Registrations')).toBeVisible();

      const firstGridRow = page.locator('table.scrollable.striped tbody tr').first();
      await expect(firstGridRow).toBeVisible();

      const cells = firstGridRow.locator('td');
      const rowFirstName = (await cells.nth(2).textContent())?.trim() || '';
      const rowLastName = (await cells.nth(3).textContent())?.trim() || '';
      const rowEmail = (await cells.nth(4).textContent())?.trim() || '';

      await cells.nth(0).locator('input[type="checkbox"]').check();
      await page.getByRole('button', { name: 'Edit Reg' }).click();

      const dialog = page.locator('#registrationDialog');
      await expect(dialog).toBeVisible();
      await expect(dialog).toContainText('Edit Registration');
      await expect(dialog.locator('input[ng-model="editReg.first_name"]')).toHaveValue(rowFirstName);
      await expect(dialog.locator('input[ng-model="editReg.last_name"]')).toHaveValue(rowLastName);
      await expect(dialog.locator('input[ng-model="editReg.email"]')).toHaveValue(rowEmail);
    });

    test('can create a new event and then delete it', async ({ page }) => {
      const uniqueToken = Date.now().toString();
      const eventName = `Playwright Event ${uniqueToken}`;
      const eventSlug = `pw-event-${uniqueToken}`;
      const eventPrefix = `P${uniqueToken.slice(-4)}`;
      const eventReplyTo = loginEmail;
      const startDate = '12/15/2026';
      const endDate = '12/16/2026';
      const regStartDate = '12/01/2026';
      const regEndDate = '12/14/2026';
      console.log(`[create-delete] starting test for "${eventName}" slug="${eventSlug}"`);

      await page.goto('/admin.php#!/event_details/-1');
      await expect(page.locator('form[name="eventForm"]')).toBeVisible();

      await page.locator('input[ng-model="eventData.name"]').fill(eventName);
      await page.locator('input[ng-model="eventData.slug"]').fill(eventSlug);
      await page.locator('input[ng-model="eventData.startdate"]').fill(startDate);
      await page.locator('input[ng-model="eventData.enddate"]').fill(endDate);
      await page.locator('input[ng-model="eventData.replytoemail"]').fill(eventReplyTo);
      await page.locator('input[ng-model="eventData.registrationstartdate"]').fill(regStartDate);
      await page.locator('input[ng-model="eventData.registrationenddate"]').fill(regEndDate);
      await page.locator('input[ng-model="eventData.city"]').fill('Chicago');
      await page.locator('input[ng-model="eventData.site"]').fill('Playwright Test Venue');
      await page.locator('input[ng-model="eventData.prefix"]').fill(eventPrefix);
      await page.locator('button', { hasText: 'Save' }).click();

      const creationNotice = page.getByText('Your event has been created.');
      const saveNotice = page.locator('#updateSavedAlert');
      const eventCreated = Promise.any([
        creationNotice.waitFor({ state: 'visible', timeout: 10000 }),
        saveNotice.waitFor({ state: 'visible', timeout: 10000 })
      ]);
      await eventCreated;

      const createdEventId = await page.evaluate(() => {
        const form = document.querySelector('form[name="eventForm"]');
        if (!form || !(window as any).angular) return '';
        const scope = (window as any).angular.element(form).scope();
        return scope?.eventData?.id?.toString() || '';
      });

      console.log(`[create-delete] createdEventId=${createdEventId || '(missing)'} currentUrl=${page.url()}`);
      await expect(createdEventId).not.toEqual('');

      await page.goto(`/admin.php#!/event_details/${createdEventId}`);
      await expect(page.locator('form[name="eventForm"]')).toBeVisible();
      await expect(page.locator('input[ng-model="eventData.name"]')).toHaveValue(eventName);
      await expect(page.locator('input[ng-model="eventData.slug"]')).toHaveValue(eventSlug);
      console.log(`[create-delete] reopened event details url=${page.url()}`);

      await page.getByRole('button', { name: 'Delete' }).click();
      await expect(page.getByText('Are you sure you want to delete this event?')).toBeVisible();
      await page.getByRole('button', { name: 'Confirm Delete' }).click();

      await expect(page).toHaveURL(/admin\.php#!\/event_management/);
      await expect(page.getByRole('heading', { name: 'Manage Events' })).toBeVisible();
      const eventTable = page.locator('table.scrollable.striped tbody');
      const tableText = await eventTable.textContent();
      console.log(`[create-delete] after delete url=${page.url()} tableContainsEvent=${(tableText || '').includes(eventName)}`);
      await expect(eventTable).not.toContainText(eventName);
    });
  });
});
