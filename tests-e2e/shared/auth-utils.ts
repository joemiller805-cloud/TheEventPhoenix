import path from 'path';
import { expect, test, type Page } from '@playwright/test';
import {
  attendeeConfirmation,
  eventLoginEmail,
  eventLoginPassword,
  loginAccountId,
  loginAccountName,
  loginEmail,
  loginPassword,
  vendorLoginPassword,
  vendorLoginUsername
} from './e2e-config';
import { waitForKnownBlockingUi } from './ui-settle';

export {
  attendeeConfirmation,
  eventLoginEmail,
  eventLoginPassword,
  loginAccountId,
  loginAccountName,
  loginEmail,
  loginPassword,
  vendorLoginPassword,
  vendorLoginUsername
};
export const authStatePath = path.join(process.cwd(), 'test-results', '.auth', 'login-safe.json');
export const eventAuthStatePath = path.join(process.cwd(), 'test-results', '.auth', 'event-login-safe.json');

export function requireLoginConfig() {
  test.skip(
    !loginAccountId || !loginAccountName || !loginEmail || !loginPassword,
    'Login test credentials are not configured. Set E2E_LOGIN_ACCOUNT, E2E_LOGIN_ACCOUNT_ID, E2E_LOGIN_EMAIL, and E2E_LOGIN_PASSWORD.'
  );
}

export function requireEventLoginConfig() {
  test.skip(
    !loginAccountId || !loginAccountName || !eventLoginEmail || !eventLoginPassword,
    'Event-user login credentials are not configured. Set E2E_LOGIN_ACCOUNT, E2E_LOGIN_ACCOUNT_ID, E2E_LOGIN_EVENT_EMAIL, and E2E_LOGIN_EVENT_PASSWORD.'
  );
}

export function requireVendorLoginConfig() {
  test.skip(
    !vendorLoginUsername || !vendorLoginPassword,
    'Vendor login credentials are not configured. Set E2E_LOGIN_VENDOR and E2E_LOGIN_VENDOR_PASSWORD.'
  );
}

export function requireAttendeeLoginConfig() {
  test.skip(
    !attendeeConfirmation,
    'Attendee login credentials are not configured. Set E2E_ATTENDEE_CONFIRMATION.'
  );
}

export async function openLoginForAccount(page: Page) {
  await page.goto('/login.php');
  await expect(page.locator('input[name="email"]')).toBeVisible();
  await expect(page.locator('input[name="pass"]')).toBeVisible();
  await waitForKnownBlockingUi(page);
  await page.evaluate((accountId) => {
    const select = document.querySelector('select[name="accountid"]');
    if (!select || !(window as any).angular) return;
    const scope = (window as any).angular.element(select).scope();
    scope.accountid = accountId;
    scope.$apply();
  }, loginAccountId);
}

export async function loginAsTestAccount(page: Page) {
  requireLoginConfig();
  await openLoginForAccount(page);
  await page.locator('input[name="email"]').fill(loginEmail);
  await page.locator('input[name="pass"]').fill(loginPassword);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/admin\.php|user_events\.php/, { timeout: 15000 });
  await waitForKnownBlockingUi(page);
}

export async function loginAsEventUser(page: Page) {
  requireEventLoginConfig();
  await openLoginForAccount(page);
  await page.locator('input[name="email"]').fill(eventLoginEmail);
  await page.locator('input[name="pass"]').fill(eventLoginPassword);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/admin\.php|user_events\.php/, { timeout: 15000 });
  await waitForKnownBlockingUi(page);
}
