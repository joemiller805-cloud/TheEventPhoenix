# The Event Phoenix — Master QA Guide

Human test plan for Phase 11 (`v1.0.0-R1-certified`). Feature list is taken from `admin.php` account/event routes, `index.php` dashboard cards, public event/register pages, and `data_access/select_all_table_recs.php` plus auto-discovered DML tables.

**Preconditions for every case unless noted**

1. MariaDB has `sql/tep_staging_seed.sql` applied (tenant **1000**).
2. Browser: Chrome or Edge, cookies enabled.
3. Base URL: local XAMPP (example `http://localhost`) or the staging host.
4. Dummy password: `TepStaging!1000`.
5. Do not use production data.

---

## Feature matrix (auto-discovered)

### A. Public / unauthenticated

| ID | Feature | Entry |
| --- | --- | --- |
| P1 | Marketing landing | `/landing.php` |
| P2 | About / pricing / markets | `/about/home.php` and sibling about pages |
| P3 | Admin login form | `/login.php` |
| P4 | Attendee login | `/login_attendee.php` |
| P5 | Exhibitor login | `/sponsor/login.php` |
| P6 | Public event home | `/e/{slug}/home` |
| P7 | Public registration | `/e/{slug}/register` |
| P8 | Confirmation lookup / print | attendee signup print + lookup |
| P9 | Vendor request | `/sponsor/newSponsor.php` |

### B. Staff dashboard (`/index.php`)

| ID | Feature | Card title |
| --- | --- | --- |
| D1 | Event Pulse | Event Pulse |
| D2 | Live poll vote | Live poll |
| D3 | Staff check-in | Staff check-in |
| D4 | Vendor leads | Vendor Operations |
| D5 | SQL snapshot | Event Snapshot |
| D6 | Web Push | Notifications |
| D7 | Upcoming events list | event name cards |

### C. Account admin (`/admin.php`)

| ID | Menu path | Route |
| --- | --- | --- |
| A1 | Account Settings → Account Configuration → Account Details | default |
| A2 | Account Settings → Attendee Messages | `#attendeeMsgs` |
| A3 | Account Settings → Features | `acct_features` |
| A4 | Account Settings → Image Management | `#imgMgmt` |
| A5 | Account Settings → Security Groups | `security_groups` |
| A6 | Account Settings → Staff | `users` |
| A7 | Account Settings → Tracks | `tracks` |
| A8 | Account Settings → Vendor Mgmt | `vendor_management` |
| A9 | Account Settings → Video Mgmt | `video_mgmt` |
| A10 | Account Settings → Expense Mgmt | `expense_management` |
| A11 | Account Settings → Course Management | `course_management` |
| A12 | Account Settings → Document Management | `document_management` |
| A13 | Account Settings → Event Management | `event_management` |
| A14 | Account Settings → Inventory | `inventory_management` |
| A15 | Account Settings → Season Passes | `season_passes` |
| A16 | Account Settings → Survey Management | `survey_management` |
| A17 | Reports → (all rpt_* routes) | see Admin Guide |

### D. Event admin (open an event, then tabs)

| ID | Tab | Route |
| --- | --- | --- |
| E1 | Dashboard | `evt_dashboard` |
| E2 | Event Details | `event_details` |
| E3 | Registration Form | `registration_form` |
| E4 | Registration Types | `registration_types` |
| E5 | Discount Codes | `registration_discounts` |
| E6 | Registration Extras | `registration_extras` |
| E7 | Registration Messages | `registration_messages` |
| E8 | Vendor Options | `sponsor_types` |
| E9 | Event Courses | `course_catalog` |
| E10 | Sessions | `sessions` |
| E11 | Rooms | `rooms` |
| E12 | Event Staff | `event_users` |
| E13 | Import Event Data | `import_event_data` |
| E14 | Event Program | `master_schedule` |
| E15 | Documents | `evt_document_management` |
| E16 | Survey Questions | `survey_questions` |
| E17 | Registrations | `registrations` |
| E18 | Redeem Extras | `redeem_extras` |
| E19 | Check-in station | `station` |

### E. Gateway / security (automated or curl)

| ID | Control | Expected |
| --- | --- | --- |
| S1 | Unauthenticated DML | HTTP 401 JSON |
| S2 | GET write query | HTTP 405 |
| S3 | Missing CSRF on POST write | HTTP 403 |
| S4 | `data_access/queries.php` over HTTP | 403 via `.htaccess` |
| S5 | Password hashes in query JSON | stripped |
| S6 | Tenant hop via posted accountid on polls | ignored; session tenant wins |

---

## Discount code math (must verify)

Source of truth: `register.php` function `regTotal`.

Let **price** = registration type price + (each extra quantity × extra price).

Then:

- If a code with **method = percent** is selected:  
  `discount = price × (code.discount / 100)`  
  Example: VIP $799 + Awards Lunch $45 = **$844.00**. A 10% code → discount **$84.40**. Amount due **$759.60**.
- If method is **not** percent (flat):  
  `discount = code.discount` (a dollar amount, not divided by 100).  
  Example: VIP $799, flat $10 → discount **$10.00**, due **$789.00**. Extra $45 lunch → price $844, flat $10 → due **$834.00**.
- If **frequency = oneTime** (UI label one-time) and the cart has two people, the code applies only to the **first** registration row (`indexOf(reg) > 0` returns the raw price with no discount on later rows).
- Codes must match **BINARY** `discount_codes.code`, `type = attendee`, and today’s date between **sunrise** and **sunset**.
- Staging seed row **SUMMIT10** is a $10 attendee code for event 1100 (`2026-01-01`–`2026-10-11`). Treat it as **flat $10** unless an operator changes **method** to percent in **Discount Codes**.

Round display to two decimals. Learning Center video carts use a separate percent formula in `learningCenter.php` (`Math.round(discount * 100) / 100`).

---

## Human test cases

### TC-01 Staff login (account 1000)

**Preconditions:** Seed applied; `/login.php` loads.

**Steps**

1. Open `/login.php`.
2. Select **Phoenix Enterprise Events**.
3. Email `admin@phoenix-enterprise.example`, password `TepStaging!1000`.
4. Click **Login**.

**Expected:** Session is staff/admin. `/admin.php` or dashboard loads. Wrong password shows **Invalid login credentials** and stays on `/login.php`.

---

### TC-02 Live poll

**Preconditions:** Logged in as account 1000 staff. Seeded poll **Is this keynote useful?**

**Steps**

1. Open `/index.php`.
2. Find card **Live poll**.
3. Tap **Yes**.
4. Click **Refresh Pulse** on **Event Pulse**.

**Expected:** Vote count for Yes increases by one. A second tap from the same browser does not add a second vote.

---

### TC-03 Staff check-in

**Preconditions:** Logged in as staff. Ticket **PES-VIP-1901** exists.

**Steps**

1. `/index.php` → **Staff check-in**.
2. Type `PES-VIP-1901` → **Search**.
3. Click **Check in**.
4. Click **Refresh Pulse**.
5. Click **Checked in — tap to undo**.

**Expected:** Search returns Morgan Patel. After check-in, Pulse in-count rises (if this ticket was out). Undo clears check-in.

---

### TC-04 Vendor lead

**Preconditions:** Dashboard shows a booth (seed A-12).

**Steps**

1. `/index.php` → **Vendor Operations**.
2. Attendee name `QA Lead`.
3. **Save lead**.
4. **Refresh Pulse**.

**Expected:** Lead listed on the card. Pulse lead count +1. Empty name should not save a usable lead.

---

### TC-05 Event snapshot

**Preconditions:** Logged in as **admin** (not attendee).

**Steps**

1. `/index.php` → **Event Snapshot** → **Export Event Snapshot**.

**Expected:** File `tep_event_snapshot_1000_*.sql` downloads. Header includes `accountid: 1000`. Attendee sessions must not see the card.

---

### TC-06 Public registration + discount math

**Preconditions:** Event registration window includes today (`registrationstartdate`–`registrationenddate` on event 1100).

**Steps**

1. Open `/e/phoenix-summit-2026/register`.
2. Choose type **VIP** ($799.00).
3. Add extra **Awards Lunch** quantity 1 ($45.00).
4. Apply code **SUMMIT10** (if the form shows a discount field).
5. Read line items: type price, extra, discount, total due.

**Expected**

- Subtotal before discount: `799 + 45 = 844.00`.
- If SUMMIT10 is flat $10: due `844.00 - 10.00 = 834.00`.
- If an operator set method to 10 percent: discount `844.00 * 0.10 = 84.40`, due `759.60`.
- Invalid or expired code: discount stays `0.00`.

---

### TC-07 Account and event CRUD smoke

**Preconditions:** Master admin login.

**Steps**

1. `/admin.php` → **Staff** → confirm three users.
2. **Tracks** → Leadership and Operations.
3. **Event Management** → open **Phoenix Enterprise Summit 2026**.
4. **Registration Types** tab → VIP / General / Speaker prices 799 / 399 / 0.
5. **Rooms** tab → Area column shows venue names.
6. **Registrations** → three confirmations PES-*.

**Expected:** All seeded rows visible. Saving a room name round-trips after reload.

---

### TC-08 Reports

**Preconditions:** Master admin.

**Steps:** `/admin.php` → **Reports** → **Registrations**, **Staff Expenses**, **Survey Responses**, **Vendor Orders**.

**Expected:** Registration report includes VIP ticket. Expense report shows Airport transfer 86.40. Survey response exists for question “Overall event rating”. Vendor order total 2500.00.

---

### TC-09 Fail-closed API

**Preconditions:** Logged out (or incognito).

**Steps**

1. POST `/data_access/getQueryResults.php` with `query=submitPollVote` and no CSRF.
2. GET `/data_access/queries.php`.

**Expected:** Vote without session/CSRF is 401/403/405 — never 200 with a vote row. Direct `queries.php` is 403.

---

## Playwright templates

See `tests/e2e/` for the five mission-critical flow templates. They are not a new Node toolchain; they follow the existing `tests-e2e/` Playwright layout.
