<?php
namespace Classes;
use Exception;

class Account
{
    private int $id;
    private string $accountNumber;
    private string $password;
    private float $balance; // RON egyenleg
    private float $balanceEUR; // EUR egyenleg
    private float $balanceUSD; // USD egyenleg
    private float $balanceGBP; // GBP egyenleg
    private Database $db;
    private string $username;
    private string $email;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    // Getterek
    public function getBalance(): float
    {
        return $this->balance;
    }

    public function getBalanceEUR(): float
    {
        return $this->balanceEUR;
    }

    public function getBalanceUSD(): float
    {
        return $this->balanceUSD;
    }

    public function getBalanceGBP(): float
    {
        return $this->balanceGBP;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    // Lekérdezi a számla adatait az email cím alapján
    public function getByEmail(string $email): ?array {
        $conn = $this->db->connect();
        $smt = $conn->prepare("SELECT * FROM accounts WHERE user_id = (SELECT id FROM users WHERE email = ?)");
        $smt->bind_param("s", $email);
        $smt->execute();
        $result = $smt->get_result()->fetch_assoc();
        $smt->close();

        if ($result) {
            $this->id = $result["id"];
            $this->accountNumber = $result["account_number"];
            $this->balance = (float) $result['balance'];
            $this->balanceEUR = (float) $result['balance_eur'];
            $this->balanceUSD = (float) $result['balance_usd'];
            $this->balanceGBP = (float) $result['balance_gbp'];

            // Felhasználó adatok lekérdezése és inicializálása
            $userStmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $userStmt->bind_param("s", $email);
            $userStmt->execute();
            $userResult = $userStmt->get_result()->fetch_assoc();
            $userStmt->close();

            if ($userResult) {
                $this->username = $userResult["name"];
                $this->email = $userResult["email"];
                $this->password = $userResult["password"];
            }

            $conn->close();
            return $result;
        }

        $conn->close();
        return null; // Ha nem található adat, null-t ad vissza
    }

    // Számlaszám alapján keres
    public function getByAccountNumber(string $accountNumber): ?array {
        $conn = $this->db->connect();
        $smt = $conn->prepare("SELECT * FROM accounts WHERE account_number = ?");
        $smt->bind_param("s", $accountNumber);
        $smt->execute();
        $result = $smt->get_result()->fetch_assoc();
        $smt->close();

        if ($result) {
            // Számla adatok inicializálása
            $this->id = $result["id"];
            $this->accountNumber = $result["account_number"];
            $this->balance = (float) $result['balance'];
            $this->balanceEUR = (float) $result['balance_eur'];
            $this->balanceUSD = (float) $result['balance_usd'];
            $this->balanceGBP = (float) $result['balance_gbp'];

            // Felhasználó adatok lekérdezése
            $userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $userStmt->bind_param("i", $result["user_id"]);
            $userStmt->execute();
            $userResult = $userStmt->get_result()->fetch_assoc();
            $userStmt->close();

            if ($userResult) {
                $this->username = $userResult["name"];
                $this->email = $userResult["email"];
                $this->password = $userResult["password"];
            }

            $conn->close();
            return $result;
        }

        $conn->close();
        return null; // Ha nem található adat, null-t ad vissza
    }

    // Jelszó ellenőrzése
    public function verifyPassword(string $password): bool {
        return password_verify($password, $this->password);
    }


    // Árfolyam lekérdezése két valuta között
    public function getExchangeRate(string $currencyFrom, string $currencyTo): ?float {
        $conn = $this->db->connect();
        $smt = $conn->prepare("SELECT exchange_rate FROM exchange_rates WHERE currency_from = ? AND currency_to = ?");
        $smt->bind_param("ss", $currencyFrom, $currencyTo);
        $smt->execute();
        $result = $smt->get_result()->fetch_assoc();
        $smt->close();
        $conn->close();

        return $result ? (float) $result['exchange_rate'] : null;
    }

    // Valutaváltás a megadott összegre két valuta között
    public function convertCurrency(float $amount, string $currencyFrom, string $currencyTo): float {
        $exchangeRate = $this->getExchangeRate($currencyFrom, $currencyTo);
        if ($exchangeRate === null) {
            throw new Exception("Hiba lépett fel az átváltás közben.");
        }

        return $amount * $exchangeRate; // Konvertált összeg visszaadása
    }

    // Egyenleg frissítése egy adott valutában
    public function updateBalanceForCurrency(string $currency, float $amount): void {
        $conn = $this->db->connect();
        $conn->begin_transaction();

        try {
            // Az adott valuta egyenlegének frissítése
            switch ($currency) {
                case 'RON':
                    $smt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
                    $smt->bind_param("di", $amount, $this->id);
                    break;
                case 'EUR':
                    $smt = $conn->prepare("UPDATE accounts SET balance_eur = ? WHERE id = ?");
                    $smt->bind_param("di", $amount, $this->id);
                    break;
                case 'USD':
                    $smt = $conn->prepare("UPDATE accounts SET balance_usd = ? WHERE id = ?");
                    $smt->bind_param("di", $amount, $this->id);
                    break;
                case 'GBP':
                    $smt = $conn->prepare("UPDATE accounts SET balance_gbp = ? WHERE id = ?");
                    $smt->bind_param("di", $amount, $this->id);
                    break;
                default:
                    throw new Exception("Hiba lépett fel az átváltás közben.");

            }

            $smt->execute();
            $smt->close();
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        } finally {
            $conn->close();
        }
    }
}
