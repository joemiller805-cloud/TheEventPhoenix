import { expect, test } from '@playwright/test';

// Template 3 of 5: live poll vote stays on dashboard and Pulse can refresh
test.describe('Mission-critical: live poll vote', () => {
	test('tap a poll option and refresh Event Pulse', async ({ page }) => {
		await page.goto('/login.php');
		const accountSelect = page.locator('select[name="accountid"]');
		await page.waitForTimeout(1500);
		test.skip((await accountSelect.locator('option').count()) <= 1, 'Staging seed not loaded.');
		await accountSelect.selectOption({ label: /Phoenix Enterprise Events/i });
		await page.locator('input[name="email"]').fill(process.env.E2E_LOGIN_EMAIL || 'admin@phoenix-enterprise.example');
		await page.locator('input[name="pass"]').fill(process.env.E2E_LOGIN_PASSWORD || 'TepStaging!1000');
		await page.getByRole('button', { name: 'Login' }).click();

		await page.goto('/index.php');
		await expect(page.getByRole('heading', { name: 'Live poll' })).toBeVisible();
		const yesBtn = page.getByRole('button', { name: /^Yes$/i }).first();
		test.skip(!(await yesBtn.count()), 'No active poll options on this dashboard.');
		await yesBtn.click(); // submitPollVote POST; unique voter_key
		await page.getByRole('button', { name: 'Refresh Pulse' }).click();
		await expect(page.getByRole('heading', { name: 'Event Pulse' })).toBeVisible();
	});
});
