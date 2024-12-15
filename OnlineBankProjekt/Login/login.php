<?php
namespace Login;
use Classes\Account;
use Classes\Database;
use Classes\Login;

require_once  '../Classes/Database.php';
require_once '../Classes/Account.php';
require_once  '../Classes/Transaction.php';
require_once  '../Classes/Login.php';
session_start();

$db = new Database();
$accounts = new Account($db);
$login = new Login($db);
$errorMessage = "";

if (isset($_SESSION['email'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if ($email && $password) {
        // Lekérjük a felhasználót az email alapján
        $user = $accounts->getByEmail($email);

        // Ellenőrizzük, hogy a felhasználó létezik-e és helyes-e a jelszó
        if ($user && $accounts->verifyPassword($password)) {
            // A sikeres bejelentkezéskor mentjük a felhasználói adatokat a session-be
            $_SESSION["account_id"] = $user["id"];
            $_SESSION["account_number"] = $user["account_number"];
            $_SESSION["user_name"] = $accounts->getUsername();
            $_SESSION["email"] = $accounts->getEmail();
            $_SESSION["balance"] = $accounts->getBalance();
            $_SESSION["balanceEUR"] = $accounts->getBalanceEUR();
            $_SESSION["balanceUSD"] = $accounts->getBalanceUSD();
            $_SESSION["balanceGBP"] = $accounts->getBalanceGBP();

            // IP cím és böngésző információ lekérdezése naplózáshoz
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $deviceInfo = $_SERVER['HTTP_USER_AGENT'];

            // A bejelentkezési adatok naplózása az adatbázisba
            $login->logIn($user["id"], $ipAddress, $deviceInfo);

            // Átirányítás a főoldalra sikeres belépés után
            header("location: ../index.php");
            exit();
        } else {
            // Ha a belépési adatok hibásak
            $errorMessage = "Hibás email vagy jelszó.";
        }
    } else {
        // Ha az egyik mezőt nem töltötték ki
        $errorMessage = "Kérjük, adja meg az emailt és a jelszót.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="login.css">
    <title>Login Form</title>
</head>
<body>
<header>
    <h1>Online Bank</h1>
</header>
<div class="login-form">
    <div class="login_cim">
        <h1>Login</h1>
    </div>
    <form method="post" action="login.php">
        <div class="content">
            <div class="input-field">
                <label for="email">Account Email:</label>
                <input type="text" id="email" name="email" required>
            </div>
            <div class="input-field">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
        </div>
        <?php if ($errorMessage): ?>
            <div class="error-message" style="color: red; font-size: 14px;margin-left: 20px">
                <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>
        <div class="action">
            <button type="submit" class="button2">Login</button>
        </div>
    </form>
</div>
<footer>
    <p>&copy; 2024 Online Bank</p>
</footer>
</body>
</html>
