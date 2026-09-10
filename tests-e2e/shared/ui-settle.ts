import type { Locator, Page } from '@playwright/test';
import { expect } from '../test';

const visibleLoadingSelectors = [
  '.loadingDialog:visible',
  '#loadingSpan:visible'
].join(', ');

const knownLoadingTexts = [
  'Fetching Order Info',
  'Retrieving Registrations',
  'Loading Event Data',
  'Loading User Data',
  'Loading Survey Data',
  'Loading Staff Data',
  'Loading Course History',
  'Loading Data',
  'Loading Vendor Data',
  'Loading Schedule'
];

const waitForHiddenIfPresent = async (locator: Locator, timeout = 15000) => {
  if ((await locator.count()) === 0) return;
  await expect(locator.first()).toBeHidden({ timeout });
};

export const waitForKnownBlockingUi = async (page: Page, timeout = 15000) => {
  for (const loadingText of knownLoadingTexts) {
    await waitForHiddenIfPresent(page.getByText(loadingText), timeout);
  }

  const loadingOverlays = page.locator('.loadingDialog, #loadingSpan');
  if ((await loadingOverlays.count()) > 0) {
    await expect(loadingOverlays.first()).toBeHidden({ timeout });
  }
};
