<?php
session_start();
require_once "../Classes/Database.php";

use Classes\Database;

$db = new Database();
$conn = $db->connect();

// Felhasználó azonosítója
$userId = $_SESSION['account_id'];

// Lekérdezzük az account_number-t az accounts táblából
$queryAccountNumber = "SELECT account_number FROM accounts WHERE user_id = ?";
$stmtAccountNumber = $conn->prepare($queryAccountNumber);
$stmtAccountNumber->bind_param("i", $userId);
$stmtAccountNumber->execute();
$resultAccountNumber = $stmtAccountNumber->get_result();
$accountNumber = $resultAccountNumber->fetch_assoc()['account_number'];
$stmtAccountNumber->close();

// Lekérdezzük, hogy van-e aktív hitel
$query = "SELECT * FROM loans WHERE account_number = ? AND NOW() < DATE_ADD(start_date, INTERVAL loan_term MONTH)";
$stmtActiveLoan = $conn->prepare($query);
$stmtActiveLoan->bind_param("s", $accountNumber);
$stmtActiveLoan->execute();
$result = $stmtActiveLoan->get_result();

$activeLoan = $result->fetch_assoc();
$stmtActiveLoan->close();

// Hitel kezelése
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['hitel'])) {
        $loan_amount = $_POST['loan_amount'];
        $loan_months = $_POST['loan_months'];

        // Kamatlábak beállítása
        $interest_rates = [
            1 => 0.01,
            3 => 0.03,
            6 => 0.07,
            9 => 0.1,
            12 => 0.13,
            24 => 0.17
        ];

        // Hitel felvétele
        $stmt = $conn->prepare("INSERT INTO loans (user_id,account_number, loan_amount, interest_rate, loan_term, total_amount_due,end_date) VALUES (?,?, ?, ?, ?, ?,?)");

        // Kiszámoljuk a teljes visszafizetendő összeget
        $interest_rate = $interest_rates[$loan_months];
        $total_amount_due = ($loan_amount * (1 + $interest_rate)) / $loan_months * $loan_months;

        //endDate
        $end_date = date('Y-m-d', strtotime($activeLoan['start_date'] . " +$loan_months months"));


        $stmt->bind_param("isdddds", $userId, $accountNumber, $loan_amount, $interest_rate, $loan_months, $total_amount_due, $end_date);

        // Ha nincs aktív hitel, új hitel felvétele
        if ($stmt->execute()) {
            // Ha sikeresen beszúrtuk a hitelt, frissítjük a kamatlábat
            $loanId = $stmt->insert_id; // Az új hitel ID-ja

            // Frissítjük a kamatlábat, ha szükséges
            $updateStmt = $conn->prepare("UPDATE loans SET interest_rate = ? WHERE id = ?");
            $updateStmt->bind_param("di", $interest_rate, $loanId);

            if ($updateStmt->execute()) {
                $_SESSION['transfer_message'] = "A hitelt sikeresen felvetted és a kamatláb frissítve lett!";
            } else {
                $_SESSION['transfer_message'] = "Hiba történt a kamatláb frissítése közben!";
            }

            $updateStmt->close();
        } else {
            $_SESSION['transfer_message'] = "Hiba történt a hitel felvétele közben!";
        }

        $stmt->close();
        $conn->close();
    }

    // Hitel törlesztése
    if (isset($_POST['torlesztes']) && $activeLoan) {
        // Felhasználói egyenleg lekérdezése
        $stmtBalance = $conn->prepare("SELECT balance FROM accounts WHERE account_number = ?");
        $stmtBalance->bind_param("s", $accountNumber);
        $stmtBalance->execute();
        $balanceResult = $stmtBalance->get_result();
        $userBalance = $balanceResult->fetch_assoc()['balance'];
        $stmtBalance->close();

        // Teljes visszafizetendő összeg kiszámítása
        $monthly_payment = ($activeLoan['loan_amount'] * (1 + $activeLoan['interest_rate'])) / $activeLoan['loan_term'];
        $total_amount_due = $monthly_payment * $activeLoan['loan_term'];

        // Ellenőrzés: van-e elegendő pénz a teljes visszafizetendő összeghez
        if ($userBalance >= $total_amount_due) {
            // Egyenleg frissítése
            $newBalance = $userBalance - $total_amount_due;
            $stmtUpdateBalance = $conn->prepare("UPDATE accounts SET balance = ? WHERE account_number = ?");
            $stmtUpdateBalance->bind_param("ds", $newBalance, $accountNumber); // Az account_number-ot használjuk
            $stmtUpdateBalance->execute();
            $stmtUpdateBalance->close();

            // Hitel törlése
            $stmtCloseLoan = $conn->prepare("DELETE FROM loans WHERE id = ?");
            $stmtCloseLoan->bind_param("i", $activeLoan['id']);
            if ($stmtCloseLoan->execute()) {
                $_SESSION['transfer_message'] = "A hiteled sikeresen törlesztve lett!";
            } else {
                $_SESSION['transfer_message'] = "Hiba történt a hitel törlesztése közben!";
            }
            $stmtCloseLoan->close();
        } else {
            $_SESSION['transfer_message'] = "Nincs elegendő pénzed a hitel teljes törlesztéséhez!";
        }
    }

    header("Location: loans.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Bank - Hitel</title>
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="loans.css">
</head>
<body>
<header>
    <h1>Online Bank</h1>
    <nav>
        <a href="../index.php">Főoldal</a>
        <a href="../Transactions/transactions.php">Tranzakciók</a>
        <a href="../Exchange/exchange.php">Váltó</a>
        <a href="loans.php">Hitel</a>
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
    <section class="left_side">
        <h2>Hitel felvétele</h2>

        <?php if ($activeLoan): ?>
            <p>Jelenleg van aktív hiteled. A hiteled részleteit és törlesztését itt kezelheted.</p>
            <form method="POST">
                <button type="submit" name="torlesztes">Hitel törlesztése</button>
            </form>
        <?php else: ?>
            <form method="POST">
                <label for="loan_months">Hány hónapra szeretnéd a hitelt?</label>
                <select id="loan_months" name="loan_months" required>
                    <option value="1">1 hónap</option>
                    <option value="3">3 hónap</option>
                    <option value="6">6 hónap</option>
                    <option value="9">9 hónap</option>
                    <option value="12">12 hónap</option>
                    <option value="24">24 hónap</option>
                </select>
                <label for="loan_amount">Összeg:</label>
                <input type="number" id="loan_amount" name="loan_amount" step="0.01" placeholder="Összeg (pl. 5000.00)"
                       required>
                <p>
                    <button type="submit" name="hitel">Hitel felvétele</button>
                </p>
            </form>
        <?php endif; ?>

        <?php
        if (isset($_SESSION['transfer_message'])) {
            echo '<p>' . htmlspecialchars($_SESSION['transfer_message']) . '</p>';
            unset($_SESSION['transfer_message']);
        }
        ?>
        <!-- Hitel részletek táblázata -->

        <?php
        if ($activeLoan) { // Ha van aktív hitel
            // Aktív hitel adatai
            $loan_amount = $activeLoan['loan_amount'];
            $loan_months = $activeLoan['loan_term'];
            $interest_rate = $activeLoan['interest_rate'];
            $total_amount_due=$activeLoan['total_amount_due'];
            $monthly_payment = ($activeLoan['loan_amount'] * (1 + $activeLoan['interest_rate'])) / $activeLoan['loan_term'];
            $end_date = $activeLoan['end_date'];


            echo '<h3>Hitel részletek</h3>';
            echo '<table>';
            echo '<tr><th>Összeg</th><th>Havi törlesztés</th><th>Lejárati dátum</th><th>Kamat</th><th>Számlaszám</th><th>Visszafizetendő összeg</th></tr>';
            echo '<tr>';
            echo '<td>' . number_format($loan_amount, 2) . ' RON</td>';
            echo '<td>' . number_format($monthly_payment, 2) . ' RON</td>';
            echo '<td>' . $end_date . '</td>';
            echo '<td>' . number_format($interest_rate * 100, 2) . '%</td>';
            echo '<td>' . $accountNumber . '</td>'; // Felhasználó ID
            echo '<td>' . number_format($total_amount_due, 2) . ' RON</td>'; // Visszafizetendő összeg
            echo '</tr>';
            echo '</table>';
        }
        ?>
    </section>
</main>
<footer>
    <p>&copy; 2024 Online Bank</p>
</footer>
</body>
</html>