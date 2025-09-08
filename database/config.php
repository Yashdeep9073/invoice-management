<?php

require __DIR__ . "/../utility/env.php";

if (!defined('DB_SERVER')) {
    define('DB_SERVER', getenv("DB_HOST"));
}
if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', getenv("DB_USER"));
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', getenv("DB_PASSWORD"));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv("DB_NAME"));
}

$db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

mysqli_select_db($db, DB_NAME);

?>