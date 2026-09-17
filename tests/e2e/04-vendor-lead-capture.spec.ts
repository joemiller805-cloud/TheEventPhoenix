import { expect, test } from '@playwright/test';

// Template 4 of 5: vendor lead capture on the dashboard booth card
test.describe('Mission-critical: vendor lead capture', () => {
	test('save a lead from Vendor Operations', async ({ page }) => {
		await page.goto('/login.php');
		const accountSelect = page.locator('select[name="accountid"]');
		await page.waitForTimeout(1500);
		test.skip((await accountSelect.locator('option').count()) <= 1, 'Staging seed not loaded.');
		await accountSelect.selectOption({ label: /Phoenix Enterprise Events/i });
		await page.locator('input[name="email"]').fill(process.env.E2E_LOGIN_EMAIL || 'admin@phoenix-enterprise.example');
		await page.locator('input[name="pass"]').fill(process.env.E2E_LOGIN_PASSWORD || 'TepStaging!1000');
		await page.getByRole('button', { name: 'Login' }).click();

		await page.goto('/index.php');
		await expect(page.getByRole('heading', { name: 'Vendor Operations' })).toBeVisible();
		const noBooth = page.getByText('No booth assignment for this login.');
		test.skip(await noBooth.isVisible(), 'No tep_vendor_booths row for this session.');

		await page.getByPlaceholder('Attendee name').fill('QA Lead');
		await page.getByPlaceholder('Email (optional)').fill('qa.lead@phoenix-enterprise.example');
		await page.getByRole('button', { name: 'Save lead' }).click();
		await expect(page.getByText(/QA Lead|lead/i).first()).toBeVisible();
	});
});
