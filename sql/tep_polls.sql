-- TEP live polls: lightweight tables for tep_local (safe to re-run)
CREATE TABLE IF NOT EXISTS tep_polls (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	accountid INT UNSIGNED NOT NULL DEFAULT 0,
	eventid INT UNSIGNED NULL DEFAULT NULL,
	question VARCHAR(500) NOT NULL,
	options_json TEXT NOT NULL,
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_tep_polls_active (accountid, is_active, eventid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tep_poll_votes (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	pollid INT UNSIGNED NOT NULL,
	option_index TINYINT UNSIGNED NOT NULL,
	voter_key VARCHAR(64) NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uk_tep_poll_votes_voter (pollid, voter_key),
	KEY idx_tep_poll_votes_poll (pollid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Local demo poll for account 1000; skipped when any poll already exists
INSERT INTO tep_polls (accountid, eventid, question, options_json, is_active)
SELECT 1000, NULL, 'Is this session useful?', '["Yes","No"]', 1
WHERE NOT EXISTS (SELECT 1 FROM tep_polls LIMIT 1);
