<?php
namespace Transactions;

require_once  '../Classes/Database.php';
require_once  '../Classes/Transaction.php';
require_once '../Classes/Account.php';

use Classes\Database;
use Classes\Transaction;
use Classes\Account;

session_start();

// Ellenőrizzük, hogy be van-e jelentkezve a felhasználó
if (!isset($_SESSION['account_id'])) {
    header("Location: ../Login/login.php"); // Ha nincs bejelentkezve, átirányítjuk a bejelentkezés oldalra
    exit;
}

$db = new Database();

$transaction = new Transaction($db);

$userId = $_SESSION['account_id']; // A bejelentkezett felhasználó ID-ja

$accountNumber = $_SESSION['account_number'];
$account = new Account($db);
$account->getByAccountNumber($accountNumber);

// Egyenlegek lekérése és munkamenetben tárolása
$_SESSION['balance'] = $account->getBalance(); // RON egyenleg
$_SESSION['balance_eur'] = $account->getBalanceEUR(); // EUR egyenleg
$_SESSION['balance_usd'] = $account->getBalanceUSD(); // USD egyenleg
$_SESSION['balance_gbp'] = $account->getBalanceGBP(); // GBP egyenleg

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['transfer'])) {
        $receiverAccountNumber = $_POST['receiver_account']; // Cél számlaszám
        $amount = $_POST['amount']; // Átutalási összeg
        $type = 'transfer'; // Tranzakció típusa

        // Összes mező ki van-e töltve
        if (!empty($receiverAccountNumber) && !empty($amount) && $amount > 0) {
            // Megkeressük a fogadó fél számlájának ID-ját
            $receiverId = $transaction->getAccountIdByAccountNumber($receiverAccountNumber);
            if ($receiverId) {
                // Ellenőrizzük, hogy a fogadó ID nem egyezik-e meg a felhasználó saját ID-jával
                if ($receiverId == $userId) {
                    $_SESSION['transfer_message'] = "Nem utalhatsz saját magadnak!";
                } else {
                    // Tranzakció végrehajtása
                    $result = $transaction->create($userId, $receiverId, $amount, $type);
                    if ($result) {
                        $_SESSION['transfer_message'] = "Sikeres átutalás!";

                        $account->getByAccountNumber($accountNumber); // Betöltjük a számla adatokat újra
                        $_SESSION['balance'] = $account->getBalance(); // RON egyenleg
                        $_SESSION['balance_eur'] = $account->getBalanceEUR(); // EUR egyenleg
                        $_SESSION['balance_usd'] = $account->getBalanceUSD(); // USD egyenleg
                        $_SESSION['balance_gbp'] = $account->getBalanceGBP(); // GBP egyenleg
                    } else {
                        $_SESSION['transfer_message'] = "Hiba történt az átutalás során!";
                    }
                }
            } else {
                $_SESSION['transfer_message'] = "Érvénytelen fogadó számlaszám!";
            }
        } else {
            $_SESSION['transfer_message'] = "Minden mezőt ki kell tölteni, és az összegnek pozitívnak kell lennie!";
        }
        // Oldal frissitése
        header("Location: transactions.php");
        exit;
    }

    // Befizetés kezelése
    if (isset($_POST['deposit'])) {
        $amount = $_POST['deposit_amount']; // Befizetési összeg
        $cardNumber = $_POST['card_number']; // Bankkártya szám
        $expiryDate = $_POST['expiry_date']; // Lejárati dátum
        $cvv = $_POST['cvv']; // CVV kód
        $type = 'deposit'; // Tranzakció típusa

        // Az összeg pozitív kell legyen
        if (!empty($amount) && $amount > 0) {
            // Bankkártya validálás
            $month = (int) substr($expiryDate, 0, 2); // A hónapot egész számra alakítjuk
            $year = (int) ('20' . substr($expiryDate, 3, 2)); // Az évet is egész számra alakítjuk

            // Aktuális hónap és év
            $currentMonth = (int) date('m');
            $currentYear = (int) date('Y');

            // Validálás: Kártya lejárat nem lehet az aktuális hónap és év
            if (preg_match('/^\d{16}$/', $cardNumber) && checkdate($month, 1, $year)
                && ($year > $currentYear || ($year == $currentYear && $month >= $currentMonth)) && preg_match('/^\d{3}$/', $cvv)) {

                // Tranzakció végrehajtása (ugyanaz a felhasználó küldi és fogadja)
                $result = $transaction->create($userId, $userId, $amount, $type);
                if ($result) {
                    $_SESSION['deposit_message'] = "Sikeres befizetés!";

                    $account->getByAccountNumber($accountNumber); // Betöltjük a számla adatokat újra
                    $_SESSION['balance'] = $account->getBalance(); // RON egyenleg
                    $_SESSION['balance_eur'] = $account->getBalanceEUR(); // EUR egyenleg
                    $_SESSION['balance_usd'] = $account->getBalanceUSD(); // USD egyenleg
                    $_SESSION['balance_gbp'] = $account->getBalanceGBP(); // GBP egyenleg
                } else {
                    $_SESSION['deposit_message'] = "Hiba történt a befizetés során!";
                }
            } else {
                $_SESSION['deposit_message'] = "Érvénytelen bankkártya adatok!";
            }
        } else {
            $_SESSION['deposit_message'] = "A befizetett összegnek pozitívnak kell lennie!";
        }
        // Átirányítás az oldalra
        header("Location: transactions.php");
        exit;
    }

    // Kivétel kezelése
    if (isset($_POST['withdraw'])) {
        $amount = $_POST['withdraw_amount']; // Kivét összeg
        $cardNumber = $_POST['card_number']; // Bankkártya szám
        $expiryDate = $_POST['expiry_date']; // Lejárati dátum
        $cvv = $_POST['cvv']; // CVV kód
        $type = 'withdraw'; // Tranzakció típusa

        // Az összeg pozitív kell legyen
        if (!empty($amount) && $amount > 0) {
            // Bankkártya validálás
            $month = (int) substr($expiryDate, 0, 2); // A hónapot egész számra konvertáljuk
            $year = (int) ('20' . substr($expiryDate, 3, 2)); // Az évet is egész számra konvertáljuk

            // Aktuális hónap és év
            $currentMonth = (int) date('m');
            $currentYear = (int) date('Y');

            // Validálás: Kártya lejárat nem lehet az aktuális hónap és év
            if (preg_match('/^\d{16}$/', $cardNumber) && checkdate($month, 1, $year) && ($year > $currentYear || ($year == $currentYear && $month >= $currentMonth)) && preg_match('/^\d{3}$/', $cvv)) {
                // Betöltjük a számla adatokat újra
                $account->getByAccountNumber($accountNumber); // Betöltjük az aktuális számla adatokat
                $currentBalance = $account->getBalance(); // Lekérjük az aktuális egyenleget

                // Ellenőrizzük, hogy a kivett összeg nem haladja-e meg az aktuális egyenleget
                if ($amount <= $currentBalance) {
                    // Tranzakció végrehajtása (ugyanaz a felhasználó küldi és fogadja)
                    $result = $transaction->create($userId, $userId, $amount, $type);
                    if ($result) {
                        $_SESSION['withdraw_message'] = "Sikeres kivétel!";
                        // Frissítjük az egyenleget
                        $account->getByAccountNumber($accountNumber); // Betöltjük a számla adatokat újra
                        $_SESSION['balance'] = $account->getBalance(); // Frissítjük a session egyenleget
                        $_SESSION['balance_eur'] = $account->getBalanceEUR(); // Frissítjük a session egyenleget
                        $_SESSION['balance_usd'] = $account->getBalanceUSD(); // Frissítjük a session egyenleget
                        $_SESSION['balance_gbp'] = $account->getBalanceGBP();// Frissítjük a session egyenleget
                    } else {
                        $_SESSION['withdraw_message'] = "Hiba történt a kivétel során!";
                    }
                } else {
                    $_SESSION['withdraw_message'] = "Nem rendelkezel elegendő egyenleggel!";
                }
            } else {
                $_SESSION['withdraw_message'] = "Érvénytelen bankkártya adatok!";
            }
        } else {
            $_SESSION['withdraw_message'] = "A kivett összegnek pozitívnak kell lennie!";
        }
        // Átirányítás az oldalra
        header("Location: transactions.php");
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Bank - Tranzakció</title>
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="transactions.css">
</head>
<body>
<header>
    <h1>Online Bank</h1>
    <nav>
        <a href="../index.php">Főoldal</a>
        <a href="transactions.php">Tranzakciók</a>
        <a href="../Exchange/exchange.php">Váltó</a>
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
<main class="layout">
    <div class="left_side">
        <section>
            <h2>Egyenleg Átutalás</h2>
            <form method="POST" action="">
                <label for="receiver_account">Számlaszám:</label>
                <input type="text" id="receiver_account" name="receiver_account" placeholder="Számlaszám" required>

                <label for="amount">Összeg:</label>
                <input type="number" id="amount" name="amount" step="0.01" placeholder="Összeg (pl. 100.00)" required>

                <p><button  type="submit" name="transfer">Átutalás</button>
                    <?php
                    if (isset($_SESSION['transfer_message'])) {
                        echo htmlspecialchars($_SESSION['transfer_message']);
                        unset($_SESSION['transfer_message']);
                    }
                    ?>
                </p>
            </form>

        </section>

        <section>
            <h2>Egyenleg befizetés</h2>
            <form method="POST" action="">
                <label for="card_number">Kártya Szám:</label>
                <input type="text" id="card_number" name="card_number" placeholder="Bankkártya száma" required>

                <label for="expiry_date">Lejárati Dátum (MM/YY):</label>
                <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" required>

                <label for="cvv">CVV:</label>
                <input type="text" id="cvv" name="cvv" placeholder="CVV" required>

                <p><label for="deposit_amount">Összeg:</label>
                    <input type="number" id="deposit_amount" name="deposit_amount" step="0.01" placeholder="Összeg (pl. 100.00)" required></p>

                <p><button type="submit" name="deposit">Befizetés</button>
                    <?php
                    if (isset($_SESSION['deposit_message'])) {
                        echo htmlspecialchars($_SESSION['deposit_message']);
                        unset($_SESSION['deposit_message']);
                    }
                    ?>
                </p>
            </form>

        </section>

        <section>
            <h2>Egyenleg kivétel</h2>
            <form method="POST" action="">
                <label for="card_number">Kártya szám:</label>
                <input type="text" id="card_number" name="card_number" placeholder="Bankkártya száma" required>

                <label for="expiry_date">Lejárati Dátum(MM/YY):</label>
                <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" required>

                <label for="cvv">CVV:</label>
                <input type="text" id="cvv" name="cvv" placeholder="CVV" required>

                <p><label for="withdraw_amount">Összeg:</label>
                    <input type="number" id="withdraw_amount" name="withdraw_amount" step="0.01" placeholder="Összeg (pl. 100.00)" required></p>

                <p><button type="submit" name="withdraw">Kivétel</button>
                    <?php
                    if (isset($_SESSION['withdraw_message'])) {
                        echo htmlspecialchars($_SESSION['withdraw_message']);
                        unset($_SESSION['withdraw_message']);
                    }
                    ?>
                </p>
            </form>

        </section>
    </div>

    <section class="right_side">
        <h2>Számla adatok</h2>
        <p><strong>Számlaszám:</strong> <?php echo htmlspecialchars($accountNumber ?? 'N/A'); ?></p>
        <p><strong>Egyenleg:</strong> <?php echo htmlspecialchars($_SESSION['balance']); ?> RON</p>
        <p><strong>EUR Egyenleg</strong> <?php echo htmlspecialchars($_SESSION['balance_eur']); ?> EUR</p>
        <p><strong>USD Egyenleg:</strong> <?php echo htmlspecialchars($_SESSION['balance_usd']); ?> USD</p>
        <p><strong>GBP Egyenleg:</strong> <?php echo htmlspecialchars($_SESSION['balance_gbp']); ?> GBP</p>
    </section>
</main>
<footer>
    <p>&copy; 2024 Online Bank</p>
</footer>
</body>
</html>