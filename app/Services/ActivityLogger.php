<?php

namespace App\Services;

/**
 * Singleton: ghi log hoạt động (đăng nhập, mua hàng) xuống 1 file dùng
 * chung storage/logs/activity.log. Chỉ một instance mở/ghi file này trong
 * vòng đời request (cùng mẫu với SettingsManager — xem docs/DESIGN_PATTERNS.docx,
 * mục "Singleton theo vòng đời request" do đặc thù PHP xử lý mỗi request
 * trong 1 tiến trình riêng).
 */
final class ActivityLogger
{
    private static ?self $instance = null;

    private string $logPath;

    private function __construct()
    {
        $this->logPath = storage_path('logs/activity.log');
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function logLoginSuccess(int $userId, string $email): void
    {
        $this->write('LOGIN_SUCCESS', [
            'user_id' => $userId,
            'email' => $email,
            'ip' => request()?->ip(),
        ]);
    }

    public function logLoginFailed(string $email, string $reason): void
    {
        $this->write('LOGIN_FAILED', [
            'email' => $email,
            'ip' => request()?->ip(),
            'reason' => $reason,
        ]);
    }

    public function logOrderCreated(string $orderCode, ?int $userId, ?int $customerId, int $grandTotal, string $source): void
    {
        $this->write('ORDER_CREATED', [
            'order_code' => $orderCode,
            'user_id' => $userId,
            'customer_id' => $customerId,
            'grand_total' => $grandTotal,
            'source' => $source,
        ]);
    }

    private function write(string $event, array $context): void
    {
        $pairs = collect($context)
            ->map(fn ($value, $key) => $key.'='.($value ?? 'null'))
            ->implode(' ');

        $line = sprintf('[%s] %s %s'.PHP_EOL, now()->toDateTimeString(), $event, $pairs);

        file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Đọc N dòng cuối của file log (mới nhất ở cuối) để hiển thị trên trang quản trị.
     */
    public function tail(int $lines = 200): array
    {
        if (! is_file($this->logPath)) {
            return [];
        }

        $all = file($this->logPath, FILE_IGNORE_NEW_LINES) ?: [];

        return array_slice($all, -$lines);
    }
}
