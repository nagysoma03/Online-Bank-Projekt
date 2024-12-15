<?php
namespace Login;

use Classes\Database;
use Classes\Login;

require_once '../Classes/Database.php';
require_once '../Classes/Login.php';

session_start();

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['account_id'])) {
    header("Location: login.php"); // Ha nincs bejelentkezve, átirányítjuk a bejelentkezés oldalra
    exit();
}

$db = new Database();
$login = new Login($db);

// Kijelentkezési idő rögzítése
$login->logOut($_SESSION['account_id']);

$_SESSION = [];

session_destroy();

// Átirányítás a bejelentkezési oldalra
header("Location: login.php");
exit();
?>