<?php

namespace Reelz222z\CryptoExchange;

use PDO;

class User
{
    private string $name;
    private Wallet $wallet;
    private array $portfolio;
    private int $id;

    public function __construct(
        string $name,
        Wallet $wallet,
        array $portfolio = [],
        int $id = 0
    ) {
        $this->name = $name;
        $this->wallet = $wallet;
        $this->portfolio = $portfolio;
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWallet(): Wallet
    {
        return $this->wallet;
    }

    public function getPortfolio(): array
    {
        return $this->portfolio;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function buyCryptocurrency(Cryptocurrency $crypto, float $amount): void
    {
        $this->wallet->deduct($crypto->getQuote()->getPrice() * $amount);
        $symbol = $crypto->getSymbol();
        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare(
            "INSERT INTO portfolio (
                user_id, symbol, amount, price, last_updated
            ) VALUES (
                :user_id, :symbol, :amount, :price, :last_updated
            )"
        );
        $stmt->execute([
            ':user_id' => $this->id,
            ':symbol' => $symbol,
            ':amount' => $amount,
            ':price' => $crypto->getQuote()->getPrice(),
            ':last_updated' => $crypto->getQuote()->getLastUpdated()
        ]);

        if (!isset($this->portfolio[$symbol])) {
            $this->portfolio[$symbol] = [];
        }
        $this->portfolio[$symbol][] = [
            'date' => date('Y-m-d H:i:s'),
            'symbol' => $crypto->getSymbol(),
            'amount' => $amount,
            'price' => $crypto->getQuote()->getPrice(),
            'total' => $crypto->getQuote()->getPrice() * $amount,
            'last_updated' => $crypto->getQuote()->getLastUpdated(),
        ];
    }

    public function sellCryptocurrency(Cryptocurrency $crypto, float $amount): void
    {
        $symbol = $crypto->getSymbol();
        $totalAmount = 0;

        foreach ($this->portfolio[$symbol] as $key => $item) {
            $totalAmount += $item['amount'];
        }

        if ($totalAmount < $amount) {
            throw new \Exception('Insufficient cryptocurrency to sell');
        }

        foreach ($this->portfolio[$symbol] as $key => &$item) {
            if ($amount <= 0) {
                break;
            }

            if ($item['amount'] <= $amount) {
                $amount -= $item['amount'];
                unset($this->portfolio[$symbol][$key]);
            } else {
                $item['amount'] -= $amount;
                $amount = 0;
            }
        }

        $this->portfolio[$symbol] = array_values($this->portfolio[$symbol]);
        $this->wallet->add($crypto->getQuote()->getPrice() * $amount);

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "DELETE FROM portfolio 
            WHERE user_id = :user_id AND symbol = :symbol AND amount <= :amount"
        );
        $stmt->execute([
            ':user_id' => $this->id,
            ':symbol' => $symbol,
            ':amount' => $amount
        ]);
    }

    public static function loadUsers(): array
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->query("SELECT * FROM users");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $users = [];
        foreach ($data as $userData) {
            $wallet = new Wallet((float)$userData['wallet_balance']);
            $portfolio = self::loadPortfolio((int)$userData['id']);
            $users[] = new self(
                $userData['name'],
                $wallet,
                $portfolio,
                (int)$userData['id']
            );
        }
        return $users;
    }

    public static function saveUser(self $user): void
    {
        $pdo = Database::getInstance()->getConnection();
        if ($user->getId() === 0) {
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, wallet_balance) 
                VALUES (:name, :wallet_balance)"
            );
            $stmt->execute([
                ':name' => $user->getName(),
                ':wallet_balance' => $user->getWallet()->getBalance()
            ]);
            $user->id = (int)$pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare(
                "UPDATE users SET wallet_balance = :wallet_balance WHERE id = :id"
            );
            $stmt->execute([
                ':wallet_balance' => $user->getWallet()->getBalance(),
                ':id' => $user->getId()
            ]);
        }
    }

    public static function loadPortfolio(int $userId): array
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT * FROM portfolio WHERE user_id = :user_id"
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
