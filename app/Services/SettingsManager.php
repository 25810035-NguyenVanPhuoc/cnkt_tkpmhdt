<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

/**
 * Singleton: chỉ một instance đọc/ghi bảng settings dùng chung toàn hệ thống
 * (đúng như ghi chú thiết kế ở docs/DESIGN_PATTERNS.docx). Cache toàn bộ
 * key/value vào $cache khi khởi tạo lần đầu trong request, các lần gọi
 * SettingsManager::instance() sau đó trong cùng request tái dùng cache này
 * thay vì query lại DB.
 */
final class SettingsManager
{
    private static ?self $instance = null;

    private array $cache;

    private function __construct()
    {
        $this->cache = Setting::query()->pluck('value', 'key')->all();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->cache[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->cache;
    }

    public function set(string $key, ?string $value, User $actor, string $type = 'string', ?string $group = null): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => $group, 'updated_by' => $actor->id],
        );

        $this->cache[$key] = $value;
    }
}
