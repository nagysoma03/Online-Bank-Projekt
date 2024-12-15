<?php

session_start();

// Ha a felhasználó nincs bejelentkezve, átirányítás a login.php-ra
if (!isset($_SESSION['account_id'])) {
    header("Location: Login/login.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Bank - Főoldal</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>
<header>
    <h1>Online Bank</h1>
    <nav>
        <a href="index.php">Főoldal</a>
        <a href="Transactions/transactions.php">Tranzakciók</a>
        <a href="Exchange/exchange.php">Váltó</a>
        <a href="Loan/loans.php">Hitel</a>
        <a href="Account/account.php">Fiók</a>

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
        <form action="Login/logout.php" method="post">
            <button type="submit" class="logout-button">Kilépés</button>
        </form>
    <?php endif; ?>
</header>
<main class="fooldal">
    <section class="welcome">
        <h2><?php
            if (isset($_SESSION['user_name'])) {
                echo "Üdvözöljük, " . htmlspecialchars($_SESSION['user_name']);
            } else {
                echo "Nincs bejelentkezve";
            }
            ?></h2>
        <p>
            Üdvözöljük az Online Bank online banki rendszerében! Köszönjük, hogy minket választott! Itt minden lehetőséget megtalál, amire a pénzügyei kezeléséhez
            szüksége lehet. Használja a könnyen navigálható felületet tranzakciók intézésére, számlakezelésre
            és gyors, biztonságos pénzmozgások lebonyolítására.
            Ha bármilyen kérdése lenne, ne habozzon kapcsolatba lépni velünk!
        </p>
    </section>
    <section class="kapcsolat">
        <h2>Kapcsolat: </h2>
        <p>
            Email: bank@example.com
        </p>
        <p>
            Mobil: +40 21 123 4567
        </p>
    </section>
</main>
<footer>
    <p>&copy; 2024 Online Bank</p>
</footer>
</body>
</html>