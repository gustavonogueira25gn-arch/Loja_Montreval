<?php
//conexao
function conexao()
{
    $host = "127.0.0.1";
    $username = "root";
    $password = "";
    $dbname = "Montreval";
    $charset = "utf8mb4";
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    try {
        $pdo = new PDO(
            $dsn,
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Erro ao conectar ao banco de dados.");
    }
}
?>