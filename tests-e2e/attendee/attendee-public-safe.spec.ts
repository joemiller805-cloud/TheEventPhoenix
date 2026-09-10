import type { Page } from '@playwright/test';
import {
  publicEventName,
  publicEventNamePattern,
  publicEventPathPattern,
  publicEventRegisterPathPattern,
  publicEventSlug,
  publicEventsPath,
  publicEventsPathPattern
} from '../shared/e2e-config';
import { startE2eMonitor } from '../shared/e2e-monitor';
import { expect, test } from '../test';
import { waitForKnownBlockingUi } from '../shared/ui-settle';

const openRegistration = async (page: Page) => {
  await page.goto('/attendee/clearAttendeeSessionInfo.php');
  await page.goto(publicEventsPath);
  await expect(page).toHaveURL(publicEventsPathPattern);
  await waitForKnownBlockingUi(page);

  const testEventCard = page.locator('.card').filter({
    has: page.getByRole('heading', { name: publicEventNamePattern }),
    hasText: publicEventName
  }).first();
  await expect(testEventCard).toBeVisible();
  await testEventCard.getByRole('link', { name: /learn more/i }).click();

  await expect(page).toHaveURL(publicEventPathPattern);
  if (!/\/register(?:\/|$)/.test(page.url())) {
    await page.getByRole('link', { name: /^register$/i }).click();
  }

  await page.waitForURL(publicEventRegisterPathPattern);
  await expect(page.locator('form[name="regForm"]')).toBeVisible();
  await expect(page.locator('.evtSidebar')).toContainText(/Already Ordered|Register/i);
  await waitForKnownBlockingUi(page);
};

const selectFirstRegistrationTypeIfNeeded = async (page: Page) => {
  const regTypeSelect = page.locator('select[ng-model="reg.registration_typeid"]').first();
  if (!(await regTypeSelect.count())) return;
  if (!(await regTypeSelect.isVisible().catch(() => false))) return;

  await page.waitForFunction(() => {
    const select = document.querySelector('select[ng-model="reg.registration_typeid"]') as HTMLSelectElement | null;
    return !!select && select.options.length > 0;
  });

  const firstValue = await regTypeSelect.locator('option').first().getAttribute('value');
  if (firstValue) await regTypeSelect.selectOption(firstValue);
};

const expectPublicPageHasContent = async (page: Page) => {
  await expect(page.locator('body')).toContainText(new RegExp(`${publicEventNamePattern.source}|Register|Courses Offered|Documents|Contact|Welcome`, 'i'));
  const visibleLayout = page.locator('.wrapper:visible, .main-content:visible, .evtSidebar:visible');
  expect(await visibleLayout.count()).toBeGreaterThan(0);
};

test.describe('Attendee public safe coverage', () => {
  test(`loads public ${publicEventName} registration form and validates required fields without submitting`, async ({
    page
  }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openRegistration(page);
    await selectFirstRegistrationTypeIfNeeded(page);

    const continueButton = page.getByRole('button', {
      name: /Continue To Payment|Complete Registration|Continue to Payment Info/i
    }).first();
    await expect(continueButton).toBeVisible();
    await continueButton.click();

    await expect(page.locator('form[name="regForm"]')).toBeVisible();
    await expect(page.locator('input[ng-model="reg.email"]')).toBeVisible();
    await expect(page.locator('input[ng-model="reg.email"]')).toHaveJSProperty('validity.valid', false);
    await expect(page.locator('input[ng-model="reg.first_name"]')).toHaveJSProperty('validity.valid', false);
    await expect(page.locator('input[ng-model="reg.last_name"]')).toHaveJSProperty('validity.valid', false);
    await expect(page.locator('#tokenizer-container')).toBeHidden();

    await monitor.assertClean();
  });

  test('shows a clear error for an invalid attendee confirmation number', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openRegistration(page);

    const loginBox = page.locator('#attendeeLoginDiv');
    await expect(loginBox).toBeVisible();
    await loginBox.getByPlaceholder('Confirmation Number').fill('PW-NOT-A-CONFIRMATION');
    await loginBox.getByRole('button', { name: 'Login' }).click();

    await expect(loginBox).toContainText('Confirmation Not Found');
    await expect(page.locator('form[name="regForm"]')).toBeVisible();

    await monitor.assertClean();
  });

  test('opens visible public event navigation links before attendee login', async ({ page }, testInfo) => {
    const monitor = startE2eMonitor(page, testInfo);

    await openRegistration(page);

    const linkData = await page.locator('.evtSidebar .list-group a.list-group-item').evaluateAll((nodes) =>
      nodes
        .filter((node) => {
          const el = node as HTMLElement;
          return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
        })
        .map((node) => ({
          href: (node as HTMLAnchorElement).href,
          text: (node.textContent || '').trim(),
          target: (node as HTMLAnchorElement).target || ''
        }))
        .filter((item) => item.text.length > 0 && item.target !== '_blank')
    );

    expect(linkData.length).toBeGreaterThan(0);

    for (const link of linkData) {
      await page.goto(link.href);
      await page.waitForLoadState('domcontentloaded');
      await waitForKnownBlockingUi(page);
      expect(new URL(page.url()).pathname).toMatch(new RegExp(`^/e/${publicEventSlug}`));
      await expectPublicPageHasContent(page);
    }

    await monitor.assertClean();
  });
});
