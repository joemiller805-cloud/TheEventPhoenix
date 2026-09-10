-- TEP Web Push subscriptions: lightweight table for tep_local (safe to re-run)
CREATE TABLE IF NOT EXISTS tep_push_subscriptions (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	accountid INT UNSIGNED NOT NULL DEFAULT 0,
	userid INT UNSIGNED NULL DEFAULT NULL,
	endpoint VARCHAR(512) NOT NULL,
	p256dh VARCHAR(255) NOT NULL,
	auth VARCHAR(255) NOT NULL,
	user_agent VARCHAR(255) NOT NULL DEFAULT '',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uk_tep_push_endpoint (endpoint),
	KEY idx_tep_push_account (accountid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
