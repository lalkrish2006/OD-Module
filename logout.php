<?php
session_start();
session_unset();
session_destroy();

// Redirect to login page after logout
header("Location: loginin.php?msg=loggedout");
exit;
?>
