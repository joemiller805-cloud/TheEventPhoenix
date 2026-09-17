-- Sweep A: tenant lookup indexes. Runtime applies these via tep_ensure_tenant_indexes()
-- in data_access/queries.php (skips missing tables/columns and existing index names).
-- Do not concatenate request data. Identifiers below are literals.

ALTER TABLE attendees ADD INDEX idx_attendees_accountid (accountid);
ALTER TABLE attendees ADD INDEX idx_attendees_email (email);
ALTER TABLE events ADD INDEX idx_events_accountid (accountid);
ALTER TABLE registrations ADD INDEX idx_registrations_eventid (eventid);
ALTER TABLE registrations ADD INDEX idx_registrations_attendeeid (attendeeid);
ALTER TABLE users ADD INDEX idx_users_accountid (accountid);
ALTER TABLE users ADD INDEX idx_users_email (email);
ALTER TABLE sponsors ADD INDEX idx_sponsors_accountid (accountid);
ALTER TABLE sponsors ADD INDEX idx_sponsors_email (email);
ALTER TABLE pages ADD INDEX idx_pages_eventid (eventid);
ALTER TABLE preferences ADD INDEX idx_preferences_accountid (accountid);
ALTER TABLE documents ADD INDEX idx_documents_accountid (accountid);
ALTER TABLE signups ADD INDEX idx_signups_registrationid (registrationid);
ALTER TABLE tep_vendor_leads ADD INDEX idx_tep_vendor_leads_eventid (eventid);
ALTER TABLE tep_vendor_leads ADD INDEX idx_tep_vendor_leads_email (email);
ALTER TABLE tep_vendor_booths ADD INDEX idx_tep_vendor_booth_eventid (eventid);
ALTER TABLE accounts ADD INDEX idx_accounts_contact_email (contact_email);
