<?php
require_once __DIR__ . '/includes/session_manager.php';
session_unset();
session_destroy();

// Redirect to login page after logout
header("Location: loginin.php?msg=loggedout");
exit;
?>