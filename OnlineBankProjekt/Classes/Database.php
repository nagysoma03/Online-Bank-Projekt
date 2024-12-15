<?php
namespace Classes;
class Database
{
    private string $password = "";
    private string $database = "online_bank";

    private string $host = "localhost";
    private string $user = "root";

    public function connect() {
        $conn = mysqli_connect($this->host, $this->user, $this->password, $this->database);
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        return $conn;
    }
}