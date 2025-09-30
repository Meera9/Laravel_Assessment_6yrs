<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Upload;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    protected ImageUploadService $service;

    public function __construct(ImageUploadService $service)
    {
        $this->service = $service;
    }

    /**
     * init: create Upload record; returns upload_id
     * POST /api/upload/init
     * body: user_id, filename, total_chunks, checksum
     */
    public function init(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'filename' => 'required|string',
            'total_chunks' => 'required|integer|min:1',
            'checksum' => 'required|string',
        ]);

        $upload = Upload::create([
            'user_id' => $data['user_id'],
            'original_filename' => $data['filename'],
            'filename' => $data['filename'],
            'checksum' => $data['checksum'],
            'total_chunks' => $data['total_chunks'],
            'status' => 'uploading'
        ]);

        return response()->json(['upload_id' => $upload->id]);
    }

    /**
     * Save chunk
     * POST /api/upload/{upload}/chunk
     * FormData: chunk_index, file
     */
    public function chunk(Request $request, Upload $upload)
    {
        $request->validate([
            'chunk_index' => 'required|integer|min:0',
            'file' => 'required|file',
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());
        $this->service->saveChunk($upload, (int)$request->input('chunk_index'), $content);

        return response()->json(['ok' => true]);
    }

    /**
     * Complete upload: assemble, validate, generate variants
     * POST /api/upload/{upload}/complete
     */
    public function complete(Request $request, Upload $upload)
    {
        // ensure upload exists
        if ($upload->status !== 'uploading') {
            return response()->json(['error' => 'Upload not in uploading state'], 422);
        }

        // ensure all chunks present
        if (!$this->service->isUploadComplete($upload)) {
            return response()->json(['error' => 'Not all chunks uploaded'], 422);
        }

        try {
            $image = $this->service->assembleAndProcess($upload);
            $this->service->cleanupChunks($upload);

            // return public URLs for original/variants
            $disk = Storage::disk('public');
            return response()->json([
                'ok' => true,
                'image' => [
                    'id' => $image->id,
                    'original_url' => $disk->url($image->path),
                    'variant_256' => $image->variant_256 ? $disk->url($image->variant_256) : null,
                    'variant_512' => $image->variant_512 ? $disk->url($image->variant_512) : null,
                    'variant_1024' => $image->variant_1024 ? $disk->url($image->variant_1024) : null,
                ]
            ]);
        } catch (\Throwable $e) {
            // mark failed
            $upload->status = 'failed';
            $upload->save();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
