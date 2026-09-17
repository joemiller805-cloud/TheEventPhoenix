-- The Event Phoenix (TEP) full-schema staging seed
-- Tenant Account 1000: Phoenix Enterprise Events
-- 72 tables from PHP/JS DML + information_schema (no venues/images tables in product).
-- Dummy logins use password TepStaging!1000 (bcrypt). Do not use in production.
-- Venues are rooms.area (Grand Ballroom, West Wing, Expo Hall).
-- Media assets are documents.filepath and videos.filepath.
-- Safe to re-run: CREATE IF NOT EXISTS + INSERT ... ON DUPLICATE KEY UPDATE.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_notes = 0;
-- Table: accounts
CREATE TABLE IF NOT EXISTS `accounts` (
	`id` INT NOT NULL,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`slug` VARCHAR(255) NOT NULL DEFAULT '',
	`web_logo` VARCHAR(255) NOT NULL DEFAULT '',
	`sponsors_enabled` VARCHAR(16) NOT NULL DEFAULT '',
	`disabled` TINYINT NOT NULL DEFAULT 0,
	`type` VARCHAR(64) NOT NULL DEFAULT '',
	`trial` TINYINT NOT NULL DEFAULT 0,
	`sis` VARCHAR(64) NOT NULL DEFAULT '',
	`home_pg_msg` TEXT NULL,
	`contact_name` VARCHAR(255) NOT NULL DEFAULT '',
	`contact_email` VARCHAR(255) NOT NULL DEFAULT '',
	`contact_add1` VARCHAR(255) NOT NULL DEFAULT '',
	`contact_add2` VARCHAR(255) NOT NULL DEFAULT '',
	`contact_city` VARCHAR(255) NOT NULL DEFAULT '',
	`contact_state` VARCHAR(64) NOT NULL DEFAULT '',
	`contact_zip` VARCHAR(32) NOT NULL DEFAULT '',
	`contact_phone` VARCHAR(64) NOT NULL DEFAULT '',
	`contact_fax` VARCHAR(64) NOT NULL DEFAULT '',
	`sponsor_invoice_msg` TEXT NULL,
	`pymt_cc_msg` TEXT NULL,
	`pymt_po_msg` TEXT NULL,
	`pymt_chk_msg` TEXT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_accounts_contact_email` (`contact_email`),
	KEY `idx_accounts_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: account_fee_structure
CREATE TABLE IF NOT EXISTS `account_fee_structure` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`reg_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`reg_fee_method` VARCHAR(32) NOT NULL DEFAULT 'flat',
	`reg_charge_to` VARCHAR(32) NOT NULL DEFAULT 'attendee',
	`reg_charge_zero_items` TINYINT NOT NULL DEFAULT 0,
	`ticket_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`ticket_fee_method` VARCHAR(32) NOT NULL DEFAULT 'flat',
	`ticket_charge_to` VARCHAR(32) NOT NULL DEFAULT 'attendee',
	`ticket_charge_zero_items` TINYINT NOT NULL DEFAULT 0,
	`start_date` DATE NULL,
	`end_date` DATE NULL,
	PRIMARY KEY (`id`),
	KEY `idx_account_fee_structure_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: account_magicwrighter_info
CREATE TABLE IF NOT EXISTS `account_magicwrighter_info` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`enable_online_pymts` VARCHAR(8) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `idx_account_magicwrighter_info_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: account_reg_types
CREATE TABLE IF NOT EXISTS `account_reg_types` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`price` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`sortorder` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_account_reg_types_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: security_groups
CREATE TABLE IF NOT EXISTS `security_groups` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`account_pages` TEXT NULL,
	`event_pages` TEXT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_security_groups_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: users
CREATE TABLE IF NOT EXISTS `users` (
	`id` INT NOT NULL,
	`accountid` INT NOT NULL DEFAULT 0,
	`first_name` VARCHAR(255) NOT NULL DEFAULT '',
	`last_name` VARCHAR(255) NOT NULL DEFAULT '',
	`email` VARCHAR(255) NOT NULL DEFAULT '',
	`pass` VARCHAR(255) NOT NULL DEFAULT '',
	`master` TINYINT NOT NULL DEFAULT 0,
	`archived` TINYINT NOT NULL DEFAULT 0,
	`security_groups` VARCHAR(255) NOT NULL DEFAULT '',
	`pages` TEXT NULL,
	`events` TEXT NULL,
	`sponsorid` INT NOT NULL DEFAULT 0,
	`business` VARCHAR(255) NOT NULL DEFAULT '',
	`address1` VARCHAR(255) NOT NULL DEFAULT '',
	`address2` VARCHAR(255) NOT NULL DEFAULT '',
	`city` VARCHAR(255) NOT NULL DEFAULT '',
	`state` VARCHAR(64) NOT NULL DEFAULT '',
	`zip` VARCHAR(32) NOT NULL DEFAULT '',
	`phone` VARCHAR(64) NOT NULL DEFAULT '',
	`bio` TEXT NULL,
	`photo` VARCHAR(255) NOT NULL DEFAULT '',
	`web_address` VARCHAR(255) NOT NULL DEFAULT '',
	`courses_preferred` VARCHAR(255) NOT NULL DEFAULT '',
	`courses_able` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_users_accountid` (`accountid`),
	KEY `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_event
CREATE TABLE IF NOT EXISTS `user_event` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`userid` INT NOT NULL DEFAULT 0,
	`eventid` INT NOT NULL DEFAULT 0,
	`request_denied` TINYINT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_user_event_eventid` (`eventid`),
	KEY `idx_user_event_userid` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_colmodel
CREATE TABLE IF NOT EXISTS `user_colmodel` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`userid` INT NOT NULL DEFAULT 0,
	`eventid` INT NULL DEFAULT NULL,
	`page` VARCHAR(128) NOT NULL DEFAULT '',
	`colmodel` TEXT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_user_colmodel_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: preferences
CREATE TABLE IF NOT EXISTS `preferences` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`value` TEXT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_preferences_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: attendees
CREATE TABLE IF NOT EXISTS `attendees` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`first_name` VARCHAR(255) NOT NULL DEFAULT '',
	`last_name` VARCHAR(255) NOT NULL DEFAULT '',
	`email` VARCHAR(255) NOT NULL DEFAULT '',
	`password` VARCHAR(255) NOT NULL DEFAULT '',
	`business` VARCHAR(255) NOT NULL DEFAULT '',
	`address1` VARCHAR(255) NOT NULL DEFAULT '',
	`address2` VARCHAR(255) NOT NULL DEFAULT '',
	`city` VARCHAR(255) NOT NULL DEFAULT '',
	`state` VARCHAR(64) NOT NULL DEFAULT '',
	`zip` VARCHAR(32) NOT NULL DEFAULT '',
	`title` VARCHAR(255) NOT NULL DEFAULT '',
	`phone` VARCHAR(64) NOT NULL DEFAULT '',
	`web_address` VARCHAR(255) NOT NULL DEFAULT '',
	`dietary_restrictions` VARCHAR(255) NOT NULL DEFAULT '',
	`ec1_email` VARCHAR(255) NOT NULL DEFAULT '',
	`ec1_name` VARCHAR(255) NOT NULL DEFAULT '',
	`ec1_phone_prim` VARCHAR(64) NOT NULL DEFAULT '',
	`ec1_phone_alt` VARCHAR(64) NOT NULL DEFAULT '',
	`ec2_email` VARCHAR(255) NOT NULL DEFAULT '',
	`ec2_name` VARCHAR(255) NOT NULL DEFAULT '',
	`ec2_phone_prim` VARCHAR(64) NOT NULL DEFAULT '',
	`ec2_phone_alt` VARCHAR(64) NOT NULL DEFAULT '',
	`terms_agreed` TINYINT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_attendees_accountid` (`accountid`),
	KEY `idx_attendees_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: students
CREATE TABLE IF NOT EXISTS `students` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`student_number` VARCHAR(64) NOT NULL DEFAULT '',
	`first_name` VARCHAR(255) NOT NULL DEFAULT '',
	`last_name` VARCHAR(255) NOT NULL DEFAULT '',
	`email` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_students_accountid` (`accountid`),
	KEY `idx_students_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: events
CREATE TABLE IF NOT EXISTS `events` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`slug` VARCHAR(255) NOT NULL DEFAULT '',
	`startdate` DATETIME NULL,
	`enddate` DATETIME NULL,
	`start_time` VARCHAR(32) NOT NULL DEFAULT '',
	`city` VARCHAR(255) NOT NULL DEFAULT '',
	`state` VARCHAR(64) NOT NULL DEFAULT '',
	`site` VARCHAR(255) NOT NULL DEFAULT '',
	`logo` VARCHAR(255) NOT NULL DEFAULT '',
	`blurb` TEXT NULL,
	`visible` VARCHAR(8) NOT NULL DEFAULT '1',
	`archived` VARCHAR(8) NOT NULL DEFAULT '0',
	`hide_after_start` VARCHAR(8) NOT NULL DEFAULT '0',
	`replytoemail` VARCHAR(255) NOT NULL DEFAULT '',
	`showschedule` TINYINT NOT NULL DEFAULT 1,
	`showMasterSched` TINYINT NOT NULL DEFAULT 1,
	`showdocumentation` TINYINT NOT NULL DEFAULT 1,
	`kioskinvoice` TINYINT NOT NULL DEFAULT 0,
	`kioskcertificate` TINYINT NOT NULL DEFAULT 0,
	`courseCatalogMsg` TEXT NULL,
	`extras_cap` INT NOT NULL DEFAULT 0,
	`allow_dup_attendee` TINYINT NOT NULL DEFAULT 0,
	`apply_cc_fee` TINYINT NOT NULL DEFAULT 0,
	`students_only` TINYINT NOT NULL DEFAULT 0,
	`student_restrictions` TEXT NULL,
	`survey_date` DATE NULL,
	`registrationstartdate` DATE NULL,
	`registrationenddate` DATE NULL,
	`allowsignups` TINYINT NOT NULL DEFAULT 1,
	`requireponumber` TINYINT NOT NULL DEFAULT 0,
	`registration_message` TEXT NULL,
	`registration_message_primary` TEXT NULL,
	`pymt_cc_msg` TEXT NULL,
	`pymt_po_msg` TEXT NULL,
	`pymt_chk_msg` TEXT NULL,
	`req_cc_pymt` TINYINT NOT NULL DEFAULT 0,
	`allow_partial_pymt` TINYINT NOT NULL DEFAULT 0,
	`sched_after_reg` TINYINT NOT NULL DEFAULT 0,
	`capacity` INT NOT NULL DEFAULT 0,
	`prefix` VARCHAR(32) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_events_accountid` (`accountid`),
	KEY `idx_events_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: pages
CREATE TABLE IF NOT EXISTS `pages` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`slug` VARCHAR(255) NOT NULL DEFAULT '',
	`home` TINYINT NOT NULL DEFAULT 0,
	`show_registrants` TINYINT NOT NULL DEFAULT 0,
	`content` MEDIUMTEXT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_pages_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: event_master_sched_pages
CREATE TABLE IF NOT EXISTS `event_master_sched_pages` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`sortorder` INT NOT NULL DEFAULT 0,
	`content` MEDIUMTEXT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_event_master_sched_pages_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tracks
CREATE TABLE IF NOT EXISTS `tracks` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_tracks_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rooms
CREATE TABLE IF NOT EXISTS `rooms` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`subname` VARCHAR(255) NOT NULL DEFAULT '',
	`area` VARCHAR(255) NOT NULL DEFAULT '',
	`capacity` INT NOT NULL DEFAULT 0,
	`sortorder` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_rooms_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sessions
CREATE TABLE IF NOT EXISTS `sessions` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`starttime` DATETIME NULL,
	`endtime` DATETIME NULL,
	PRIMARY KEY (`id`),
	KEY `idx_sessions_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: courses
CREATE TABLE IF NOT EXISTS `courses` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`abbreviation` VARCHAR(64) NOT NULL DEFAULT '',
	`description` TEXT NULL,
	`sponsorid` INT NULL DEFAULT NULL,
	`excludefromschedule` TINYINT NOT NULL DEFAULT 0,
	`excludefromdocs` TINYINT NOT NULL DEFAULT 0,
	`archived` TINYINT NOT NULL DEFAULT 0,
	`color` VARCHAR(32) NOT NULL DEFAULT '',
	`created_time` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_courses_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: course_tracks
CREATE TABLE IF NOT EXISTS `course_tracks` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`courseid` INT NOT NULL DEFAULT 0,
	`trackid` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_course_tracks_courseid` (`courseid`),
	KEY `idx_course_tracks_trackid` (`trackid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: events_courses
CREATE TABLE IF NOT EXISTS `events_courses` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`courseid` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_events_courses_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sections
CREATE TABLE IF NOT EXISTS `sections` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`courseid` INT NOT NULL DEFAULT 0,
	`sessionid` INT NOT NULL DEFAULT 0,
	`roomid` INT NOT NULL DEFAULT 0,
	`capacity` INT NOT NULL DEFAULT 0,
	`is_virtual` TINYINT NOT NULL DEFAULT 0,
	`additionalsessions` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_sections_sessionid` (`sessionid`),
	KEY `idx_sections_courseid` (`courseid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: section_presenters
CREATE TABLE IF NOT EXISTS `section_presenters` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`sectionid` INT NOT NULL DEFAULT 0,
	`userid` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_section_presenters_sectionid` (`sectionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: section_sessions
CREATE TABLE IF NOT EXISTS `section_sessions` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`sectionid` INT NOT NULL DEFAULT 0,
	`sessionid` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_section_sessions_sessionid` (`sessionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: registration_types
CREATE TABLE IF NOT EXISTS `registration_types` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`price` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`sunrise` DATE NULL,
	`sunset` DATE NULL,
	`sortorder` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_registration_types_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: registration_fields
CREATE TABLE IF NOT EXISTS `registration_fields` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`eventid` INT NOT NULL DEFAULT 0,
	`label` VARCHAR(255) NOT NULL DEFAULT '',
	`type` VARCHAR(64) NOT NULL DEFAULT 'text',
	`options` TEXT NULL,
	`sortorder` INT NOT NULL DEFAULT 0,
	`archived` TINYINT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_registration_fields_accountid` (`accountid`),
	KEY `idx_registration_fields_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: registration_field_exclusions
CREATE TABLE IF NOT EXISTS `registration_field_exclusions` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`registration_fields_id` INT NOT NULL DEFAULT 0,
	`default_field` VARCHAR(64) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_registration_field_exclusions_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: registration_messages
CREATE TABLE IF NOT EXISTS `registration_messages` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`message` TEXT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_registration_messages_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: registration_extras
CREATE TABLE IF NOT EXISTS `registration_extras` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`price` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`capacity` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_registration_extras_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: registrations
CREATE TABLE IF NOT EXISTS `registrations` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`attendeeid` INT NOT NULL DEFAULT 0,
	`userid` INT NOT NULL DEFAULT 0,
	`registration_typeid` INT NOT NULL DEFAULT 0,
	`confirmation` VARCHAR(64) NOT NULL DEFAULT '',
	`deleted` TINYINT NOT NULL DEFAULT 0,
	`checkin` DATETIME NULL,
	`checkin_userid` INT NOT NULL DEFAULT 0,
	`vendor_access` TINYINT NOT NULL DEFAULT 0,
	`payment_method` VARCHAR(32) NOT NULL DEFAULT '',
	`payment_number` VARCHAR(255) NOT NULL DEFAULT '',
	`discount_code` VARCHAR(64) NOT NULL DEFAULT '',
	`season_pass_ordersid` INT NULL DEFAULT NULL,
	`create_date` DATETIME NULL,
	`student_number` VARCHAR(64) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_registrations_eventid` (`eventid`),
	KEY `idx_registrations_attendeeid` (`attendeeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: registration_data
CREATE TABLE IF NOT EXISTS `registration_data` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`registrationid` INT NOT NULL DEFAULT 0,
	`registration_fieldid` INT NOT NULL DEFAULT 0,
	`data` TEXT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_registration_data_registrationid` (`registrationid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: registration_extra_orders
CREATE TABLE IF NOT EXISTS `registration_extra_orders` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`registrationid` INT NOT NULL DEFAULT 0,
	`registration_extras_id` INT NOT NULL DEFAULT 0,
	`qty` INT NOT NULL DEFAULT 1,
	`redeemed` TINYINT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_registration_extra_orders_registrationid` (`registrationid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: registration_payments
CREATE TABLE IF NOT EXISTS `registration_payments` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`registrationid` INT NOT NULL DEFAULT 0,
	`amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`cc_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`payment_type` VARCHAR(64) NOT NULL DEFAULT '',
	`ref_nbr` VARCHAR(128) NOT NULL DEFAULT '',
	`entered_by` INT NOT NULL DEFAULT 0,
	`entered_date` DATETIME NULL,
	`note` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_registration_payments_registrationid` (`registrationid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: signups
CREATE TABLE IF NOT EXISTS `signups` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`registrationid` INT NOT NULL DEFAULT 0,
	`sessionid` INT NOT NULL DEFAULT 0,
	`sectionid` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_signups_registrationid` (`registrationid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: discount_codes
CREATE TABLE IF NOT EXISTS `discount_codes` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`accountid` INT NOT NULL DEFAULT 0,
	`code` VARCHAR(64) NOT NULL DEFAULT '',
	`type` VARCHAR(32) NOT NULL DEFAULT 'attendee',
	`method` VARCHAR(32) NOT NULL DEFAULT 'amount', -- queries.php: amount vs percent
	`discount` DECIMAL(10,2) NOT NULL DEFAULT 0, -- UI field name (not amount)
	`amount` DECIMAL(10,2) NOT NULL DEFAULT 0, -- kept for stub schemas that already had amount
	`frequency` VARCHAR(32) NOT NULL DEFAULT 'unlimited',
	`sunrise` DATE NULL,
	`sunset` DATE NULL,
	PRIMARY KEY (`id`),
	KEY `idx_discount_codes_eventid` (`eventid`),
	KEY `idx_discount_codes_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: season_passes
CREATE TABLE IF NOT EXISTS `season_passes` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`price` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`sunrise` DATE NULL,
	`sunset` DATE NULL,
	`description` TEXT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_season_passes_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: season_pass_orders
CREATE TABLE IF NOT EXISTS `season_pass_orders` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`attendeeid` INT NOT NULL DEFAULT 0,
	`order_details` TEXT NULL,
	`amt_charged` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`cc_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`ref_nbr` VARCHAR(128) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_season_pass_orders_accountid` (`accountid`),
	KEY `idx_season_pass_orders_attendeeid` (`attendeeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sponsors
CREATE TABLE IF NOT EXISTS `sponsors` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`email` VARCHAR(255) NOT NULL DEFAULT '',
	`username` VARCHAR(255) NOT NULL DEFAULT '',
	`pass` VARCHAR(255) NOT NULL DEFAULT '',
	`web_address` VARCHAR(255) NOT NULL DEFAULT '',
	`archived` TINYINT NOT NULL DEFAULT 0,
	`phone` VARCHAR(64) NOT NULL DEFAULT '',
	`address1` VARCHAR(255) NOT NULL DEFAULT '',
	`city` VARCHAR(255) NOT NULL DEFAULT '',
	`state` VARCHAR(64) NOT NULL DEFAULT '',
	`zip` VARCHAR(32) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_sponsors_accountid` (`accountid`),
	KEY `idx_sponsors_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: event_sponsor_options
CREATE TABLE IF NOT EXISTS `event_sponsor_options` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`type` VARCHAR(64) NOT NULL DEFAULT 'event vendor',
	`price` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`staff_allowance` INT NOT NULL DEFAULT 0,
	`registration_type_id` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_event_sponsor_options_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: vendor_orders
CREATE TABLE IF NOT EXISTS `vendor_orders` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`sponsorid` INT NOT NULL DEFAULT 0,
	`cancelled` TINYINT NOT NULL DEFAULT 0,
	`total` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`notes` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_vendor_orders_sponsorid` (`sponsorid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: vendor_order_details
CREATE TABLE IF NOT EXISTS `vendor_order_details` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`vendor_orders_id` INT NOT NULL DEFAULT 0,
	`event_sponsor_options_id` INT NOT NULL DEFAULT 0,
	`qty` INT NOT NULL DEFAULT 1,
	PRIMARY KEY (`id`),
	KEY `idx_vendor_order_details_vendor_orders_id` (`vendor_orders_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: vendor_payments
CREATE TABLE IF NOT EXISTS `vendor_payments` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`sponsorid` INT NOT NULL DEFAULT 0,
	`eventid` INT NOT NULL DEFAULT 0,
	`vendor_orders_id` INT NOT NULL DEFAULT 0,
	`amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`method` VARCHAR(32) NOT NULL DEFAULT '',
	`cc_confirmation_number` VARCHAR(128) NOT NULL DEFAULT '',
	`entered_by` INT NOT NULL DEFAULT 0,
	`payment_date` DATETIME NULL,
	`note` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_vendor_payments_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sponsor_packages
CREATE TABLE IF NOT EXISTS `sponsor_packages` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`price` DECIMAL(10,2) NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_sponsor_packages_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sponsor_pkg_opt_assoc
CREATE TABLE IF NOT EXISTS `sponsor_pkg_opt_assoc` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`sponsor_packagesid` INT NOT NULL DEFAULT 0,
	`event_sponsor_options_id` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sponsor_orders
CREATE TABLE IF NOT EXISTS `sponsor_orders` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`sponsorid` INT NOT NULL DEFAULT 0,
	`sponsor_packagesid` INT NOT NULL DEFAULT 0,
	`total` DECIMAL(10,2) NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sponsor_payments
CREATE TABLE IF NOT EXISTS `sponsor_payments` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`sponsorid` INT NOT NULL DEFAULT 0,
	`amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`method` VARCHAR(32) NOT NULL DEFAULT '',
	`ref_nbr` VARCHAR(128) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sponsor_pending_pymts
CREATE TABLE IF NOT EXISTS `sponsor_pending_pymts` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`sponsorid` INT NOT NULL DEFAULT 0,
	`amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`status` VARCHAR(32) NOT NULL DEFAULT 'pending',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tep_vendor_booths
CREATE TABLE IF NOT EXISTS `tep_vendor_booths` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`accountid` INT UNSIGNED NOT NULL DEFAULT 0,
	`sponsorid` INT UNSIGNED NOT NULL DEFAULT 0,
	`eventid` INT UNSIGNED NULL DEFAULT NULL,
	`vendor_name` VARCHAR(255) NOT NULL DEFAULT '',
	`booth` VARCHAR(64) NOT NULL DEFAULT '',
	`hall` VARCHAR(128) NOT NULL DEFAULT '',
	`notes` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_tep_vendor_booth_acct` (`accountid`, `sponsorid`),
	KEY `idx_tep_vendor_booth_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tep_vendor_leads
CREATE TABLE IF NOT EXISTS `tep_vendor_leads` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`accountid` INT UNSIGNED NOT NULL DEFAULT 0,
	`sponsorid` INT UNSIGNED NOT NULL DEFAULT 0,
	`eventid` INT UNSIGNED NULL DEFAULT NULL,
	`attendee_name` VARCHAR(255) NOT NULL DEFAULT '',
	`email` VARCHAR(255) NOT NULL DEFAULT '',
	`company` VARCHAR(255) NOT NULL DEFAULT '',
	`ticket` VARCHAR(64) NOT NULL DEFAULT '',
	`notes` VARCHAR(500) NOT NULL DEFAULT '',
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_tep_vendor_leads_acct` (`accountid`, `sponsorid`),
	KEY `idx_tep_vendor_leads_eventid` (`eventid`),
	KEY `idx_tep_vendor_leads_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: survey_questions
CREATE TABLE IF NOT EXISTS `survey_questions` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`text` VARCHAR(500) NOT NULL DEFAULT '',
	`type` VARCHAR(32) NOT NULL DEFAULT 'numeric',
	`options` TEXT NULL,
	`assoc` VARCHAR(32) NOT NULL DEFAULT 'event',
	`sortorder` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_survey_questions_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: survey_evt_association
CREATE TABLE IF NOT EXISTS `survey_evt_association` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`eventid` INT NOT NULL DEFAULT 0,
	`questionid` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_survey_evt_association_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: survey_responses
CREATE TABLE IF NOT EXISTS `survey_responses` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`registrationid` INT NOT NULL DEFAULT 0,
	`eventid` INT NOT NULL DEFAULT 0,
	`questionid` INT NOT NULL DEFAULT 0,
	`response` TEXT NULL,
	`sectionid` INT NULL DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_survey_responses_eventid` (`eventid`),
	KEY `idx_survey_responses_registrationid` (`registrationid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tep_polls
CREATE TABLE IF NOT EXISTS `tep_polls` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`accountid` INT UNSIGNED NOT NULL DEFAULT 0,
	`eventid` INT UNSIGNED NULL DEFAULT NULL,
	`question` VARCHAR(500) NOT NULL,
	`options_json` TEXT NOT NULL,
	`is_active` TINYINT(1) NOT NULL DEFAULT 1,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_tep_polls_active` (`accountid`, `is_active`, `eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tep_poll_votes
CREATE TABLE IF NOT EXISTS `tep_poll_votes` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`pollid` INT UNSIGNED NOT NULL,
	`option_index` TINYINT UNSIGNED NOT NULL,
	`voter_key` VARCHAR(64) NOT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uk_tep_poll_votes_voter` (`pollid`, `voter_key`),
	KEY `idx_tep_poll_votes_poll` (`pollid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tep_push_subscriptions
CREATE TABLE IF NOT EXISTS `tep_push_subscriptions` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`accountid` INT UNSIGNED NOT NULL DEFAULT 0,
	`userid` INT UNSIGNED NULL DEFAULT NULL,
	`endpoint` VARCHAR(512) NOT NULL,
	`p256dh` VARCHAR(255) NOT NULL,
	`auth` VARCHAR(255) NOT NULL,
	`user_agent` VARCHAR(255) NOT NULL DEFAULT '',
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uk_tep_push_endpoint` (`endpoint`),
	KEY `idx_tep_push_account` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: documents
CREATE TABLE IF NOT EXISTS `documents` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`filepath` VARCHAR(512) NOT NULL DEFAULT '',
	`author` VARCHAR(255) NOT NULL DEFAULT '',
	`description` TEXT NULL,
	`status` VARCHAR(16) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `idx_documents_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: document_association
CREATE TABLE IF NOT EXISTS `document_association` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`documentid` INT NOT NULL DEFAULT 0,
	`accountid` INT NOT NULL DEFAULT 0,
	`eventid` INT NULL DEFAULT NULL,
	`courseid` INT NULL DEFAULT NULL,
	`videoid` INT NULL DEFAULT NULL,
	`sessionid` INT NULL DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_document_association_eventid` (`eventid`),
	KEY `idx_document_association_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: videos
CREATE TABLE IF NOT EXISTS `videos` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`display_name` VARCHAR(255) NOT NULL DEFAULT '',
	`filepath` VARCHAR(512) NOT NULL DEFAULT '',
	`published_lc` TINYINT NOT NULL DEFAULT 0,
	`price` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`rental_duration` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_videos_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: video_association
CREATE TABLE IF NOT EXISTS `video_association` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`videoid` INT NOT NULL DEFAULT 0,
	`courseid` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: video_packages
CREATE TABLE IF NOT EXISTS `video_packages` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`price` DECIMAL(10,2) NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_video_packages_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: video_package_details
CREATE TABLE IF NOT EXISTS `video_package_details` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`video_packagesid` INT NOT NULL DEFAULT 0,
	`videoid` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: video_orders
CREATE TABLE IF NOT EXISTS `video_orders` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`attendeeid` INT NOT NULL DEFAULT 0,
	`total` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`discount` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`discount_code` VARCHAR(64) NOT NULL DEFAULT '',
	`paid` TINYINT NOT NULL DEFAULT 0,
	`payment_number` VARCHAR(128) NOT NULL DEFAULT '',
	`created_time` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_video_orders_accountid` (`accountid`),
	KEY `idx_video_orders_attendeeid` (`attendeeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: video_order_details
CREATE TABLE IF NOT EXISTS `video_order_details` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`video_ordersid` INT NOT NULL DEFAULT 0,
	`videoid` INT NULL DEFAULT NULL,
	`video_packagesid` INT NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: video_reviews
CREATE TABLE IF NOT EXISTS `video_reviews` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`videoid` INT NULL DEFAULT NULL,
	`video_packagesid` INT NULL DEFAULT NULL,
	`rating` INT NOT NULL DEFAULT 0,
	`comment` TEXT NULL,
	`attendeeid` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_video_reviews_attendeeid` (`attendeeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: expense_categories
CREATE TABLE IF NOT EXISTS `expense_categories` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`max` DECIMAL(10,2) NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_expense_categories_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: staff_expenses
CREATE TABLE IF NOT EXISTS `staff_expenses` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`userid` INT NOT NULL DEFAULT 0,
	`eventid` INT NOT NULL DEFAULT 0,
	`expense_category_id` INT NOT NULL DEFAULT 0,
	`description` VARCHAR(255) NOT NULL DEFAULT '',
	`amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`approved_amt` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`receipt` VARCHAR(255) NOT NULL DEFAULT '',
	`expense_date` DATE NULL,
	PRIMARY KEY (`id`),
	KEY `idx_staff_expenses_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: staff_payments
CREATE TABLE IF NOT EXISTS `staff_payments` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`staff_expense_id` INT NOT NULL DEFAULT 0,
	`amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`method` VARCHAR(64) NOT NULL DEFAULT '',
	`date_value` DATE NULL,
	`note` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: inventory
CREATE TABLE IF NOT EXISTS `inventory` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`category` VARCHAR(128) NOT NULL DEFAULT '',
	`type` VARCHAR(128) NOT NULL DEFAULT '',
	`brand` VARCHAR(128) NOT NULL DEFAULT '',
	`model` VARCHAR(128) NOT NULL DEFAULT '',
	`serial_number` VARCHAR(128) NOT NULL DEFAULT '',
	`notes` TEXT NULL,
	`item_condition` VARCHAR(64) NOT NULL DEFAULT '',
	`location` VARCHAR(128) NOT NULL DEFAULT '',
	`purchase_date` DATE NULL,
	`purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`retired` TINYINT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_inventory_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: emails_sent
CREATE TABLE IF NOT EXISTS `emails_sent` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`eventid` INT NOT NULL DEFAULT 0,
	`subject` VARCHAR(255) NOT NULL DEFAULT '',
	`body` TEXT NULL,
	`recipients` TEXT NULL,
	`sent_date` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_emails_sent_accountid` (`accountid`),
	KEY `idx_emails_sent_eventid` (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: support_tickets
CREATE TABLE IF NOT EXISTS `support_tickets` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`subject` VARCHAR(255) NOT NULL DEFAULT '',
	`description` TEXT NULL,
	`status` VARCHAR(32) NOT NULL DEFAULT 'open',
	`created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_support_tickets_accountid` (`accountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: acme_access_requests
CREATE TABLE IF NOT EXISTS `acme_access_requests` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`accountid` INT NOT NULL DEFAULT 0,
	`email` VARCHAR(255) NOT NULL DEFAULT '',
	`first_name` VARCHAR(255) NOT NULL DEFAULT '',
	`last_name` VARCHAR(255) NOT NULL DEFAULT '',
	`approved` TINYINT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_acme_access_requests_accountid` (`accountid`),
	KEY `idx_acme_access_requests_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: course_proposals
CREATE TABLE IF NOT EXISTS `course_proposals` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`userid` INT NULL DEFAULT NULL,
	`sponsorid` INT NULL DEFAULT NULL,
	`title` VARCHAR(255) NOT NULL DEFAULT '',
	`description` TEXT NULL,
	`status` VARCHAR(32) NOT NULL DEFAULT 'pending',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: regLog
CREATE TABLE IF NOT EXISTS `regLog` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`referrer` VARCHAR(512) NOT NULL DEFAULT '',
	`userAgent` VARCHAR(512) NOT NULL DEFAULT '',
	`email` VARCHAR(255) NOT NULL DEFAULT '',
	`payment_method` VARCHAR(32) NOT NULL DEFAULT '',
	`reg_type` VARCHAR(64) NOT NULL DEFAULT '',
	`acctName` VARCHAR(255) NOT NULL DEFAULT '',
	`eventName` VARCHAR(255) NOT NULL DEFAULT '',
	`cc_enabled` VARCHAR(8) NOT NULL DEFAULT '',
	`req_cc_pymt` VARCHAR(8) NOT NULL DEFAULT '',
	`reg_fee` VARCHAR(32) NOT NULL DEFAULT '',
	`registrationid` INT NOT NULL DEFAULT 0,
	`confirmation` VARCHAR(64) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `idx_regLog_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Expand stub columns that already exist on tep_local without the production field list
ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `trial` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `sis` VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `pymt_cc_msg` TEXT NULL;
ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `pymt_po_msg` TEXT NULL;
ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `pymt_chk_msg` TEXT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `pages` TEXT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `events` TEXT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `sponsorid` INT NOT NULL DEFAULT 0;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `business` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `address1` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `address2` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `city` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `state` VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `zip` VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `phone` VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `bio` TEXT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `photo` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `web_address` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `courses_preferred` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `courses_able` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `password` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `business` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `address1` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `address2` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `city` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `state` VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `zip` VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `title` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `phone` VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `web_address` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `dietary_restrictions` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `ec1_email` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `ec1_name` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `ec1_phone_prim` VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `ec1_phone_alt` VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `ec2_email` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `ec2_name` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `ec2_phone_prim` VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `ec2_phone_alt` VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE `attendees` ADD COLUMN IF NOT EXISTS `terms_agreed` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `start_time` VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `site` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `replytoemail` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `showschedule` TINYINT NOT NULL DEFAULT 1;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `showMasterSched` TINYINT NOT NULL DEFAULT 1;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `showdocumentation` TINYINT NOT NULL DEFAULT 1;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `kioskinvoice` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `kioskcertificate` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `courseCatalogMsg` TEXT NULL;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `extras_cap` INT NOT NULL DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `allow_dup_attendee` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `apply_cc_fee` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `students_only` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `student_restrictions` TEXT NULL;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `survey_date` DATE NULL;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `registrationstartdate` DATE NULL;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `registrationenddate` DATE NULL;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `allowsignups` TINYINT NOT NULL DEFAULT 1;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `requireponumber` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `registration_message` TEXT NULL;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `registration_message_primary` TEXT NULL;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `pymt_cc_msg` TEXT NULL;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `pymt_po_msg` TEXT NULL;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `pymt_chk_msg` TEXT NULL;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `req_cc_pymt` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `allow_partial_pymt` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `sched_after_reg` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `capacity` INT NOT NULL DEFAULT 0;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `prefix` VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE `pages` ADD COLUMN IF NOT EXISTS `name` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `pages` ADD COLUMN IF NOT EXISTS `show_registrants` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `pages` ADD COLUMN IF NOT EXISTS `content` MEDIUMTEXT NULL;
ALTER TABLE `registrations` ADD COLUMN IF NOT EXISTS `userid` INT NOT NULL DEFAULT 0;
ALTER TABLE `registrations` ADD COLUMN IF NOT EXISTS `vendor_access` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `registrations` ADD COLUMN IF NOT EXISTS `payment_method` VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE `registrations` ADD COLUMN IF NOT EXISTS `payment_number` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `registrations` ADD COLUMN IF NOT EXISTS `discount_code` VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE `registrations` ADD COLUMN IF NOT EXISTS `season_pass_ordersid` INT NULL DEFAULT NULL;
ALTER TABLE `registrations` ADD COLUMN IF NOT EXISTS `create_date` DATETIME NULL;
ALTER TABLE `registrations` ADD COLUMN IF NOT EXISTS `student_number` VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE `season_passes` ADD COLUMN IF NOT EXISTS `name` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `season_passes` ADD COLUMN IF NOT EXISTS `price` DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE `season_passes` ADD COLUMN IF NOT EXISTS `description` TEXT NULL;
ALTER TABLE `discount_codes` ADD COLUMN IF NOT EXISTS `method` VARCHAR(32) NOT NULL DEFAULT 'amount'; -- amount | percent
ALTER TABLE `discount_codes` ADD COLUMN IF NOT EXISTS `discount` DECIMAL(10,2) NOT NULL DEFAULT 0; -- matches AngularJS record.discount

-- B-tree indexes on tenant filter columns (skip if the index name already exists)
DROP PROCEDURE IF EXISTS tep_seed_add_index;
DELIMITER //
CREATE PROCEDURE tep_seed_add_index(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_cols VARCHAR(255))
BEGIN
	IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = p_table)
	AND NOT EXISTS (
		SELECT 1 FROM information_schema.statistics
		WHERE table_schema = DATABASE() AND table_name = p_table AND index_name = p_index
	) THEN
		SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_cols, ')');
		PREPARE stmt FROM @sql;
		EXECUTE stmt;
		DEALLOCATE PREPARE stmt;
	END IF;
END //
DELIMITER ;

CALL tep_seed_add_index('accounts','idx_accounts_contact_email','contact_email');
CALL tep_seed_add_index('users','idx_users_accountid','accountid');
CALL tep_seed_add_index('users','idx_users_email','email');
CALL tep_seed_add_index('attendees','idx_attendees_accountid','accountid');
CALL tep_seed_add_index('attendees','idx_attendees_email','email');
CALL tep_seed_add_index('events','idx_events_accountid','accountid');
CALL tep_seed_add_index('registrations','idx_registrations_eventid','eventid');
CALL tep_seed_add_index('registrations','idx_registrations_attendeeid','attendeeid');
CALL tep_seed_add_index('sponsors','idx_sponsors_accountid','accountid');
CALL tep_seed_add_index('sponsors','idx_sponsors_email','email');
CALL tep_seed_add_index('pages','idx_pages_eventid','eventid');
CALL tep_seed_add_index('preferences','idx_preferences_accountid','accountid');
CALL tep_seed_add_index('documents','idx_documents_accountid','accountid');
CALL tep_seed_add_index('signups','idx_signups_registrationid','registrationid');
CALL tep_seed_add_index('courses','idx_courses_accountid','accountid');
CALL tep_seed_add_index('sessions','idx_sessions_eventid','eventid');
CALL tep_seed_add_index('rooms','idx_rooms_eventid','eventid');
CALL tep_seed_add_index('tracks','idx_tracks_accountid','accountid');
CALL tep_seed_add_index('security_groups','idx_security_groups_accountid','accountid');
CALL tep_seed_add_index('survey_questions','idx_survey_questions_accountid','accountid');
CALL tep_seed_add_index('inventory','idx_inventory_accountid','accountid');
CALL tep_seed_add_index('emails_sent','idx_emails_sent_accountid','accountid');
CALL tep_seed_add_index('students','idx_students_accountid','accountid');
CALL tep_seed_add_index('students','idx_students_email','email');
CALL tep_seed_add_index('tep_vendor_leads','idx_tep_vendor_leads_eventid','eventid');
CALL tep_seed_add_index('tep_vendor_leads','idx_tep_vendor_leads_email','email');
CALL tep_seed_add_index('tep_vendor_booths','idx_tep_vendor_booth_eventid','eventid');
DROP PROCEDURE IF EXISTS tep_seed_add_index;

-- ===================== DATA: tenant 1000 Phoenix Enterprise Events =====================
-- FK map (logical): accounts 1-N users/attendees/sponsors/events/preferences
-- events 1-N pages/rooms/sessions/registration_types/registrations
-- courses N-M tracks via course_tracks; events N-M courses via events_courses
-- sections N-M presenters via section_presenters; sections N-M sessions via section_sessions
-- registrations 1-N signups/payments/extra_orders/survey_responses
-- sponsors 1-N vendor_orders; vendor_orders 1-N vendor_order_details
-- surveys: survey_questions N-M events via survey_evt_association

INSERT INTO accounts (id, name, slug, web_logo, sponsors_enabled, disabled, type, trial, sis, home_pg_msg, contact_name, contact_email, contact_add1, contact_city, contact_state, contact_zip, contact_phone, sponsor_invoice_msg)
VALUES (1000, 'Phoenix Enterprise Events', 'phoenix-enterprise', '/css/tep-logo.svg', '1', 0, 'conference', 0, '', 'Welcome to Phoenix Enterprise Events.', 'Alex Rivera', 'ops@phoenix-enterprise.example', '100 Summit Way', 'Phoenix', 'AZ', '85001', '602-555-0100', 'Thank you for exhibiting.')
ON DUPLICATE KEY UPDATE name=VALUES(name), slug=VALUES(slug), sponsors_enabled=VALUES(sponsors_enabled), disabled=VALUES(disabled);

INSERT INTO account_fee_structure (id, accountid, reg_fee, reg_fee_method, reg_charge_to, reg_charge_zero_items, ticket_fee, ticket_fee_method, ticket_charge_to, ticket_charge_zero_items, start_date, end_date)
VALUES (1001, 1000, 2.50, 'flat', 'attendee', 0, 1.00, 'flat', 'attendee', 0, '2026-01-01', '2026-12-31')
ON DUPLICATE KEY UPDATE accountid=VALUES(accountid), reg_fee=VALUES(reg_fee);

INSERT INTO account_magicwrighter_info (id, accountid, enable_online_pymts)
VALUES (1001, 1000, '0')
ON DUPLICATE KEY UPDATE enable_online_pymts=VALUES(enable_online_pymts);

INSERT INTO account_reg_types (id, accountid, name, price, sortorder)
VALUES (1001, 1000, 'Member', 199.00, 1), (1002, 1000, 'Non-Member', 299.00, 2)
ON DUPLICATE KEY UPDATE name=VALUES(name), price=VALUES(price);

INSERT INTO security_groups (id, accountid, name, account_pages, event_pages)
VALUES (1001, 1000, 'Event Operations', 'event_management,document_management,expense_management', 'manage_schedule,registrations,course_catalog,master_schedule')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO users (id, accountid, first_name, last_name, email, pass, master, archived, security_groups, pages, events, sponsorid, business, city, state, zip, phone, bio)
VALUES
(1001, 1000, 'Alex', 'Rivera', 'admin@phoenix-enterprise.example', '$2y$10$Da2xpEmNg8p3QKbwJJvJG.1XJ//81yuQ0UGGdx34RRcrehBlRqS0i', 1, 0, '', 'event_management,document_management,expense_management,users,security_groups', '', 0, 'Phoenix Enterprise Events', 'Phoenix', 'AZ', '85001', '602-555-0101', 'Master account admin.'),
(1002, 1000, 'Sam', 'Chen', 'staff@phoenix-enterprise.example', '$2y$10$Da2xpEmNg8p3QKbwJJvJG.1XJ//81yuQ0UGGdx34RRcrehBlRqS0i', 0, 0, '1001', 'event_management,expense_management', '1100', 0, 'Phoenix Enterprise Events', 'Phoenix', 'AZ', '85001', '602-555-0102', 'Floor staff.'),
(1003, 1000, 'Jordan', 'Blake', 'speaker@phoenix-enterprise.example', '$2y$10$Da2xpEmNg8p3QKbwJJvJG.1XJ//81yuQ0UGGdx34RRcrehBlRqS0i', 0, 0, '1001', 'course_management', '1100', 0, 'Blake Advisory', 'Tempe', 'AZ', '85281', '480-555-0103', 'Keynote speaker.')
ON DUPLICATE KEY UPDATE email=VALUES(email), pass=VALUES(pass), master=VALUES(master);

INSERT INTO events (id, accountid, name, slug, startdate, enddate, start_time, city, state, site, logo, blurb, visible, archived, hide_after_start, replytoemail, showschedule, showMasterSched, showdocumentation, extras_cap, allowsignups, registrationstartdate, registrationenddate, survey_date, registration_message, capacity, prefix)
VALUES (1100, 1000, 'Phoenix Enterprise Summit 2026', 'phoenix-summit-2026', '2026-10-12 08:00:00', '2026-10-14 17:00:00', '08:00', 'Phoenix', 'AZ', 'Phoenix Convention Center', '/css/tep-logo.svg', 'Three-day operations summit.', '1', '0', '0', 'ops@phoenix-enterprise.example', 1, 1, 1, 4, 1, '2026-01-01', '2026-10-11', '2026-10-14', 'Welcome to the Summit.', 1200, 'PES')
ON DUPLICATE KEY UPDATE name=VALUES(name), slug=VALUES(slug), visible=VALUES(visible);

INSERT INTO user_event (id, userid, eventid, request_denied) VALUES (1101, 1002, 1100, 0), (1102, 1003, 1100, 0)
ON DUPLICATE KEY UPDATE eventid=VALUES(eventid);

INSERT INTO tracks (id, accountid, name) VALUES (1201, 1000, 'Leadership'), (1202, 1000, 'Operations')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO rooms (id, eventid, name, subname, area, capacity, sortorder)
VALUES (1301, 1100, 'Hall A', 'Main Stage', 'Grand Ballroom', 400, 1),
(1302, 1100, 'Room 12', 'Breakout', 'West Wing', 80, 2),
(1303, 1100, 'Expo 1', 'Booth floor', 'Expo Hall', 200, 3)
ON DUPLICATE KEY UPDATE name=VALUES(name), area=VALUES(area);

INSERT INTO sessions (id, eventid, name, starttime, endtime)
VALUES (1401, 1100, 'Monday Morning', '2026-10-12 09:00:00', '2026-10-12 12:00:00'),
(1402, 1100, 'Monday Afternoon', '2026-10-12 13:00:00', '2026-10-12 17:00:00')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO courses (id, accountid, name, abbreviation, description, sponsorid, excludefromschedule, excludefromdocs, archived, color)
VALUES (1501, 1000, 'Opening Keynote: Scaling Events', 'KEY1', 'Enterprise event operations keynote.', NULL, 0, 0, 0, '#1a365d'),
(1502, 1000, 'Check-In Lab', 'LAB1', 'Hands-on badge and scan workshop.', NULL, 0, 0, 0, '#2b6cb0')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO course_tracks (id, courseid, trackid) VALUES (1501, 1501, 1201), (1502, 1502, 1202)
ON DUPLICATE KEY UPDATE courseid=VALUES(courseid);

INSERT INTO events_courses (id, eventid, courseid) VALUES (1511, 1100, 1501), (1512, 1100, 1502)
ON DUPLICATE KEY UPDATE eventid=VALUES(eventid);

INSERT INTO sections (id, courseid, sessionid, roomid, capacity, is_virtual)
VALUES (1601, 1501, 1401, 1301, 400, 0), (1602, 1502, 1402, 1302, 80, 0)
ON DUPLICATE KEY UPDATE capacity=VALUES(capacity);

INSERT INTO section_presenters (id, sectionid, userid) VALUES (1611, 1601, 1003)
ON DUPLICATE KEY UPDATE userid=VALUES(userid);

INSERT INTO section_sessions (id, sectionid, sessionid) VALUES (1621, 1601, 1401), (1622, 1602, 1402)
ON DUPLICATE KEY UPDATE sessionid=VALUES(sessionid);

INSERT INTO event_master_sched_pages (id, eventid, name, sortorder, content)
VALUES (1110, 1100, 'Day 1 Grid', 1, '<p>Monday master schedule.</p>')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO pages (id, eventid, name, slug, home, show_registrants, content)
VALUES (1111, 1100, 'Home', 'home', 1, 0, '<h1>Phoenix Enterprise Summit 2026</h1><p>Welcome.</p>')
ON DUPLICATE KEY UPDATE name=VALUES(name), slug=VALUES(slug);

INSERT INTO registration_types (id, eventid, name, price, sunrise, sunset, sortorder)
VALUES (1701, 1100, 'VIP', 799.00, '2026-01-01', '2026-10-11', 1),
(1702, 1100, 'General', 399.00, '2026-01-01', '2026-10-11', 2),
(1703, 1100, 'Speaker', 0.00, '2026-01-01', '2026-10-11', 3)
ON DUPLICATE KEY UPDATE name=VALUES(name), price=VALUES(price);

INSERT INTO registration_fields (id, accountid, eventid, label, type, options, sortorder, archived)
VALUES (1710, 1000, 1100, 'T-shirt size', 'select', 'S;M;L;XL', 1, 0)
ON DUPLICATE KEY UPDATE label=VALUES(label);

INSERT INTO registration_messages (id, eventid, name, message)
VALUES (1720, 1100, 'Confirmation', 'Your Summit registration is confirmed.')
ON DUPLICATE KEY UPDATE message=VALUES(message);

INSERT INTO registration_extras (id, eventid, name, price, capacity)
VALUES (1730, 1100, 'Awards Lunch', 45.00, 200)
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO attendees (id, accountid, first_name, last_name, email, password, business, address1, city, state, zip, title, phone, terms_agreed)
VALUES
(1801, 1000, 'Morgan', 'Patel', 'vip.attendee@phoenix-enterprise.example', '$2y$10$Da2xpEmNg8p3QKbwJJvJG.1XJ//81yuQ0UGGdx34RRcrehBlRqS0i', 'Patel Group', '12 N Central', 'Phoenix', 'AZ', '85004', 'Director', '602-555-0201', 1),
(1802, 1000, 'Riley', 'Nguyen', 'general.attendee@phoenix-enterprise.example', '$2y$10$Da2xpEmNg8p3QKbwJJvJG.1XJ//81yuQ0UGGdx34RRcrehBlRqS0i', 'Nguyen Labs', '44 E Van Buren', 'Phoenix', 'AZ', '85003', 'Analyst', '602-555-0202', 1),
(1803, 1000, 'Casey', 'Ortiz', 'speaker.attendee@phoenix-enterprise.example', '$2y$10$Da2xpEmNg8p3QKbwJJvJG.1XJ//81yuQ0UGGdx34RRcrehBlRqS0i', 'Blake Advisory', '9 Mill Ave', 'Tempe', 'AZ', '85281', 'Presenter', '480-555-0203', 1)
ON DUPLICATE KEY UPDATE email=VALUES(email), password=VALUES(password);

INSERT INTO students (id, accountid, student_number, first_name, last_name, email)
VALUES (1810, 1000, 'STU-1000', 'Taylor', 'Brooks', 'student@phoenix-enterprise.example')
ON DUPLICATE KEY UPDATE email=VALUES(email);

INSERT INTO registrations (id, eventid, attendeeid, userid, registration_typeid, confirmation, deleted, checkin, checkin_userid, vendor_access, payment_method, create_date)
VALUES
(1901, 1100, 1801, 0, 1701, 'PES-VIP-1901', 0, '2026-10-12 08:15:00', 1002, 1, 'cc', '2026-09-01 10:00:00'),
(1902, 1100, 1802, 0, 1702, 'PES-GEN-1902', 0, NULL, 0, 1, 'check', '2026-09-02 11:00:00'),
(1903, 1100, 1803, 1003, 1703, 'PES-SPK-1903', 0, NULL, 0, 0, 'comp', '2026-08-15 09:00:00')
ON DUPLICATE KEY UPDATE confirmation=VALUES(confirmation), registration_typeid=VALUES(registration_typeid);

INSERT INTO registration_data (id, registrationid, registration_fieldid, data) VALUES (1910, 1901, 1710, 'L')
ON DUPLICATE KEY UPDATE data=VALUES(data);

INSERT INTO registration_extra_orders (id, registrationid, registration_extras_id, qty, redeemed) VALUES (1920, 1901, 1730, 1, 0)
ON DUPLICATE KEY UPDATE qty=VALUES(qty);

INSERT INTO registration_payments (id, registrationid, amount, cc_fee, payment_type, ref_nbr, entered_by, entered_date, note)
VALUES (1930, 1901, 799.00, 0.00, 'CC', 'STG-CC-1901', 1001, '2026-09-01 10:05:00', 'Staging VIP payment')
ON DUPLICATE KEY UPDATE amount=VALUES(amount);

INSERT INTO signups (id, registrationid, sessionid, sectionid)
VALUES (1940, 1901, 1401, 1601), (1941, 1902, 1402, 1602)
ON DUPLICATE KEY UPDATE sectionid=VALUES(sectionid);

INSERT INTO discount_codes (id, eventid, accountid, code, type, method, discount, amount, frequency, sunrise, sunset)
VALUES (1950, 1100, 1000, 'SUMMIT10', 'attendee', 'amount', 10.00, 10.00, 'unlimited', '2026-01-01', '2026-10-11')
ON DUPLICATE KEY UPDATE code=VALUES(code), method=VALUES(method), discount=VALUES(discount); -- flat $10 attendee code

INSERT INTO season_passes (id, accountid, name, price, sunrise, sunset, description)
VALUES (1960, 1000, '2026 Season Pass', 999.00, '2026-01-01', '2026-12-31', 'All Phoenix Enterprise Events in 2026.')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO season_pass_orders (id, accountid, attendeeid, order_details, amt_charged, cc_fee, ref_nbr)
VALUES (1961, 1000, 1801, '{"passId":"1960"}', 999.00, 0.00, 'STG-PASS-1961')
ON DUPLICATE KEY UPDATE amt_charged=VALUES(amt_charged);

INSERT INTO sponsors (id, accountid, name, email, username, pass, web_address, archived, phone, city, state, zip)
VALUES (2001, 1000, 'Apex Exhibitors', 'sponsor@phoenix-enterprise.example', 'apex.exhibitor', '$2y$10$Da2xpEmNg8p3QKbwJJvJG.1XJ//81yuQ0UGGdx34RRcrehBlRqS0i', 'https://apex.example', 0, '602-555-0301', 'Phoenix', 'AZ', '85034')
ON DUPLICATE KEY UPDATE email=VALUES(email), pass=VALUES(pass);

INSERT INTO users (id, accountid, first_name, last_name, email, pass, master, archived, security_groups, sponsorid, city, state)
VALUES (2002, 1000, 'Kim', 'Apex', 'sponsor.staff@phoenix-enterprise.example', '$2y$10$Da2xpEmNg8p3QKbwJJvJG.1XJ//81yuQ0UGGdx34RRcrehBlRqS0i', 0, 0, '', 2001, 'Phoenix', 'AZ')
ON DUPLICATE KEY UPDATE sponsorid=VALUES(sponsorid), pass=VALUES(pass);

INSERT INTO event_sponsor_options (id, eventid, name, type, price, staff_allowance, registration_type_id)
VALUES (2010, 1100, 'Expo Booth', 'event vendor', 2500.00, 2, 1702)
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO vendor_orders (id, sponsorid, cancelled, total, notes) VALUES (2020, 2001, 0, 2500.00, 'Staging booth order')
ON DUPLICATE KEY UPDATE total=VALUES(total);

INSERT INTO vendor_order_details (id, vendor_orders_id, event_sponsor_options_id, qty) VALUES (2021, 2020, 2010, 1)
ON DUPLICATE KEY UPDATE qty=VALUES(qty);

INSERT INTO vendor_payments (id, sponsorid, eventid, vendor_orders_id, amount, method, cc_confirmation_number, entered_by, payment_date, note)
VALUES (2022, 2001, 1100, 2020, 2500.00, 'check', '', 1001, '2026-09-05 12:00:00', 'Staging booth payment')
ON DUPLICATE KEY UPDATE amount=VALUES(amount);

INSERT INTO sponsor_packages (id, accountid, name, price) VALUES (2030, 1000, 'Gold Partner', 5000.00)
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO sponsor_pkg_opt_assoc (id, sponsor_packagesid, event_sponsor_options_id) VALUES (2031, 2030, 2010)
ON DUPLICATE KEY UPDATE event_sponsor_options_id=VALUES(event_sponsor_options_id);

INSERT INTO sponsor_orders (id, sponsorid, sponsor_packagesid, total) VALUES (2032, 2001, 2030, 5000.00)
ON DUPLICATE KEY UPDATE total=VALUES(total);

INSERT INTO sponsor_payments (id, sponsorid, amount, method, ref_nbr) VALUES (2033, 2001, 2500.00, 'check', 'STG-SP-2033')
ON DUPLICATE KEY UPDATE amount=VALUES(amount);

INSERT INTO sponsor_pending_pymts (id, sponsorid, amount, status) VALUES (2034, 2001, 2500.00, 'pending')
ON DUPLICATE KEY UPDATE status=VALUES(status);

INSERT INTO tep_vendor_booths (id, accountid, sponsorid, eventid, vendor_name, booth, hall, notes)
VALUES (2040, 1000, 2001, 1100, 'Apex Exhibitors', 'A-12', 'Expo Hall', 'Corner booth')
ON DUPLICATE KEY UPDATE booth=VALUES(booth);

INSERT INTO tep_vendor_leads (id, accountid, sponsorid, eventid, attendee_name, email, company, ticket, notes)
VALUES (2041, 1000, 2001, 1100, 'Morgan Patel', 'vip.attendee@phoenix-enterprise.example', 'Patel Group', 'PES-VIP-1901', 'Requested demo')
ON DUPLICATE KEY UPDATE notes=VALUES(notes);

INSERT INTO survey_questions (id, accountid, text, type, options, assoc, sortorder)
VALUES (2101, 1000, 'Overall event rating', 'numeric', '', 'event', 1),
(2102, 1000, 'Would you attend again?', 'select', 'Yes**No**Maybe', 'event', 2),
(2103, 1000, 'Session usefulness', 'numeric', '', 'section', 1)
ON DUPLICATE KEY UPDATE text=VALUES(text);

INSERT INTO survey_evt_association (id, eventid, questionid) VALUES (2110, 1100, 2101), (2111, 1100, 2102)
ON DUPLICATE KEY UPDATE questionid=VALUES(questionid);

INSERT INTO survey_responses (id, registrationid, eventid, questionid, response, sectionid)
VALUES (2120, 1901, 1100, 2101, '5', NULL)
ON DUPLICATE KEY UPDATE response=VALUES(response);

INSERT INTO tep_polls (id, accountid, eventid, question, options_json, is_active)
VALUES (2201, 1000, 1100, 'Is this keynote useful?', '["Yes","No"]', 1)
ON DUPLICATE KEY UPDATE question=VALUES(question), is_active=VALUES(is_active);

INSERT INTO tep_poll_votes (id, pollid, option_index, voter_key)
VALUES (2202, 2201, 0, 'stg-voter-1901')
ON DUPLICATE KEY UPDATE option_index=VALUES(option_index);

INSERT INTO tep_push_subscriptions (id, accountid, userid, endpoint, p256dh, auth, user_agent)
VALUES (2210, 1000, 1002, 'https://push.example/staging-endpoint-1002', 'stg-p256dh', 'stg-auth', 'TEP-Staging/1.0')
ON DUPLICATE KEY UPDATE endpoint=VALUES(endpoint);

INSERT INTO documents (id, accountid, filepath, author, description, status)
VALUES (2301, 1000, 'documents/account1000/summit-program.pdf', 'Alex Rivera', 'Printed program PDF', '0')
ON DUPLICATE KEY UPDATE filepath=VALUES(filepath);

INSERT INTO document_association (id, documentid, accountid, eventid, courseid, videoid, sessionid)
VALUES (2302, 2301, 1000, 1100, NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE eventid=VALUES(eventid);

INSERT INTO videos (id, accountid, name, display_name, filepath, published_lc, price, rental_duration)
VALUES (2310, 1000, 'keynote.mp4', 'Opening Keynote Replay', 'videos/account1000/keynote.mp4', 1, 29.00, 72)
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name);

INSERT INTO video_association (id, videoid, courseid) VALUES (2311, 2310, 1501)
ON DUPLICATE KEY UPDATE courseid=VALUES(courseid);

INSERT INTO video_packages (id, accountid, name, price) VALUES (2320, 1000, 'Summit Replay Pack', 79.00)
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO video_package_details (id, video_packagesid, videoid) VALUES (2321, 2320, 2310)
ON DUPLICATE KEY UPDATE videoid=VALUES(videoid);

INSERT INTO video_orders (id, accountid, attendeeid, total, discount, discount_code, paid, payment_number)
VALUES (2330, 1000, 1802, 29.00, 0.00, '', 1, 'STG-VID-2330')
ON DUPLICATE KEY UPDATE paid=VALUES(paid);

INSERT INTO video_order_details (id, video_ordersid, videoid, video_packagesid) VALUES (2331, 2330, 2310, NULL)
ON DUPLICATE KEY UPDATE videoid=VALUES(videoid);

INSERT INTO video_reviews (id, videoid, video_packagesid, rating, comment, attendeeid)
VALUES (2332, 2310, NULL, 5, 'Clear and useful.', 1802)
ON DUPLICATE KEY UPDATE rating=VALUES(rating);

INSERT INTO expense_categories (id, accountid, name, max)
VALUES (2401, 1000, 'Travel', 1500.00), (2402, 1000, 'Meals', 250.00)
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO staff_expenses (id, userid, eventid, expense_category_id, description, amount, approved_amt, receipt, expense_date)
VALUES (2410, 1002, 1100, 2401, 'Airport transfer', 86.40, 86.40, '', '2026-10-11')
ON DUPLICATE KEY UPDATE amount=VALUES(amount);

INSERT INTO staff_payments (id, staff_expense_id, amount, method, date_value, note)
VALUES (2411, 2410, 86.40, 'Check', '2026-10-15', 'Staging reimbursement')
ON DUPLICATE KEY UPDATE amount=VALUES(amount);

INSERT INTO inventory (id, accountid, category, type, brand, model, serial_number, notes, item_condition, location, purchase_date, purchase_price, retired)
VALUES (2501, 1000, 'AV', 'Scanner', 'Socket', 'CHS 7Ci', 'SN-STG-2501', 'Badge scanner', 'Good', 'Expo Hall', '2025-06-01', 399.00, 0)
ON DUPLICATE KEY UPDATE serial_number=VALUES(serial_number);

INSERT INTO preferences (id, accountid, name, value)
VALUES
(2601, 1000, 'inventorySettings', '{"categories":[{"name":"AV"}],"locations":["Expo Hall"],"conditions":["Good"]}'),
(2602, 1000, 'videoCategories', '["Keynote","Workshop"]'),
(2603, 1000, 'regScreenConfig', '{"title":{"label":"Position/Title"}}')
ON DUPLICATE KEY UPDATE value=VALUES(value);

INSERT INTO emails_sent (id, accountid, eventid, subject, body, recipients)
VALUES (2701, 1000, 1100, 'Summit confirmation', 'Thank you for registering.', 'vip.attendee@phoenix-enterprise.example')
ON DUPLICATE KEY UPDATE subject=VALUES(subject);

INSERT INTO support_tickets (id, accountid, subject, description, status)
VALUES (2710, 1000, 'Staging badge printer', 'Need extra ribbon for Hall A.', 'open')
ON DUPLICATE KEY UPDATE status=VALUES(status);

INSERT INTO acme_access_requests (id, accountid, email, first_name, last_name, approved)
VALUES (2720, 1000, 'acme.req@phoenix-enterprise.example', 'Lee', 'Hart', 0)
ON DUPLICATE KEY UPDATE approved=VALUES(approved);

INSERT INTO course_proposals (id, userid, sponsorid, title, description, status)
VALUES (2730, 1003, NULL, 'Advanced Check-In Patterns', 'Proposed follow-on lab.', 'pending')
ON DUPLICATE KEY UPDATE status=VALUES(status);

INSERT INTO user_colmodel (id, userid, eventid, page, colmodel)
VALUES (2740, 1001, 1100, 'registrations', '[{"name":"last_name"}]')
ON DUPLICATE KEY UPDATE colmodel=VALUES(colmodel);

INSERT INTO registration_field_exclusions (id, eventid, registration_fields_id, default_field)
VALUES (2750, 1100, 0, 'web_address')
ON DUPLICATE KEY UPDATE default_field=VALUES(default_field);

INSERT INTO regLog (id, referrer, userAgent, email, payment_method, reg_type, acctName, eventName, cc_enabled, req_cc_pymt, reg_fee, registrationid, confirmation)
VALUES (2760, 'staging', 'TEP-Staging/1.0', 'vip.attendee@phoenix-enterprise.example', 'cc', 'VIP', 'Phoenix Enterprise Events', 'Phoenix Enterprise Summit 2026', '0', '0', '799', 1901, 'PES-VIP-1901')
ON DUPLICATE KEY UPDATE confirmation=VALUES(confirmation);

SET FOREIGN_KEY_CHECKS = 1;
SET sql_notes = 1;