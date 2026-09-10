<?php
session_start();
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1750)) {
    print('expired');
}
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    // last request was more than 30 minutes ago
    session_unset();     // unset $_SESSION variable for the run-time 
    session_destroy();   // destroy session data in storage
    print('expired');
}
if (isset($_SESSION['last_activity'])){
	print(time() - $_SESSION['last_activity']);
}
?>