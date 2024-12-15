<?php
namespace Login;

use Classes\Account;
use Classes\Database;
use Classes\Login;
use Classes\Transaction;

require_once '../Classes/Database.php';
require_once '../Classes/Account.php';
require_once '../Classes/Transaction.php';
require_once '../Classes/Login.php';


session_start();

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['account_id'])) {
    header("Location: ../Login/login.php"); // Ha nincs bejelentkezve, átirányítjuk a bejelentkezés oldalra
    exit;
}

$db = new Database();
$login = new Login($db);

// Bejelentkezési történelem lekérése
$loginHistory = $login->getLoginHistory($_SESSION['account_id']);

$accountNumber = $_SESSION['account_number'];
$account = new Account($db);
$account->getByAccountNumber($accountNumber);

// Egyenlegek lekérése és munkamenetben tárolása
$_SESSION['balance'] = $account->getBalance(); // RON egyenleg
$_SESSION['balance_eur'] = $account->getBalanceEUR(); // EUR egyenleg
$_SESSION['balance_usd'] = $account->getBalanceUSD(); // USD egyenleg
$_SESSION['balance_gbp'] = $account->getBalanceGBP(); // GBP egyenleg

// Tranzakciók lekérdezése a felhasználóhoz tartozóan
$transaction = new Transaction($db);
$transactions = $transaction->getByCustomer($_SESSION['account_id']);

?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Bank - Fiók</title>
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="account.css">
</head>
<body>
<header>
    <h1>Online Bank</h1>
    <nav>
        <a href="../index.php">Főoldal</a>
        <a href="../Transactions/transactions.php">Tranzakciók</a>
        <a href="../Exchange/exchange.php">Váltó</a>
        <a href="../Loan/loans.php">Hitel</a>
        <a href="account.php">Fiók</a>
    </nav>
    <span>
        <?php
        if (isset($_SESSION['user_name'])) {
            echo "Üdvözöljük, " . htmlspecialchars($_SESSION['user_name']);
        } else {
            echo "Nincs bejelentkezve";
        }
        ?>
    </span>
    <?php if (isset($_SESSION['user_name'])): ?>
        <form action="../Login/logout.php" method="post">
            <button type="submit" class="logout-button">Kilépés</button>
        </form>
    <?php endif; ?>
</header>
<main class="layout">
    <section class="account_info">
        <h2>Felhasználói adatok</h2>
        <p><strong>Név:</strong> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'N/A'); ?></p>
        <p><strong>Számlaszám:</strong> <?php echo htmlspecialchars($_SESSION["account_number"] ?? 'N/A'); ?></p>
        <p><strong>Email:</strong> <?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'Nincs bejelentkezve'; ?></p>
        <p><strong>Egyenleg:</strong> <?php echo htmlspecialchars($_SESSION['balance']); ?> RON</p>
        <p><strong>EUR Egyenleg:</strong> <?php echo htmlspecialchars($_SESSION['balance_eur']); ?> EUR</p>
        <p><strong>USD Egyenleg:</strong> <?php echo htmlspecialchars($_SESSION['balance_usd']); ?> USD</p>
        <p><strong>GBP Egyenleg:</strong> <?php echo htmlspecialchars($_SESSION['balance_gbp']); ?> GBP</p>

        <!-- Letöltés gomb -->
        <form method="post" action="download.php">
            <button type="submit" class="download-button">Kivonat letöltése</button>
        </form>
    </section>
    <section class="login_history">
        <h2>Bejelentkezési előzmények</h2>
        <table>
            <thead>
            <tr>
                <th>Bejelentkezési idő</th>
                <th>Kijelentkezési idő</th>
                <th>IP-cím</th>
                <th>Eszköz</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($loginHistory)): ?>
                <?php foreach ($loginHistory as $login): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($login['login_time']); ?></td>
                        <td><?php echo htmlspecialchars($login['logout_time'] ?? 'Még aktív'); ?></td>
                        <td><?php echo htmlspecialchars($login['ip_address']); ?></td>
                        <td><?php echo htmlspecialchars($login['device_info']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">Nincsenek bejelentkezési adatok.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
    <section class="transactions">
        <h2>Tranzakciók</h2>
        <table>
            <thead>
            <tr>
                <th>Feladó Számla</th>
                <th>Címzett Számla</th>
                <th>Összeg</th>
                <th>Típus</th>
                <th>Tranzakció Dátum</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($transactions)): ?>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($transaction['sender_account_number']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['receiver_account_number']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['amount']); ?> RON</td>
                        <td><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['type']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">Nincsenek tranzakciók.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

</main>
<footer>
    <p>&copy; 2024 Online Bank</p>
</footer>
</body>
</html>
