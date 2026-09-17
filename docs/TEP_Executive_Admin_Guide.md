# The Event Phoenix — Executive Admin Guide

Operational handbook for **tenant account 1000** (`Phoenix Enterprise Events`) after Phase 11 certification (`v1.0.0-R1-certified`). Written for a non-technical operator. Do not edit code; follow the menu paths below.

## Staging tenant (account 1000)

Load `sql/tep_staging_seed.sql` into MariaDB once (safe to re-run). Then use these dummy logins. Password for every seeded user is `TepStaging!1000`.

| Role | Email / username | Where to sign in |
| --- | --- | --- |
| Master admin | `admin@phoenix-enterprise.example` | `/login.php` → select **Phoenix Enterprise Events** |
| Floor staff | `staff@phoenix-enterprise.example` | `/login.php` → same account |
| Speaker staff | `speaker@phoenix-enterprise.example` | `/login.php` |
| Exhibitor | username `apex.exhibitor` | `/sponsor/login.php?accountid=1000` |
| VIP attendee | `vip.attendee@phoenix-enterprise.example` | event site or `/login_attendee.php` |
| Public event home | slug `phoenix-summit-2026` | `/e/phoenix-summit-2026/home` |

VIP ticket code for search: **PES-VIP-1901**. General: **PES-GEN-1902**. Speaker: **PES-SPK-1903**.

There is no separate **Venues** table. Venue names live on **Rooms → Area** (seeded as Grand Ballroom, West Wing, Expo Hall). Images and media live as **Documents** and **Videos** file paths, not a dedicated images table.

---

## 1. Sign in as account 1000 admin

1. Open `/login.php`.
2. Confirm the heading **Admin/Staff Login**.
3. In **Account**, choose **Phoenix Enterprise Events**.
4. Email: `admin@phoenix-enterprise.example`.
5. Password: `TepStaging!1000`.
6. Click **Login**.
7. Expected: you land on the staff dashboard (`/index.php` or `/admin.php` after the existing login redirect). The navbar appears after account data loads.

If the page shows **Invalid login credentials**, the seed was not applied or the account picker is empty.

---

## 2. Staff dashboard (live operations)

Open `/index.php` while still logged in as staff/admin.

### Event Pulse

Card title: **Event Pulse**.

1. Read **checked in (X of Y)** and the orange percent bar.
2. Read **vendor leads logged**.
3. Read live poll vote counts under each question.
4. Click **Refresh Pulse**.
5. Expected: numbers update without a full page reload. Empty polls show **No live polls yet.**

### Live polling

Card title: **Live poll**.

1. Confirm a question such as **Is this keynote useful?** with **Yes** / **No**.
2. Tap one option (48px buttons).
3. Expected: the selected option records one vote for this browser. A second tap from the same device does not double-count (unique voter key).
4. Click **Refresh Pulse** and confirm the vote total moved.

### Staff check-in

Card title: **Staff check-in**.

1. In **Name or ticket**, type `Patel` or `PES-VIP-1901` (at least two characters).
2. Click **Search** (or press Enter).
3. Expected: **Morgan Patel**, ticket **PES-VIP-1901**, event **Phoenix Enterprise Summit 2026**.
4. Click **Check in**.
5. Expected: status text **Checked in.** and the button becomes **Checked in — tap to undo**.
6. Click **Refresh Pulse**. Expected: checked-in count increases by one if this ticket was previously out.
7. Click **Checked in — tap to undo** to reverse.

### Vendor lead monitoring

Card title: **Vendor Operations**.

Seeded booth: **Apex Exhibitors · Booth A-12** in **Expo Hall**.

1. Confirm the booth line is visible. If you see **No booth assignment for this login**, you are not on a user tied to sponsor 2001 / account 1000 booth seed — use the admin login on XAMPP after the seed, or the exhibitor login.
2. Fill **Attendee name** (required). Optional: Email, Company, Ticket, Notes.
3. Click **Save lead**.
4. Expected: the lead appears in the recent-leads list on this card. **Event Pulse** lead count increases after **Refresh Pulse**.

### Database snapshots (Event Snapshot)

Card title: **Event Snapshot**. Visible only when the session is a real admin (`accountid` matches `useraccount`).

1. Stay logged in as **admin@phoenix-enterprise.example**.
2. Click **Export Event Snapshot**.
3. Expected: the browser downloads `tep_event_snapshot_1000_YYYYMMDD_HHMMSS.sql`.
4. Open the file in a text editor. Expected first lines: `-- TEP Event Snapshot`, `-- accountid: 1000`.
5. Attendee or empty sessions must **not** see this card.

CLI backup (server operator only): `php bin/backup_db.php` writes under `TEP_BACKUP_DIR` when set.

---

## 3. Account administration (`/admin.php`)

Sign in as master admin, then open `/admin.php`. Left menu groups:

### Account Settings

| Menu label | Hash / route | What to do |
| --- | --- | --- |
| Account Configuration → Account Details | `/admin.php` (default) | Confirm name **Phoenix Enterprise Events**, slug `phoenix-enterprise`. |
| Attendee Messages | `#attendeeMsgs` | Home-page banner text (`home_pg_msg`). |
| Features | `acct_features` | Toggles: surveys, docs, inventory, season pass, course proposals, event requests, staff expense, sponsors, videos. |
| Image Management | `#imgMgmt` | Account logos/photos under `img/account1000/`. |
| Security Groups | `security_groups` | Group **Event Operations** (id 1001) with account and event page lists. |
| Staff | `users` | Users **Alex Rivera**, **Sam Chen**, **Jordan Blake**. |
| Tracks | `tracks` | **Leadership**, **Operations**. |
| Vendor Mgmt | `vendor_management` | **Apex Exhibitors**. |
| Video Mgmt | `video_mgmt` | **Opening Keynote Replay**. |
| Expense Mgmt | `expense_management` | Staff expense **Airport transfer** $86.40 for Sam Chen. |

### Reports (Account Settings → Reports)

Open **Reports**, then pick:

- Course History
- Email History
- Event Sponsor Attendance
- Event User Attendance
- Payments
- Staff Expenses / Staff Payments
- Outstanding Balances
- Registrations
- Vendor Orders
- Video Orders
- Users
- Survey Responses
- Season Pass Orders

---

## 4. Event administration

From `/admin.php` choose **Event Management**, open **Phoenix Enterprise Summit 2026**, then use the event tabs.

| Tab / menu | Route | Seeded content |
| --- | --- | --- |
| Event Details | `event_details` | 12–14 Oct 2026, Phoenix Convention Center, prefix PES |
| Registration Types | `registration_types` | **VIP** $799, **General** $399, **Speaker** $0 |
| Discount Codes | `registration_discounts` | Code **SUMMIT10** |
| Registration Extras | `registration_extras` | **Awards Lunch** $45 |
| Vendor Options | `sponsor_types` | **Expo Booth** $2500 |
| Sessions | `sessions` | Monday Morning / Monday Afternoon |
| Rooms | `rooms` | Hall A (Grand Ballroom), Room 12 (West Wing), Expo 1 (Expo Hall) |
| Event Courses | `course_catalog` | Opening Keynote, Check-In Lab |
| Event Program | `master_schedule` | Day 1 Grid |
| Event Staff | `event_users` | Sam Chen, Jordan Blake |
| Registrations | `registrations` | Three seeded tickets |
| Survey Questions | `survey_questions` | Overall rating, attend again, session usefulness |

---

## 5. Public attendee and exhibitor surfaces

- Public home: `/e/phoenix-summit-2026/home`
- Register: `/e/phoenix-summit-2026/register` (or the **register** link on the event page)
- Exhibitor login: `/sponsor/login.php?accountid=1000`
- Confirmation lookup: attendee tools that ask for confirmation **PES-VIP-1901**

---

## 6. Safety rules for operators

- Never paste passwords, session cookies, or SQL dumps into tickets or chat.
- Snapshot SQL is tenant-scoped to the logged-in account. Store it like a backup.
- Production mailboxes and hostnames stay `easyregpro.com`. User-facing product name is **The Event Phoenix**.
- Do not run `sql/tep_staging_seed.sql` against production. It is dummy data for staging and local XAMPP (`tep_local`).
