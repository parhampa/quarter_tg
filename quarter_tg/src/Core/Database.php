<?php

namespace QuarterTg\Core;

/**
 * کلاس مدیریت اتصال به دیتابیس با PDO
 * پشتیبانی از MySQL/MariaDB با Prepared Statements
 */
class Database
{
    private $pdo;
    private $config;
    private $connected = false;
    private $lastError = null;

    /**
     * @param array $config آرایه تنظیمات شامل host, name, user, password, charset
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * برقراری اتصال به دیتابیس
     */
    private function connect(): void
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                $this->config['host'],
                $this->config['name'],
                $this->config['charset'] ?? 'utf8mb4'
            );

            $this->pdo = new \PDO($dsn, $this->config['user'], $this->config['password'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);

            $this->connected = true;
        } catch (\PDOException $e) {
            $this->connected = false;
            $this->lastError = $e->getMessage();
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * اجرای یک کوئری SELECT و برگرداندن همه‌ی رکوردها
     * @return array|false
     */
    public function query(string $sql, array $params = [])
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * اجرای یک کوئری SELECT و برگرداندن یک رکورد
     * @return array|false
     */
    public function queryRow(string $sql, array $params = [])
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * اجرای یک کوئری SELECT و برگرداندن مقدار یک ستون
     * @return mixed|false
     */
    public function queryColumn(string $sql, array $params = [])
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * اجرای یک کوئری INSERT/UPDATE/DELETE و برگرداندن تعداد ردیف‌های تحت تأثیر
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * درج یک رکورد و برگرداندن ID آخرین درج
     */
    public function insert(string $table, array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $sql = sprintf(
            "INSERT INTO `%s` (`%s`) VALUES (%s)",
            $table,
            implode('`, `', $fields),
            implode(', ', $placeholders)
        );

        $stmt = $this->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * به‌روزرسانی یک یا چند رکورد
     */
    public function update(string $table, array $data, array $where, string $operator = 'AND'): int
    {
        $setParts = [];
        $params = [];

        foreach ($data as $key => $value) {
            $setParts[] = "`$key` = ?";
            $params[] = $value;
        }

        $whereParts = [];
        foreach ($where as $key => $value) {
            $whereParts[] = "`$key` = ?";
            $params[] = $value;
        }

        $sql = sprintf(
            "UPDATE `%s` SET %s WHERE %s",
            $table,
            implode(', ', $setParts),
            implode(" $operator ", $whereParts)
        );

        return $this->execute($sql, $params);
    }

    /**
     * حذف رکوردها با شرط
     */
    public function delete(string $table, array $where, string $operator = 'AND'): int
    {
        $whereParts = [];
        $params = [];

        foreach ($where as $key => $value) {
            $whereParts[] = "`$key` = ?";
            $params[] = $value;
        }

        $sql = sprintf(
            "DELETE FROM `%s` WHERE %s",
            $table,
            implode(" $operator ", $whereParts)
        );

        return $this->execute($sql, $params);
    }

    /**
     * آماده‌سازی یک کوئری (از بیرون قابل دسترسی برای Prepared Statements دستی)
     */
    public function prepare(string $sql): \PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    /**
     * آغاز تراکنش
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * تأیید تراکنش
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * بازگشت تراکنش
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * بررسی وضعیت اتصال
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * دریافت آخرین خطا
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * دریافت شیء PDO (برای موارد خاص)
     */
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    /**
     * فراخوانی متدهای PDO به‌صورت داینامیک
     */
    public function __call($method, $args)
    {
        if (method_exists($this->pdo, $method)) {
            return call_user_func_array([$this->pdo, $method], $args);
        }
        throw new \BadMethodCallException("Method $method does not exist in PDO or Database");
    }
}