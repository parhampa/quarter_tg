<?php

declare(strict_types=1);

namespace QuarterTg\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * کلاس مدیریت اتصال به دیتابیس MySQL با PDO
 * 
 * ویژگیها:
 * - Prepared statements امن
 * - پشتیبانی از تراکنشها
 * - متدهای کمکی برای INSERT, UPDATE, DELETE
 * - مدیریت خطا با لاگ کردن
 * - امکان کش کردن نتایج (اختیاری)
 */
class Database
{
    private PDO $pdo;
    private Logger $logger;
    private ?Cache $cache = null;
    private int $queryCount = 0;
    private array $queryLog = [];

    /**
     * @param string $host     میزبان دیتابیس
     * @param string $name     نام دیتابیس
     * @param string $username نام کاربری
     * @param string $password رمز عبور
     * @param string $charset  کاراکترست (پیشفرض utf8mb4)
     * @param Logger $logger   نمونه Logger
     * @param ?Cache $cache    نمونه Cache برای کش کردن نتایج (اختیاری)
     * @param array  $options  گزینههای اضافی PDO
     * @throws \RuntimeException در صورت عدم اتصال
     */
    public function __construct(
        string $host,
        string $name,
        string $username,
        string $password,
        string $charset = 'utf8mb4',
        Logger $logger,
        ?Cache $cache = null,
        array $options = []
    ) {
        $this->logger = $logger;
        $this->cache = $cache;

        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        $defaultOptions = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];

        $options = array_replace($defaultOptions, $options);

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
            $this->logger->info('Database connection established.', ['host' => $host, 'db' => $name]);
        } catch (PDOException $e) {
            $this->logger->critical('Database connection failed: ' . $e->getMessage(), [
                'host' => $host,
                'db'   => $name,
                'code' => $e->getCode(),
            ]);
            throw new \RuntimeException('Database connection error: ' . $e->getMessage(), 0, $e);
        }
    }

    // ============================================================
    // متدهای اصلی کوئری
    // ============================================================

    /**
     * اجرای یک کوئری با پارامترهای bind شده (SELECT)
     * 
     * @param string $sql    کوئری SQL با placeholders
     * @param array  $params آرایه پارامترها (key => value)
     * @param int    $fetchMode حالت fetch (پیشفرض FETCH_ASSOC)
     * @return array|false آرایه نتایج یا false در صورت خطا
     */
    public function query(string $sql, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): array|false
    {
        $this->queryCount++;
        $this->logQuery($sql, $params);

        // اگر کش فعال باشد و کوئری SELECT باشد، از کش میخوانیم
        $cacheKey = null;
        if ($this->cache !== null && stripos(trim($sql), 'select') === 0) {
            $cacheKey = 'db_' . md5($sql . serialize($params));
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                $this->logger->debug('Query result from cache.', ['key' => $cacheKey]);
                return $cached;
            }
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();
            $result = $stmt->fetchAll($fetchMode);

            // ذخیره در کش اگر کوئری SELECT باشد و نتیجه خالی نباشد
            if ($cacheKey !== null && $result !== false && !empty($result)) {
                $this->cache->set($cacheKey, $result, 300); // 5 دقیقه کش
            }

            return $result;
        } catch (PDOException $e) {
            $this->logger->error('Query execution failed: ' . $e->getMessage(), [
                'sql'    => $sql,
                'params' => $params,
                'code'   => $e->getCode(),
            ]);
            return false;
        }
    }

    /**
     * اجرای یک کوئری و برگرداندن اولین ردیف
     * 
     * @param string $sql    کوئری SQL
     * @param array  $params پارامترها
     * @param int    $fetchMode حالت fetch
     * @return array|false اولین ردیف یا false
     */
    public function queryRow(string $sql, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): array|false
    {
        $result = $this->query($sql, $params, $fetchMode);
        if (is_array($result) && count($result) > 0) {
            return $result[0];
        }
        return false;
    }

    /**
     * اجرای یک کوئری و برگرداندن یک مقدار واحد (ستون اول از ردیف اول)
     * 
     * @param string $sql    کوئری SQL
     * @param array  $params پارامترها
     * @return mixed مقدار یا false در صورت خطا
     */
    public function queryValue(string $sql, array $params = []): mixed
    {
        $row = $this->queryRow($sql, $params, PDO::FETCH_NUM);
        if (is_array($row) && count($row) > 0) {
            return $row[0];
        }
        return false;
    }

    /**
     * اجرای یک کوئری غیر انتخابی (INSERT, UPDATE, DELETE, ...)
     * 
     * @param string $sql    کوئری SQL
     * @param array  $params پارامترها
     * @return int تعداد ردیفهای تحت تأثیر یا -1 در صورت خطا
     */
    public function execute(string $sql, array $params = []): int
    {
        $this->queryCount++;
        $this->logQuery($sql, $params);

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logger->error('Execute failed: ' . $e->getMessage(), [
                'sql'    => $sql,
                'params' => $params,
                'code'   => $e->getCode(),
            ]);
            return -1;
        }
    }

    // ============================================================
    // متدهای کمکی برای INSERT, UPDATE, DELETE
    // ============================================================

    /**
     * درج یک ردیف در جدول
     * 
     * @param string $table نام جدول
     * @param array  $data  آرایه کلید => مقدار برای درج
     * @return int|false آخرین ID درجشده یا false در صورت خطا
     */
    public function insert(string $table, array $data): int|false
    {
        if (empty($data)) {
            $this->logger->warning('Insert called with empty data.', ['table' => $table]);
            return false;
        }

        $columns = array_keys($data);
        $placeholders = ':' . implode(', :', $columns);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $this->escapeIdentifier($table),
            $this->escapeIdentifier($columns),
            $placeholders
        );

        $result = $this->execute($sql, $data);
        if ($result >= 0) {
            return (int) $this->pdo->lastInsertId();
        }
        return false;
    }

    /**
     * بهروزرسانی ردیفها در جدول
     * 
     * @param string $table نام جدول
     * @param array  $data  آرایه کلید => مقدار برای بهروزرسانی
     * @param array  $where آرایه شرطها (کلید => مقدار)
     * @param string $operator عملگر بین شرطها (AND یا OR)
     * @return int تعداد ردیفهای بهروزرسانی شده یا -1 در صورت خطا
     */
    public function update(string $table, array $data, array $where, string $operator = 'AND'): int
    {
        if (empty($data)) {
            $this->logger->warning('Update called with empty data.', ['table' => $table]);
            return -1;
        }

        $setParts = [];
        $params = [];
        foreach ($data as $key => $value) {
            $placeholder = ":set_{$key}";
            $setParts[] = "`{$this->escapeIdentifier($key)}` = {$placeholder}";
            $params[$placeholder] = $value;
        }

        $whereParts = [];
        foreach ($where as $key => $value) {
            $placeholder = ":where_{$key}";
            $whereParts[] = "`{$this->escapeIdentifier($key)}` = {$placeholder}";
            $params[$placeholder] = $value;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $this->escapeIdentifier($table),
            implode(', ', $setParts),
            implode(" {$operator} ", $whereParts)
        );

        return $this->execute($sql, $params);
    }

    /**
     * حذف ردیفها از جدول
     * 
     * @param string $table نام جدول
     * @param array  $where آرایه شرطها
     * @param string $operator عملگر بین شرطها
     * @return int تعداد ردیفهای حذفشده یا -1 در صورت خطا
     */
    public function delete(string $table, array $where, string $operator = 'AND'): int
    {
        if (empty($where)) {
            $this->logger->warning('Delete called with empty where condition.', ['table' => $table]);
            return -1;
        }

        $whereParts = [];
        $params = [];
        foreach ($where as $key => $value) {
            $placeholder = ":{$key}";
            $whereParts[] = "`{$this->escapeIdentifier($key)}` = {$placeholder}";
            $params[$placeholder] = $value;
        }

        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $this->escapeIdentifier($table),
            implode(" {$operator} ", $whereParts)
        );

        return $this->execute($sql, $params);
    }

    // ============================================================
    // متدهای تراکنش
    // ============================================================

    public function beginTransaction(): bool
    {
        try {
            $result = $this->pdo->beginTransaction();
            if ($result) {
                $this->logger->debug('Transaction started.');
            }
            return $result;
        } catch (PDOException $e) {
            $this->logger->error('Begin transaction failed: ' . $e->getMessage());
            return false;
        }
    }

    public function commit(): bool
    {
        try {
            $result = $this->pdo->commit();
            if ($result) {
                $this->logger->debug('Transaction committed.');
            }
            return $result;
        } catch (PDOException $e) {
            $this->logger->error('Commit failed: ' . $e->getMessage());
            return false;
        }
    }

    public function rollback(): bool
    {
        try {
            $result = $this->pdo->rollBack();
            if ($result) {
                $this->logger->debug('Transaction rolled back.');
            }
            return $result;
        } catch (PDOException $e) {
            $this->logger->error('Rollback failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * اجرای یک تابع درون تراکنش (بهصورت خودکار commit/rollback)
     * 
     * @param callable $callback تابعی که در تراکنش اجرا میشود
     * @return mixed نتیجه تابع یا false در صورت خطا
     * @throws Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            $this->logger->error('Transaction failed, rolled back.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * بایند کردن پارامترها به استیتمنت
     */
    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            // اگر کلید عددی باشد، از موقعیت (?) استفاده شده
            if (is_int($key)) {
                $stmt->bindValue($key + 1, $value, $this->getPdoType($value));
            } else {
                $stmt->bindValue($key, $value, $this->getPdoType($value));
            }
        }
    }

    /**
     * تشخیص نوع داده برای PDO::PARAM_*
     */
    private function getPdoType(mixed $value): int
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } else {
            return PDO::PARAM_STR;
        }
    }

    /**
     * ایمنسازی نام جدول/ستون (برای استفاده در کوئریهای پویا)
     */
    private function escapeIdentifier(string|array $identifier): string
    {
        if (is_array($identifier)) {
            return implode('`, `', array_map([$this, 'escapeIdentifier'], $identifier));
        }
        // حذف کاراکترهای خطرناک
        return str_replace('`', '``', $identifier);
    }

    /**
     * لاگ کردن کوئریها (فقط در سطح debug)
     */
    private function logQuery(string $sql, array $params): void
    {
        $this->queryLog[] = [
            'sql'    => $sql,
            'params' => $params,
            'time'   => microtime(true),
        ];
        $this->logger->debug('Query executed.', [
            'sql'    => $sql,
            'params' => $params,
            'count'  => $this->queryCount,
        ]);
    }

    // ============================================================
    // متدهای Getter / Status
    // ============================================================

    /**
     * دریافت تعداد کوئریهای اجراشده در این درخواست
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * دریافت لاگ کامل کوئریها (برای دیباگ)
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * دریافت نمونه PDO اصلی (برای موارد خاص)
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * پاک کردن کش کوئریها (در صورت نیاز)
     */
    public function clearQueryCache(): void
    {
        if ($this->cache !== null) {
            // این متد باید در Cache پیادهسازی شود تا بتوان کلیدهای خاص را پاک کرد
            // فعلاً فقط یک هشدار لاگ میکنیم
            $this->logger->warning('Clear query cache called, but not fully implemented.');
        }
    }
}