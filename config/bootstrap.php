<?php
require_once dirname(__DIR__) . '/common_functions.php'; // Cookie helpers, roles, DB constants
start_secure_session(); // HttpOnly + SameSite=Lax + Secure-on-HTTPS before any output
tep_bind_request_accountid(); // Public tenant only; never overwrite a logged-in principal
