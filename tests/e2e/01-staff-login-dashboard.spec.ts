import { expect, test } from '@playwright/test';

// Template 1 of 5: staff login + dashboard cards for tenant 1000
test.describe('Mission-critical: staff login and dashboard', () => {
	test('admin logs into Phoenix Enterprise Events and sees operations cards', async ({ page }) => {
		await page.goto('/login.php'); // Staff login form
		await expect(page.getByRole('heading', { name: 'Admin/Staff Login' })).toBeVisible(); // Login chrome

		const accountSelect = page.locator('select[name="accountid"]'); // Account picker
		await expect(accountSelect).toBeVisible();
		await page.waitForTimeout(1500); // Allow accountList JSON to fill options
		const optionCount = await accountSelect.locator('option').count();
		test.skip(optionCount <= 1, 'Staging seed not loaded; account picker empty.');

		await accountSelect.selectOption({ label: /Phoenix Enterprise Events/i }); // Tenant 1000
		await page.locator('input[name="email"]').fill(process.env.E2E_LOGIN_EMAIL || 'admin@phoenix-enterprise.example');
		await page.locator('input[name="pass"]').fill(process.env.E2E_LOGIN_PASSWORD || 'TepStaging!1000');
		await page.getByRole('button', { name: 'Login' }).click();

		await expect(page).not.toHaveURL(/login\.php$/); // Successful login leaves the form
		await page.goto('/index.php'); // Operations dashboard
		await expect(page.getByRole('heading', { name: 'Event Pulse' })).toBeVisible();
		await expect(page.getByRole('heading', { name: 'Staff check-in' })).toBeVisible();
		await expect(page.getByRole('heading', { name: 'Live poll' })).toBeVisible();
	});
});
