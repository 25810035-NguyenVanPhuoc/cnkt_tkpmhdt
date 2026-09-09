<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thư viện media dùng chung — một nơi để tra và DÙNG LẠI ảnh đã tải lên, thay vì mỗi lần
 * chọn ảnh lại lưu một file mới không ai tra lại được (tên gốc, kích thước, ai tải lên).
 *
 * `path` là đường dẫn tương đối trên disk `public`, KHÔNG phải URL — URL dựng bằng
 * Storage::disk('public')->url() lúc trả về, để đổi domain không phải sửa dữ liệu.
 * Các bảng khác (product_images...) lưu thẳng chuỗi `path` này, không có ràng buộc khoá
 * ngoại tới đây — xoá một media file đang được dùng sẽ để lại path chết, đây là đánh đổi
 * chấp nhận được ở quy mô hiện tại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
