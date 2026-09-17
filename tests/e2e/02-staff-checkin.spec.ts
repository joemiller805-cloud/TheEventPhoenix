import { expect, test } from '@playwright/test';

// Template 2 of 5: staff check-in search + toggle for seeded VIP ticket
test.describe('Mission-critical: staff check-in', () => {
	test('search PES-VIP-1901 and toggle check-in', async ({ page }) => {
		await page.goto('/login.php');
		const accountSelect = page.locator('select[name="accountid"]');
		await page.waitForTimeout(1500);
		test.skip((await accountSelect.locator('option').count()) <= 1, 'Staging seed not loaded.');
		await accountSelect.selectOption({ label: /Phoenix Enterprise Events/i });
		await page.locator('input[name="email"]').fill(process.env.E2E_LOGIN_EMAIL || 'admin@phoenix-enterprise.example');
		await page.locator('input[name="pass"]').fill(process.env.E2E_LOGIN_PASSWORD || 'TepStaging!1000');
		await page.getByRole('button', { name: 'Login' }).click();

		await page.goto('/index.php');
		const search = page.getByPlaceholder('Name or ticket'); // Staff check-in card
		await expect(search).toBeVisible();
		await search.fill('PES-VIP-1901'); // Seeded VIP confirmation
		await page.getByRole('button', { name: 'Search' }).click();
		await expect(page.getByText(/PES-VIP-1901/)).toBeVisible();
		await expect(page.getByText(/Morgan Patel/i)).toBeVisible();

		const toggle = page.getByRole('button', { name: /Check in|Checked in/i }).first();
		await toggle.click(); // checkInAttendee POST + CSRF
		await expect(page.getByText(/Checked in|Check-in cleared/i)).toBeVisible();
	});
});
