<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

/**
 * Resize ảnh upload trước khi lưu — ảnh chụp điện thoại hiện đại thường vài MB/cạnh 4000px,
 * trong khi thumbnail/gallery không cần quá 1600px. Giữ nguyên định dạng gốc (AutoEncoder)
 * để tránh lệch giữa phần mở rộng lưu trong DB và định dạng bytes thật.
 */
class ImageOptimizer
{
    /** Cạnh của thumbnail vuông. */
    public const THUMB = 200;

    /**
     * Lưu ảnh đã resize vào disk `public`, sinh thumbnail, trả về đầy đủ metadata.
     *
     * @return array{path:string,mime_type:string,size:int,width:int,height:int}
     */
    public function storeWithMetadata(UploadedFile $file, string $folder, int $maxWidth = 1600): array
    {
        $image = Image::decode($file->getRealPath())->scaleDown(width: $maxWidth);
        $content = (string) $image->encode(new AutoEncoder(quality: 82));

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $path = trim($folder, '/').'/'.Str::random(40).'.'.$extension;

        Storage::disk('public')->put($path, $content);

        // Đọc kích thước từ ảnh đã resize (không phải file gốc) — khớp với file thật đang lưu.
        $width = $image->width();
        $height = $image->height();

        $this->generateThumbnail($path, $image);

        return [
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => strlen($content),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Thumbnail vuông, cắt vừa khung, đặt cạnh file gốc theo quy ước `{tên}-{THUMB}x{THUMB}.{đuôi}`.
     *
     * Lỗi ở đây không được làm hỏng lượt upload: mất thumbnail thì client tạm dùng ảnh gốc.
     */
    private function generateThumbnail(string $path, ImageInterface $image): void
    {
        try {
            Storage::disk('public')->put(
                self::thumbnailPath($path),
                (string) $image->cover(self::THUMB, self::THUMB)->encode(new AutoEncoder(quality: 78)),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Suy đường dẫn thumbnail từ đường dẫn gốc. Không kiểm file có tồn tại hay không. */
    public static function thumbnailPath(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = $extension === '' ? $path : substr($path, 0, -(strlen($extension) + 1));

        return $base.'-'.self::THUMB.'x'.self::THUMB.($extension === '' ? '' : '.'.$extension);
    }
}
