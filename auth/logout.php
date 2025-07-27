<?php
session_start();
session_unset();
session_destroy();
header("Location: /id_verification/index.php");
exit;