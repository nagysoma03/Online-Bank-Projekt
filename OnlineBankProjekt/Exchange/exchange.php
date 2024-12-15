<?php
namespace Exchange;

require_once '../Classes/Database.php';
require_once '../Classes/Account.php';

use Classes\Database;
use Classes\Account;
use Exception;

session_start();
// Adatbázis kapcsolat inicializálása
$db = new Database();

// Számlainformációk lekérése
$accountNumber = $_SESSION['account_number']; // Felhasználó számlaszáma
$account = new Account($db);
$account->getByAccountNumber($accountNumber); // Számla adatok betöltése

// Egyenlegek lekérése és munkamenetben tárolása
$_SESSION['balance'] = $account->getBalance(); // RON egyenleg
$_SESSION['balance_eur'] = $account->getBalanceEUR(); // EUR egyenleg
$_SESSION['balance_usd'] = $account->getBalanceUSD(); // USD egyenleg
$_SESSION['balance_gbp'] = $account->getBalanceGBP(); // GBP egyenleg

// Valutaváltás kezelése
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['exchange'])) {
        $amount = $_POST['amount']; // Váltandó összeg
        $currency_from = $_POST['currency_from']; // Miből váltunk
        $currency_to = $_POST['currency_to']; // Mire váltunk

        // Azonos valuták ellenőrzése
        if ($currency_from === $currency_to) {
            $_SESSION['exchange_message'] = "Nem válthatsz ugyanabból a valutából ugyanabba!";
        }
        // Egyenleg ellenőrzése
        elseif ($currency_from === 'RON' && $_SESSION['balance'] < $amount) {
            $_SESSION['exchange_message'] = "Nincs elég RON egyenleged a tranzakcióhoz!";
        } elseif ($currency_from === 'EUR' && $_SESSION['balance_eur'] < $amount) {
            $_SESSION['exchange_message'] = "Nincs elég EUR egyenleged a tranzakcióhoz!";
        } elseif ($currency_from === 'USD' && $_SESSION['balance_usd'] < $amount) {
            $_SESSION['exchange_message'] = "Nincs elég USD egyenleged a tranzakcióhoz!";
        } elseif ($currency_from === 'GBP' && $_SESSION['balance_gbp'] < $amount) {
            $_SESSION['exchange_message'] = "Nincs elég GBP egyenleged a tranzakcióhoz!";
        } else {
            try {
                // Valutaváltás végrehajtása
                $converted_amount = $account->convertCurrency($amount, $currency_from, $currency_to);

                // Egyenlegek frissítése
                if ($currency_from === 'RON') {
                    $_SESSION['balance'] -= $amount;
                } elseif ($currency_from === 'EUR') {
                    $_SESSION['balance_eur'] -= $amount;
                } elseif ($currency_from === 'USD') {
                    $_SESSION['balance_usd'] -= $amount;
                } elseif ($currency_from === 'GBP') {
                    $_SESSION['balance_gbp'] -= $amount;
                }

                // Átváltott összeg hozzáadása a valutához
                if ($currency_to === 'RON') {
                    $_SESSION['balance'] += $converted_amount;
                } elseif ($currency_to === 'EUR') {
                    $_SESSION['balance_eur'] += $converted_amount;
                } elseif ($currency_to === 'USD') {
                    $_SESSION['balance_usd'] += $converted_amount;
                } elseif ($currency_to === 'GBP') {
                    $_SESSION['balance_gbp'] += $converted_amount;
                }

                // Egyenleg adatbázisban történő frissítése
                if ($currency_from === 'RON' || $currency_to === 'RON') {
                    $account->updateBalanceForCurrency('RON', $_SESSION['balance']);
                }
                if ($currency_from === 'EUR' || $currency_to === 'EUR') {
                    $account->updateBalanceForCurrency('EUR', $_SESSION['balance_eur']);
                }
                if ($currency_from === 'USD' || $currency_to === 'USD') {
                    $account->updateBalanceForCurrency('USD', $_SESSION['balance_usd']);
                }
                if ($currency_from === 'GBP' || $currency_to === 'GBP') {
                    $account->updateBalanceForCurrency('GBP', $_SESSION['balance_gbp']);
                }

                $_SESSION['exchange_message'] = "Sikeres valuta csere!";
            } catch (Exception $e) {
                $_SESSION['exchange_message'] = "Hiba történt: " . $e->getMessage();
            }
        }

        header("Location: exchange.php"); // Újratöltés
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Bank - Valutaváltás</title>
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="exchange.css">
</head>
<body>
<header>
    <h1>Online Bank</h1>
    <nav>
        <a href="../index.php">Főoldal</a>
        <a href="../Transactions/transactions.php">Tranzakciók</a>
        <a href="exchange.php">Váltó</a>
        <a href="../Loan/loans.php">Hitel</a>
        <a href="../Account/account.php">Fiók</a>
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
<main>
    <div class="exchange">
        <h2>Váltó</h2>
        <form method="POST" action="exchange.php">
            <p><label for="currency_from">From:</label>
                <select name="currency_from" id="currency_from">
                    <option value="RON">RON</option>
                    <option value="EUR">EUR</option>
                    <option value="USD">USD</option>
                    <option value="GBP">GBP</option>
                </select></p>

            <p><label for="currency_to">To:</label>
                <select name="currency_to" id="currency_to">
                    <option value="RON">RON</option>
                    <option value="EUR">EUR</option>
                    <option value="USD">USD</option>
                    <option value="GBP">GBP</option>
                </select></p>

            <p><label for="amount">Összeg:</label>
                <input type="number" name="amount" id="amount" step="0.01" required></p>

            <button type="submit" name="exchange">Váltás</button>

            <p><?php
                if (isset($_SESSION['exchange_message'])) {
                    echo "<p>" . $_SESSION['exchange_message'] . "</p>";
                    unset($_SESSION['exchange_message']);
                }
                ?></p>
        </form>
    </div>

    <div class="exchange_rates">
        <h3>Valutáid és egyenlegek:</h3>
        <table border="1">
            <thead>
            <tr>
                <th>Valuta</th>
                <th>Egyenleg</th>
                <th>Átváltási árfolyamok</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>RON</td>
                <td><?php echo $_SESSION['balance']; ?> RON</td>
                <td>
                    EUR: <?php echo number_format($account->getExchangeRate('RON', 'EUR'), 2); ?>,
                    USD: <?php echo number_format($account->getExchangeRate('RON', 'USD'), 2); ?>,
                    GBP: <?php echo number_format($account->getExchangeRate('RON', 'GBP'), 2); ?>
                </td>
            </tr>
            <tr>
                <td>EUR</td>
                <td><?php echo $_SESSION['balance_eur']; ?> EUR</td>
                <td>
                    RON: <?php echo number_format($account->getExchangeRate('EUR', 'RON'), 2); ?>,
                    USD: <?php echo number_format($account->getExchangeRate('EUR', 'USD'), 2); ?>,
                    GBP: <?php echo number_format($account->getExchangeRate('EUR', 'GBP'), 2); ?>
                </td>
            </tr>
            <tr>
                <td>USD</td>
                <td><?php echo $_SESSION['balance_usd']; ?> USD</td>
                <td>
                    RON: <?php echo number_format($account->getExchangeRate('USD', 'RON'), 2); ?>,
                    EUR: <?php echo number_format($account->getExchangeRate('USD', 'EUR'), 2); ?>,
                    GBP: <?php echo number_format($account->getExchangeRate('USD', 'GBP'), 2); ?>
                </td>
            </tr>
            <tr>
                <td>GBP</td>
                <td><?php echo $_SESSION['balance_gbp']; ?> GBP</td>
                <td>
                    RON: <?php echo number_format($account->getExchangeRate('GBP', 'RON'), 2); ?>,
                    EUR: <?php echo number_format($account->getExchangeRate('GBP', 'EUR'), 2); ?>,
                    USD: <?php echo number_format($account->getExchangeRate('GBP', 'USD'), 2); ?>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
</main>
<footer>
    <p>&copy; 2024 Online Bank</p>
</footer>
</body>
</html>
