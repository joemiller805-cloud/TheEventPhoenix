-- TEP vendor booth + captured leads: lightweight tables for tep_local (safe to re-run)
CREATE TABLE IF NOT EXISTS tep_vendor_booths (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	accountid INT UNSIGNED NOT NULL DEFAULT 0,
	sponsorid INT UNSIGNED NOT NULL DEFAULT 0,
	eventid INT UNSIGNED NULL DEFAULT NULL,
	vendor_name VARCHAR(255) NOT NULL DEFAULT '',
	booth VARCHAR(64) NOT NULL DEFAULT '',
	hall VARCHAR(128) NOT NULL DEFAULT '',
	notes VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (id),
	KEY idx_tep_vendor_booth_acct (accountid, sponsorid),
	KEY idx_tep_vendor_booth_eventid (eventid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tep_vendor_leads (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	accountid INT UNSIGNED NOT NULL DEFAULT 0,
	sponsorid INT UNSIGNED NOT NULL DEFAULT 0,
	eventid INT UNSIGNED NULL DEFAULT NULL,
	attendee_name VARCHAR(255) NOT NULL DEFAULT '',
	email VARCHAR(255) NOT NULL DEFAULT '',
	company VARCHAR(255) NOT NULL DEFAULT '',
	ticket VARCHAR(64) NOT NULL DEFAULT '',
	notes VARCHAR(500) NOT NULL DEFAULT '',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_tep_vendor_leads_acct (accountid, sponsorid),
	KEY idx_tep_vendor_leads_eventid (eventid),
	KEY idx_tep_vendor_leads_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
