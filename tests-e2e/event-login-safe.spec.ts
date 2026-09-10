import { expect, test } from './test';
import { eventAuthStatePath } from './shared/auth-utils';
import { waitForKnownBlockingUi } from './shared/ui-settle';

test.describe('Event user coverage', () => {
  test.use({ storageState: eventAuthStatePath });

  test('event-level user lands on an event-management area', async ({ page }) => {
    await page.goto('/admin.php');
    await page.waitForURL(/admin\.php|user_events\.php/, { timeout: 15000 });
    await waitForKnownBlockingUi(page);

    if (page.url().includes('/user_events.php')) {
      await expect(page.getByRole('heading', { name: 'Events' })).toBeVisible();
      await expect(page.getByText('Request Participation')).toBeVisible();
    } else {
      await expect(page).toHaveURL(/admin\.php#!\/event_management/);
      await expect(page.getByRole('heading', { name: 'Manage Events' })).toBeVisible();
      await expect(page.locator('table.scrollable.striped')).toBeVisible();
    }
  });

  test('event-level user can open the first event and view attendee registrations', async ({ page }) => {
    await page.goto('/admin.php#!/event_management');

    await expect(page).toHaveURL(/admin\.php#!\/event_management/);
    await expect(page.getByRole('heading', { name: 'Manage Events' })).toBeVisible();
    await waitForKnownBlockingUi(page);

    const firstEventRow = page.locator('table.scrollable.striped tbody tr').first();
    await expect(firstEventRow).toBeVisible();

    const firstEventLink = page.locator('tbody tr td a').first();
    await expect(firstEventLink).toBeVisible();
    await firstEventLink.click();

    await expect(page).toHaveURL(/admin\.php#!\/registrations\/\d+/);
    await expect(page.getByText('Manage Registrations')).toBeVisible();
    await waitForKnownBlockingUi(page);

    const attendeeRows = page.locator('table.scrollable.striped tbody tr');
    await expect(attendeeRows.first()).toBeVisible();
    expect(await attendeeRows.count()).toBeGreaterThan(0);
  });
});
