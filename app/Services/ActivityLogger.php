<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;

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

    /** Nhãn vai trò hiển thị trong log — khớp ROLE_LABEL ở employees/list.vue. */
    private const ROLE_LABEL = [
        'admin' => 'Quản trị viên',
        'sales_staff' => 'NV bán hàng',
        'warehouse_staff' => 'NV kho',
    ];

    private function __construct()
    {
        $this->logPath = storage_path('logs/activity.log');
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function logLoginSuccess(User $user): void
    {
        $this->write('LOGIN_SUCCESS', [
            'nguoi_dung' => $this->userLabel($user),
            'email' => $user->email,
            'ip' => request()?->ip(),
        ]);
    }

    public function logLoginFailed(string $email, string $reason, ?User $user = null): void
    {
        $this->write('LOGIN_FAILED', [
            'nguoi_dung' => $user ? $this->userLabel($user) : $email,
            'ip' => request()?->ip(),
            'reason' => $reason,
        ]);
    }

    public function logOrderCreated(string $orderCode, User $actor, ?Customer $customer, int $grandTotal, string $source): void
    {
        $this->write('ORDER_CREATED', [
            'order_code' => $orderCode,
            'nguoi_tao' => $source === 'storefront' ? 'Khách đặt online (không đăng nhập)' : $this->userLabel($actor),
            'khach_hang' => $customer ? "{$customer->name} ({$customer->phone})" : 'Khách vãng lai',
            'grand_total' => $grandTotal,
            'source' => $source,
        ]);
    }

    /**
     * "Tên nhân viên (Vai trò)", vd: "Nguyễn Văn An (NV bán hàng)" — nhân
     * viên có thể chưa gán vai trò nào (vd admin seed thủ công).
     */
    private function userLabel(User $user): string
    {
        $role = $user->getRoleNames()->first();
        $roleLabel = $role ? (self::ROLE_LABEL[$role] ?? $role) : 'chưa gán vai trò';

        return "{$user->name} ({$roleLabel})";
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
