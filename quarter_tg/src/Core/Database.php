<?php
namespace Core;

class Database
{
    private $connection;
    private static $instance = null;

    private function __construct(array $config)
    {
        $this->connection = new \mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database']
        );
        if ($this->connection->connect_error) {
            throw new \Exception("Database connection failed: " . $this->connection->connect_error);
        }
        $this->connection->set_charset($config['charset'] ?? 'utf8mb4');
    }

    public static function getInstance(array $config): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function getConnection(): \mysqli
    {
        return $this->connection;
    }

    public function query(string $sql): ?\mysqli_result
    {
        $result = $this->connection->query($sql);
        if ($result === false) {
            throw new \Exception("Query error: " . $this->connection->error . " SQL: " . $sql);
        }
        return $result;
    }

    public function fetchAll(string $sql): array
    {
        $result = $this->query($sql);
        if ($result === null) {
            return [];
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function fetchOne(string $sql): ?array
    {
        $result = $this->query($sql);
        if ($result === null || $result->num_rows === 0) {
            return null;
        }
        return $result->fetch_assoc();
    }

    public function execute(string $sql): bool
    {
        $result = $this->connection->query($sql);
        if ($result === false) {
            throw new \Exception("Execute error: " . $this->connection->error . " SQL: " . $sql);
        }
        return true;
    }

    public function escapeString(string $string): string
    {
        return $this->connection->real_escape_string($string);
    }

    public function getLastInsertId(): int
    {
        return $this->connection->insert_id;
    }

    public function close(): void
    {
        $this->connection->close();
    }
}