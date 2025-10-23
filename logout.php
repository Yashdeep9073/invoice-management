<?php
session_start();


require './utility/env.php';


unset($_SESSION["admin_id"]);
unset($_SESSION["admin_name"]);
unset($_SESSION["admin_role"]);
// session_destroy();


  header("Location: " . getenv("BASE_URL"));

?>