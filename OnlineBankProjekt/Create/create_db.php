<?php
namespace Create;

use mysqli;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "online_bank";

// Kapcsolódás a MySQL szerverhez
$conn = new mysqli($servername, $username, $password);

// Kapcsolódási hiba ellenőrzése
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Adatbázis létrehozása, ha még nem létezik
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Database '$dbname' created successfully\n";
} else {
    echo "Error creating database: " . $conn->error . "\n";
}

// Az adatbázis kiválasztása
$conn->select_db($dbname);

// 'users' tábla
$sql = "CREATE TABLE IF NOT EXISTS users (
id INT(11) NOT NULL AUTO_INCREMENT,
name VARCHAR(255) NOT NULL,
email VARCHAR(255) NOT NULL,
password VARCHAR(255) NOT NULL,
PRIMARY KEY (id),
UNIQUE INDEX email (email ASC)
)";
if ($conn->query($sql) === TRUE) {
    echo "Table 'users' created successfully\n";
} else {
    echo "Error creating table 'users': " . $conn->error . "\n";
}

// 'accounts' tábla létrehozása
$sql = "CREATE TABLE IF NOT EXISTS accounts (
id INT(11) NOT NULL AUTO_INCREMENT,
user_id INT(11) NOT NULL,
account_number VARCHAR(20) NOT NULL,
balance DECIMAL(10,2) DEFAULT 0.00,
balance_eur DECIMAL(10,2) DEFAULT 0.00,
balance_usd DECIMAL(10,2) DEFAULT 0.00,
balance_gbp DECIMAL(10,2) DEFAULT 0.00,
created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
PRIMARY KEY (id),
UNIQUE INDEX account_number (account_number ASC),
INDEX user_id (user_id ASC),
CONSTRAINT accounts FOREIGN KEY (user_id) REFERENCES users (id)
)";
if ($conn->query($sql) === TRUE) {
    echo "Table 'accounts' created successfully\n";
} else {
    echo "Error creating table 'accounts': " . $conn->error . "\n";
}

// 'logins' tábla létrehozása
$sql = "CREATE TABLE IF NOT EXISTS logins (
id INT(11) NOT NULL AUTO_INCREMENT,
user_id INT(11) NOT NULL,
login_time DATETIME NOT NULL,
logout_time DATETIME DEFAULT NULL,
ip_address VARCHAR(45) NOT NULL,
device_info TEXT DEFAULT NULL,
PRIMARY KEY (id),
INDEX user_id (user_id ASC),
CONSTRAINT logins FOREIGN KEY (user_id) REFERENCES users (id)
)";
if ($conn->query($sql) === TRUE) {
    echo "Table 'logins' created successfully\n";
} else {
    echo "Error creating table 'logins': " . $conn->error . "\n";
}

// 'transactions' tábla létrehozása
$sql = "CREATE TABLE IF NOT EXISTS transactions (
id INT(11) NOT NULL AUTO_INCREMENT,
sender_account_id INT(11) NOT NULL,
receiver_account_id INT(11) NOT NULL,
amount DECIMAL(10,2) NOT NULL,
transaction_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
type ENUM('deposit', 'withdraw', 'transfer') NOT NULL,
PRIMARY KEY (id),
INDEX sender_account_id (sender_account_id ASC),
INDEX receiver_account_id (receiver_account_id ASC),
CONSTRAINT transactions_ibfk_1 FOREIGN KEY (sender_account_id) REFERENCES accounts (id),
CONSTRAINT transactions_ibfk_2 FOREIGN KEY (receiver_account_id) REFERENCES accounts (id)
)";
if ($conn->query($sql) === TRUE) {
    echo "Table 'transactions' created successfully\n";
} else {
    echo "Error creating table 'transactions': " . $conn->error . "\n";
}

// 'loans' tábla létrehozása
$sql = "CREATE TABLE IF NOT EXISTS loans (
id INT(11) NOT NULL AUTO_INCREMENT,
user_id INT(11) NOT NULL,
account_number VARCHAR(20),
loan_amount DECIMAL(15,2) NOT NULL,
interest_rate DECIMAL(5,2) NOT NULL,
loan_term INT(11) NOT NULL,
total_amount_due DECIMAL(10, 2) NOT NULL,
start_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
end_date TIMESTAMP NULL DEFAULT NULL,
PRIMARY KEY (id),
INDEX user_id (user_id ASC),
CONSTRAINT loans_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id)
)";
if ($conn->query($sql) === TRUE) {
    echo "Table 'loans' created successfully\n";
} else {
    echo "Error creating table 'loans': " . $conn->error . "\n";
}

// 'exchange_rates' tábla
$sql = "CREATE TABLE IF NOT EXISTS exchange_rates (
id INT(11) NOT NULL AUTO_INCREMENT,
currency_from VARCHAR(3) NOT NULL,
currency_to VARCHAR(3) NOT NULL,
exchange_rate DECIMAL(10,6) NOT NULL,
PRIMARY KEY (id),
UNIQUE INDEX currency_pair (currency_from, currency_to)
)";
if ($conn->query($sql) === TRUE) {
    echo "Table 'exchange_rates' created successfully\n";
} else {
    echo "Error creating table 'exchange_rates': " . $conn->error . "\n";
}

// Felhasználók beszúrása
$passwordHashUser1 = password_hash("user1", PASSWORD_DEFAULT);
$passwordHashUser2 = password_hash("user2", PASSWORD_DEFAULT);
$passwordHashUser3 = password_hash("user3", PASSWORD_DEFAULT);
$passwordHashUser4 = password_hash("user4", PASSWORD_DEFAULT);

$sqlInsertUsers = "INSERT INTO users (name, email, password) VALUES
('Lakatos Anita', 'anitalak@example.com', '$passwordHashUser1'),
('Kis Imi', 'kisimi@example.com', '$passwordHashUser2'),
('Nagy Emese', 'nagyemi@example.com', '$passwordHashUser3'),
('Kopacz Karcsi', 'kopkarcsi@example.com', '$passwordHashUser4')";

if ($conn->query($sqlInsertUsers) === TRUE) {
    echo "Users inserted successfully\n";
} else {
    echo "Error inserting users: " . $conn->error . "\n";
}

// Számlák beszúrása
$sqlInsertAccounts = "INSERT INTO accounts (user_id, account_number, balance) VALUES
(1, 'RO43ZERD9851231805', 599.99),
(2, 'RO43BCRD4059261705', 501.30),
(3, 'RO27BRFJ92876544700', 1521.30),
(4, 'RO14RDSC46254747831', 3000.00)";

if ($conn->query($sqlInsertAccounts) === TRUE) {
    echo "Account inserted successfully\n";
} else {
    echo "Error inserting accounts: " . $conn->error . "\n";
}

// Árfolyamok beszúrása
$sqlInsertExchangeRates = "INSERT INTO exchange_rates (currency_from, currency_to, exchange_rate) VALUES
('USD', 'RON', 4.73),
('RON', 'USD', 1 / 4.73),
('EUR', 'RON', 4.97),
('RON', 'EUR', 1 / 4.97),
('GBP', 'RON', 6.03),
('RON', 'GBP', 1 / 6.03),
('USD', 'EUR', 0.95),
('EUR', 'USD', 1 / 0.95),
('USD', 'GBP', 0.79),
('GBP', 'USD', 1 / 0.79),
('EUR', 'GBP', 0.82),
('GBP', 'EUR', 1 / 0.82)";


if ($conn->query($sqlInsertExchangeRates) === TRUE) {
    echo "Exchange rates inserted successfully\n";
} else {
    echo "Error inserting exchange rates: " . $conn->error . "\n";
}

$conn->close();
?>
