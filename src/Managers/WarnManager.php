<?php

declare(strict_types=1);

namespace QuarterTg\Managers;

use QuarterTg\Core\Cache;
use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Core\TelegramApi;
use Throwable;

/**
 * مدیریت اخطارهای کاربران
 * 
 * مسئولیتها:
 * - افزایش/کاهش اخطار برای کاربران
 * - بررسی تعداد اخطارها و بن خودکار بعد از آستانه تعیینشده
 * - انقضای خودکار اخطارها بعد از زمان مشخص
 * - کش کردن تعداد اخطارها برای کاهش کوئری
 */
class WarnManager
{
    private Database $db;
    private Cache $cache;
    private Logger $logger;
    private TelegramApi $telegram;
    private AuthorizationManager $authManager;
    private LockManager $lockManager;
    
    /** @var int حداکثر تعداد اخطار قبل از بن */
    private int $maxWarns = 3;
    
    /** @var int زمان انقضای اخطارها به ثانیه (پیشفرض ۲۴ ساعت) */
    private int $warnExpiry = 86400;
    
    /** @var array کش دروندرخواستی تعداد اخطارها */
    private array $warnCache = [];
    
    /** @var int زمان کش (پیشفرض ۵ دقیقه) */
    private int $cacheTtl = 300;

    public function __construct(
        Database $db,
        Cache $cache,
        Logger $logger,
        TelegramApi $telegram,
        AuthorizationManager $authManager,
        LockManager $lockManager,
        int $maxWarns = 3,
        int $warnExpiry = 86400
    ) {
        $this->db = $db;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->telegram = $telegram;
        $this->authManager = $authManager;
        $this->lockManager = $lockManager;
        $this->maxWarns = $maxWarns;
        $this->warnExpiry = $warnExpiry;
    }

    // ============================================================
    // متدهای اصلی
    // ============================================================

    /**
     * افزایش اخطار برای یک کاربر در گروه
     * 
     * @param int $chatId شناسه گروه
     * @param int $userId شناسه کاربر
     * @param string $reason دلیل اخطار (اختیاری)
     * @param int|null $adminId شناسه ادمین صادرکننده (اختیاری)
     * @return array نتیجه شامل وضعیت و پیام
     */
    public function addWarn(int $chatId, int $userId, string $reason = '', ?int $adminId = null): array
    {
        // ۱. بررسی اینکه کاربر ادمین نباشد
        if ($this->authManager->isAdmin($chatId, $userId)) {
            $this->logger->warning('Attempted to warn an admin.', ['chat' => $chatId, 'user' => $userId]);
            return [
                'success' => false,
                'message' => '⛔ نمی‌توانید به ادمین اخطار دهید.',
                'warns'   => 0,
            ];
        }

        // ۲. بررسی اینکه کاربر مالک نباشد
        if ($this->authManager->isOwner($userId)) {
            $this->logger->warning('Attempted to warn the owner.', ['chat' => $chatId, 'user' => $userId]);
            return [
                'success' => false,
                'message' => '⛔ نمی‌توانید به مالک ربات اخطار دهید.',
                'warns'   => 0,
            ];
        }

        try {
            // ۳. شروع تراکنش
            $this->db->beginTransaction();

            // ۴. حذف اخطارهای منقضی شده
            $this->cleanExpiredWarns($chatId, $userId);

            // ۵. دریافت تعداد اخطارهای فعلی
            $currentWarns = $this->getWarnCount($chatId, $userId, false); // false = بدون کش

            // ۶. درج اخطار جدید
            $result = $this->db->insert('warns', [
                'group_id'   => $chatId,
                'user_id'    => $userId,
                'admin_id'   => $adminId ?? 0,
                'reason'     => $reason ?: 'تخلف از قوانین گروه',
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', time() + $this->warnExpiry),
            ]);

            if ($result === false) {
                $this->db->rollback();
                $this->logger->error('Failed to insert warn.', ['chat' => $chatId, 'user' => $userId]);
                return [
                    'success' => false,
                    'message' => '❌ خطا در ثبت اخطار. لطفاً دوباره تلاش کنید.',
                    'warns'   => $currentWarns,
                ];
            }

            // ۷. افزایش تعداد اخطارها (موقت)
            $newWarnCount = $currentWarns + 1;

            // ۸. پاک کردن کش
            $this->clearCache($chatId, $userId);

            // ۹. بررسی برای بن خودکار
            if ($newWarnCount >= $this->maxWarns) {
                // بن کردن کاربر
                $banResult = $this->telegram->banChatMember($chatId, $userId);
                if ($banResult['ok'] ?? false) {
                    // ثبت در جدول bans (اختیاری)
                    $this->db->insert('bans', [
                        'group_id'   => $chatId,
                        'user_id'    => $userId,
                        'admin_id'   => $adminId ?? 0,
                        'reason'     => "اخطار خودکار (تعداد اخطار: {$newWarnCount})",
                        'created_at' => date('Y-m-d H:i:s'),
                        'is_permanent' => 1,
                    ]);

                    $this->db->commit();
                    
                    // ارسال پیام به گروه
                    $this->telegram->sendMessage(
                        $chatId,
                        "🚫 کاربر با شناسه {$userId} به دلیل دریافت {$this->maxWarns} اخطار از گروه بن شد."
                    );

                    $this->logger->info('User auto-banned after warns.', [
                        'chat' => $chatId,
                        'user' => $userId,
                        'warns' => $newWarnCount,
                    ]);

                    return [
                        'success' => true,
                        'message' => "🚫 کاربر بن شد (تعداد اخطار: {$newWarnCount})",
                        'warns'   => $newWarnCount,
                        'banned'  => true,
                    ];
                } else {
                    // اگر بن موفق نبود، تراکنش را Rollback میکنیم
                    $this->db->rollback();
                    $this->logger->error('Auto-ban failed.', [
                        'chat' => $chatId,
                        'user' => $userId,
                        'error' => $banResult['description'] ?? 'Unknown error',
                    ]);
                    return [
                        'success' => false,
                        'message' => '❌ خطا در بن خودکار کاربر. لطفاً دستی اقدام کنید.',
                        'warns'   => $newWarnCount,
                    ];
                }
            }

            // ۱۰. اگر به آستانه نرسیده، تراکنش را Commit میکنیم
            $this->db->commit();

            $this->logger->info('Warn added successfully.', [
                'chat' => $chatId,
                'user' => $userId,
                'warns' => $newWarnCount,
                'max' => $this->maxWarns,
            ]);

            return [
                'success' => true,
                'message' => "⚠️ اخطار ثبت شد. تعداد اخطار: {$newWarnCount} از {$this->maxWarns}",
                'warns'   => $newWarnCount,
                'banned'  => false,
            ];

        } catch (Throwable $e) {
            $this->db->rollback();
            $this->logger->error('Error in addWarn.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => '❌ خطای داخلی در ثبت اخطار.',
                'warns'   => 0,
            ];
        }
    }

    /**
     * کاهش اخطار برای یک کاربر (یک اخطار کمتر)
     */
    public function removeWarn(int $chatId, int $userId): array
    {
        try {
            // حذف آخرین اخطار ثبتشده
            $result = $this->db->queryRow(
                'SELECT id FROM warns 
                 WHERE group_id = ? AND user_id = ? AND expires_at > NOW() 
                 ORDER BY created_at DESC LIMIT 1',
                [$chatId, $userId]
            );

            if ($result === false) {
                return [
                    'success' => false,
                    'message' => '❌ این کاربر اخطاری برای کاهش ندارد.',
                    'warns'   => $this->getWarnCount($chatId, $userId),
                ];
            }

            // حذف اخطار
            $this->db->delete('warns', ['id' => $result['id']]);

            // پاک کردن کش
            $this->clearCache($chatId, $userId);

            $newCount = $this->getWarnCount($chatId, $userId, false);

            $this->logger->info('Warn removed.', ['chat' => $chatId, 'user' => $userId, 'warns' => $newCount]);

            return [
                'success' => true,
                'message' => "✅ یک اخطار کاهش یافت. تعداد فعلی: {$newCount}",
                'warns'   => $newCount,
            ];

        } catch (Throwable $e) {
            $this->logger->error('Error in removeWarn.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => '❌ خطا در کاهش اخطار.',
                'warns'   => $this->getWarnCount($chatId, $userId),
            ];
        }
    }

    /**
     * حذف همه اخطارهای یک کاربر در گروه
     */
    public function clearWarns(int $chatId, int $userId): array
    {
        try {
            $result = $this->db->execute(
                'DELETE FROM warns WHERE group_id = ? AND user_id = ?',
                [$chatId, $userId]
            );

            $this->clearCache($chatId, $userId);

            $this->logger->info('All warns cleared.', ['chat' => $chatId, 'user' => $userId]);

            return [
                'success' => true,
                'message' => '✅ همه اخطارهای کاربر پاک شد.',
                'warns'   => 0,
            ];

        } catch (Throwable $e) {
            $this->logger->error('Error in clearWarns.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => '❌ خطا در پاک کردن اخطارها.',
                'warns'   => $this->getWarnCount($chatId, $userId),
            ];
        }
    }

    // ============================================================
    // متدهای دریافت اطلاعات
    // ============================================================

    /**
     * دریافت تعداد اخطارهای فعال یک کاربر در گروه
     * 
     * @param int $chatId شناسه گروه
     * @param int $userId شناسه کاربر
     * @param bool $useCache استفاده از کش (پیشفرض true)
     * @return int تعداد اخطارها
     */
    public function getWarnCount(int $chatId, int $userId, bool $useCache = true): int
    {
        $cacheKey = "warns_{$chatId}_{$userId}";

        // کش دروندرخواستی
        if ($useCache && isset($this->warnCache[$cacheKey])) {
            return $this->warnCache[$cacheKey];
        }

        // کش فایل
        if ($useCache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null && is_int($cached)) {
                $this->warnCache[$cacheKey] = $cached;
                return $cached;
            }
        }

        // خواندن از دیتابیس (فقط اخطارهای منقضی نشده)
        try {
            $count = $this->db->queryValue(
                'SELECT COUNT(*) FROM warns 
                 WHERE group_id = ? AND user_id = ? AND expires_at > NOW()',
                [$chatId, $userId]
            );
            $count = (int)($count ?: 0);

            // ذخیره در کش
            $this->cache->set($cacheKey, $count, $this->cacheTtl);
            $this->warnCache[$cacheKey] = $count;

            return $count;

        } catch (Throwable $e) {
            $this->logger->error('Failed to get warn count.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * دریافت لیست کامل اخطارهای یک کاربر
     */
    public function getWarns(int $chatId, int $userId): array
    {
        try {
            return $this->db->query(
                'SELECT id, admin_id, reason, created_at, expires_at 
                 FROM warns 
                 WHERE group_id = ? AND user_id = ? AND expires_at > NOW() 
                 ORDER BY created_at DESC',
                [$chatId, $userId]
            );
        } catch (Throwable $e) {
            $this->logger->error('Failed to get warns list.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * دریافت آمار کلی اخطارها در گروه
     */
    public function getGroupStats(int $chatId): array
    {
        try {
            $total = $this->db->queryValue(
                'SELECT COUNT(*) FROM warns WHERE group_id = ? AND expires_at > NOW()',
                [$chatId]
            );
            
            $topUsers = $this->db->query(
                'SELECT user_id, COUNT(*) as count 
                 FROM warns 
                 WHERE group_id = ? AND expires_at > NOW() 
                 GROUP BY user_id 
                 ORDER BY count DESC 
                 LIMIT 10',
                [$chatId]
            );

            return [
                'total_warns' => (int)($total ?: 0),
                'top_users' => $topUsers ?: [],
            ];
        } catch (Throwable $e) {
            $this->logger->error('Failed to get group stats.', ['chat' => $chatId]);
            return ['total_warns' => 0, 'top_users' => []];
        }
    }

    // ============================================================
    // متدهای داخلی
    // ============================================================

    /**
     * پاک کردن اخطارهای منقضی شده
     */
    private function cleanExpiredWarns(int $chatId, int $userId): void
    {
        try {
            $result = $this->db->execute(
                'DELETE FROM warns WHERE group_id = ? AND user_id = ? AND expires_at <= NOW()',
                [$chatId, $userId]
            );
            if ($result > 0) {
                $this->logger->debug('Expired warns cleaned.', ['chat' => $chatId, 'user' => $userId, 'count' => $result]);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Failed to clean expired warns.', ['chat' => $chatId, 'user' => $userId]);
        }
    }

    /**
     * پاک کردن کش اخطارهای یک کاربر
     */
    public function clearCache(int $chatId, int $userId): void
    {
        $cacheKey = "warns_{$chatId}_{$userId}";
        $this->cache->delete($cacheKey);
        unset($this->warnCache[$cacheKey]);
        $this->logger->debug('Warn cache cleared.', ['chat' => $chatId, 'user' => $userId]);
    }

    // ============================================================
    // متدهای تنظیمات
    // ============================================================

    /**
     * تنظیم حداکثر تعداد اخطار قبل از بن
     */
    public function setMaxWarns(int $maxWarns): void
    {
        $this->maxWarns = max(1, $maxWarns);
        $this->logger->info('Max warns updated.', ['max' => $this->maxWarns]);
    }

    /**
     * تنظیم زمان انقضای اخطارها (به ثانیه)
     */
    public function setWarnExpiry(int $seconds): void
    {
        $this->warnExpiry = max(3600, $seconds); // حداقل ۱ ساعت
        $this->logger->info('Warn expiry updated.', ['expiry' => $this->warnExpiry]);
    }

    /**
     * تنظیم زمان TTL کش (به ثانیه)
     */
    public function setCacheTtl(int $ttl): void
    {
        $this->cacheTtl = max(60, $ttl);
    }
}