<?php

require 'vendor/autoload.php';

use Reelz222z\CryptoExchange\Database;

$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare(
    "INSERT INTO users (name, wallet_balance) 
    VALUES (:name, :wallet_balance)"
);
$stmt->execute([
    ':name' => 'Jane Smith',
    ':wallet_balance' => 1000.0
]);

echo "Test user inserted.\n";
