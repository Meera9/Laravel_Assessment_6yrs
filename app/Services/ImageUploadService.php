<?php

namespace App\Services;

use App\Models\Upload;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageUploadService
{
    protected string $publicDisk = 'public';
    protected string $tempDisk = 'local';
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Save a chunk (idempotent: overwrites same chunk index)
     */
    public function saveChunk(Upload $upload, int $index, $content)
    : string
    {
        $dir = "uploads_temp/{$upload->id}";
        Storage::disk($this->tempDisk)->makeDirectory($dir);
        $chunkPath = "{$dir}/chunk_{$index}";
        Storage::disk($this->tempDisk)->put($chunkPath, $content);
        return $chunkPath;
    }

    /**
     * Return true if the number of chunk files >= expected
     */
    public function isUploadComplete(Upload $upload)
    : bool
    {
        $dir = "uploads_temp/{$upload->id}";
        $all = Storage::disk($this->tempDisk)->files($dir);
        $count = collect($all)->filter(fn($f) => str_contains($f, 'chunk_'))->count();
        return $count >= (int) $upload->total_chunks;
    }

    public function assembleAndProcess(Upload $upload)
    {
        $tempDir = "uploads_temp/{$upload->id}";
        $assembledLocal = storage_path("app/{$tempDir}/assembled_{$upload->id}_" . Str::slug(pathinfo($upload->original_filename, PATHINFO_FILENAME)) . "." . pathinfo($upload->original_filename, PATHINFO_EXTENSION));

        if ( !is_dir(dirname($assembledLocal)) ) {
            mkdir(dirname($assembledLocal), 0755, true);
        }

        $fp = fopen($assembledLocal, 'ab');
        if ( $fp === false ) {
            throw new \RuntimeException("Cannot open assembled file for writing: {$assembledLocal}");
        }

        $total = (int) $upload->total_chunks;
        for ($i = 0; $i < $total; $i++) {
            $chunkPath = "{$tempDir}/chunk_{$i}";
            if ( !Storage::disk($this->tempDisk)->exists($chunkPath) ) {
                fclose($fp);
                throw new \RuntimeException("Missing chunk {$i}");
            }
            $stream = Storage::disk($this->tempDisk)->readStream($chunkPath);
            if ( $stream === false ) {
                fclose($fp);
                throw new \RuntimeException("Cannot read chunk {$i}");
            }
            stream_copy_to_stream($stream, $fp);
            fclose($stream);
        }
        fclose($fp);

        if ( !file_exists($assembledLocal) || filesize($assembledLocal) === 0 ) {
            throw new \RuntimeException("Assembled file missing or empty");
        }

        if ( $upload->checksum ) {
            $actual = md5_file($assembledLocal);
            if ( strtolower($actual) !== strtolower($upload->checksum) ) {
                $upload->status = 'failed';
                $upload->save();
                // cleanup assembled file
                @unlink($assembledLocal);
                throw new \RuntimeException("Checksum mismatch: expected {$upload->checksum}, got {$actual}");
            }
        }

        $ext = strtolower(pathinfo($assembledLocal, PATHINFO_EXTENSION));
        $publicDir = "uploads/{$upload->user_id}";
        Storage::disk($this->publicDisk)->makeDirectory($publicDir);

        // If SVG: just store original (no raster variants)
        $variantPaths = [];
        if ( $ext === 'svg' ) {
            $mime = 'image/svg+xml';
            $width = $height = null;

            $origPath = "{$publicDir}/orig_{$upload->id}." . $ext;
            Storage::disk($this->publicDisk)->put($origPath, file_get_contents($assembledLocal));
        } else {
            $binary = file_get_contents($assembledLocal);
            try {
                $img = $this->manager->read($binary);
            } catch (\Throwable $e) {
                throw new \RuntimeException("Image decode failed: " . $e->getMessage());
            }

            $width = $img->width();
            $height = $img->height();
            $mime = mime_content_type($assembledLocal) ?: null;

            $origPath = "{$publicDir}/orig_{$upload->id}." . $ext;
            Storage::disk($this->publicDisk)->put($origPath, $binary);

            $sizes = [256, 512, 1024];
            foreach ($sizes as $size) {
                $variantImg = $this->manager->read($binary);
                if ( $variantImg->width() > $size ) {
                    $variantImg->resize($size, null, function ($c) {
                        $c->aspectRatio();
                        $c->upsize();
                    });
                }
                $variantPath = "{$publicDir}/{$size}_{$upload->id}." . $ext;
                $encoded = $this->encodeImageBinary($variantImg, $ext);
                Storage::disk($this->publicDisk)->put($variantPath, $encoded);
                $variantPaths[$size] = $variantPath;
            }
        }

        return DB::transaction(function () use ($upload, $origPath, $variantPaths, $mime, $width, $height, $ext, $assembledLocal) {
            $upload->status = 'completed';
            $upload->filename = basename($origPath);
            $upload->save();

            $existing = Image::where('upload_id', $upload->id)->where('user_id', $upload->user_id)->first();
            if ( $existing ) {
                @unlink($assembledLocal);
                return $existing;
            }

            Image::where('user_id', $upload->user_id)->where('is_primary', true)->update(['is_primary' => false]);

            $img = Image::create([
                'upload_id'    => $upload->id,
                'user_id'      => $upload->user_id,
                'path'         => $origPath,
                'variant_256'  => $variantPaths[256] ?? null,
                'variant_512'  => $variantPaths[512] ?? null,
                'variant_1024' => $variantPaths[1024] ?? null,
                'mime'         => $mime,
                'width'        => $width,
                'height'       => $height,
                'is_primary'   => true,
            ]);

            @unlink($assembledLocal);
            return $img;
        });
    }

    protected function encodeImageBinary($imageInstance, string $ext)
    : string
    {
        $ext = strtolower($ext);
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                return $imageInstance->toJpeg();
            case 'png':
                return $imageInstance->toPng();
            case 'webp':
                return $imageInstance->toWebp();
            default:
                return $imageInstance->toJpeg();
        }
    }

    public function cleanupChunks(Upload $upload)
    : void
    {
        $dir = "uploads_temp/{$upload->id}";
        $files = Storage::disk($this->tempDisk)->files($dir);
        foreach ($files as $f) {
            if ( str_contains($f, 'chunk_') ) {
                Storage::disk($this->tempDisk)->delete($f);
            }
        }
        @rmdir(storage_path("app/{$dir}"));
    }
}
