<?php
namespace Classes;
use DateTime;

class Transaction
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }


    //Frissíti a számla egyenlegét
    public function updateBalance(int $customerId, float $amount): void {
        $conn = $this->db->connect();
        $stmt = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?"); // Az egyenleg frissítése
        $stmt->bind_param("di", $amount, $customerId);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }

    //Egy tranzakció létrehozása
    public function create(int $senderId, int $receiverId, float $amount, string $type): bool {
        $conn = $this->db->connect();
        try {
            $conn->begin_transaction();

            // Ellenőrizzük, hogy elegendő pénz áll-e rendelkezésre
            if ($type === 'transfer') {
                $senderBalance = $this->getBalanceByAccountId($senderId); // A küldő számla egyenlegének lekérdezése
                if ($senderBalance < $amount) {
                    throw new \Exception("Nem elegendő pénz a küldő számlán.");
                }
                $this->updateBalance($senderId, -$amount); // Levonás a küldő számláról
                $this->updateBalance($receiverId, $amount); // Hozzáadás a fogadó számlához
            } elseif ($type === 'deposit') {
                $this->updateBalance($receiverId, $amount); // Befizetés hozzáadása
            } elseif ($type === 'withdraw') {
                $this->updateBalance($senderId, -$amount); // Kivétel levonása
            }
            else {
                throw new \Exception("Érvénytelen tranzakció típus: $type"); // Hibás típus kezelése
            }

            // Tranzakció rögzítése az adatbázisban
            $transactionDate = (new DateTime())->format('Y-m-d H:i:s'); // Aktuális időpont formázása
            $stmt = $conn->prepare(
                "INSERT INTO transactions (sender_account_id, receiver_account_id, amount, transaction_date, type)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iidss", $senderId, $receiverId, $amount, $transactionDate, $type);

            if (!$stmt->execute()) {
                throw new \Exception("Tranzakció rögzítése nem sikerült: " . $stmt->error);
            }

            $conn->commit();
            return true; // Sikeres tranzakció
        } catch (\Exception $e) {
            echo "Transaction failed: " . $e->getMessage();
            $conn->rollback();
            return false; // Sikertelen tranzakció
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
            $conn->close();
        }
    }

    //Egy számla egyenlegének lekérdezése
    public function getBalanceByAccountId(int $accountId): float {
        $conn = $this->db->connect();
        $stmt = $conn->prepare("SELECT balance FROM accounts WHERE id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();

        return $result ? (float) $result['balance'] : 0.0; // Visszatérés az egyenleggel, vagy 0-val, ha nem talál adatot
    }

    //Egy adott felhasználó tranzakciós előzménye
    public function getByCustomer(int $customerId): array {
        $conn = $this->db->connect();
        $stmt = $conn->prepare(
            "SELECT t.*, 
                    sa.account_number AS sender_account_number, 
                    ra.account_number AS receiver_account_number 
             FROM transactions t
             LEFT JOIN accounts sa ON t.sender_account_id = sa.id
             LEFT JOIN accounts ra ON t.receiver_account_id = ra.id
             WHERE t.sender_account_id = ? OR t.receiver_account_id = ?
             ORDER BY t.transaction_date DESC"
        );
        $stmt->bind_param("ii", $customerId, $customerId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $conn->close();
        return $result; // Visszatérés a tranzakciók listájával
    }

    //Egy számla azonosítójának lekérdezése a számlaszám alapján
    public function getAccountIdByAccountNumber($accountNumber) {
        $conn = $this->db->connect();
        $sql = "SELECT id FROM accounts WHERE account_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $accountNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row ? $row['id'] : null; // Az azonosító visszaadása, vagy null
    }
}
