<?php
namespace Classes;


class Login
{
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    // Bejelentkezés felvétele az adatbázisba
    public function logIn(int $userId, string $ipAddress, ?string $deviceInfo): bool {
        $conn = $this->db->connect();
        $loginTime = (new \DateTime())->format('Y-m-d H:i:s'); // Az aktuális idő beállítása
        $smt = $conn->prepare("INSERT INTO logins (user_id, login_time, ip_address, device_info) VALUES (?, ?, ?, ?)");
        $smt->bind_param("isss", $userId, $loginTime, $ipAddress, $deviceInfo);
        $smt->execute();
        $smt->close();
        $conn->close();

        return true;
    }

    // Kijelentkezés felvétele az adatbázisban
    public function logOut(int $userId): bool {
        $conn = $this->db->connect();
        $logoutTime = (new \DateTime())->format('Y-m-d H:i:s'); // Az aktuális idő beállítása
        $smt = $conn->prepare("UPDATE logins SET logout_time = ? WHERE user_id = ? AND logout_time IS NULL");
        $smt->bind_param("si", $logoutTime, $userId);
        $smt->execute();
        $smt->close();
        $conn->close();

        return true;
    }

    // Bejelentkezési előzmény lekérdezése
    public function getLoginHistory(int $userId): array {
        $conn = $this->db->connect();
        $smt = $conn->prepare("SELECT * FROM logins WHERE user_id = ?");
        $smt->bind_param("i", $userId);
        $smt->execute();
        $result = $smt->get_result()->fetch_all(MYSQLI_ASSOC);
        $smt->close();
        $conn->close();

        return $result; // Visszaadja a bejelentkezési előzményt
    }


}
