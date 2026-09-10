const fromEnv = (name: string, fallback = '') => process.env[name] || fallback;

export const escapeRegExp = (value: string) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

export const loginAccountName = fromEnv('E2E_LOGIN_ACCOUNT');
export const loginAccountId = fromEnv('E2E_LOGIN_ACCOUNT_ID');
export const loginEmail = fromEnv('E2E_LOGIN_EMAIL');
export const loginPassword = fromEnv('E2E_LOGIN_PASSWORD');
export const eventLoginEmail = fromEnv('E2E_LOGIN_EVENT_EMAIL');
export const eventLoginPassword = fromEnv('E2E_LOGIN_EVENT_PASSWORD', process.env.E2E_LOGIN_EVENT || '');
export const vendorLoginUsername = fromEnv('E2E_LOGIN_VENDOR');
export const vendorLoginPassword = fromEnv('E2E_LOGIN_VENDOR_PASSWORD');
export const attendeeConfirmation = fromEnv('E2E_ATTENDEE_CONFIRMATION');

export const publicEventsPath = fromEnv('E2E_PUBLIC_EVENTS_PATH', '/psugevents');
export const publicEventName = fromEnv('E2E_PUBLIC_EVENT_NAME', 'Test Event');
export const publicEventSlug = fromEnv('E2E_PUBLIC_EVENT_SLUG', 'test-event');
export const publicEventPath = `/e/${publicEventSlug}`;
export const publicEventRegisterPath = `${publicEventPath}/register`;
export const publicEventNamePattern = new RegExp(escapeRegExp(publicEventName), 'i');
export const publicEventsPathPattern = new RegExp(`${escapeRegExp(publicEventsPath)}(?:/|$)`);
export const publicEventPathPattern = new RegExp(`${escapeRegExp(publicEventPath)}(/|$)`);
export const publicEventRegisterPathPattern = new RegExp(`${escapeRegExp(publicEventRegisterPath)}(/|$)`);

export const vendorAccountId = fromEnv('E2E_VENDOR_ACCOUNT_ID', '1');
export const vendorLoginPath = `/sponsor/login.php?accountid=${vendorAccountId}`;
