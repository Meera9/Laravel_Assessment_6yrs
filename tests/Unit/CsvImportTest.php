<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class CsvImportTest extends TestCase
{
    public function test_csv_upsert_imports_and_updates()
    {
        Storage::fake('local');

        $csv = "name,email,phone\nJohn Doe,john@example.com,123\nJane Roe,jane@example.com,456\nJohn Doe,john@example.com,123\n";
        $file = tmpfile();
        $meta = stream_get_meta_data($file);
        $tmpPath = $meta['uri'];
        file_put_contents($tmpPath, $csv);

        $response = $this->postJson('/api/import-csv', [
            'csv_file' => new UploadedFile($tmpPath, 'test.csv', null, null, true),
        ]);

        $response->assertStatus(200);
        $summary = $response->json('summary');
        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(2, $summary['imported']);
        $this->assertEquals(0, $summary['updated']);
        $this->assertEquals(0, $summary['invalid']);
        $this->assertEquals(1, $summary['duplicates']);

        // re-run with updated name to test update
        $csv2 = "name,email,phone\nJohn Updated,john@example.com,999\n";
        file_put_contents($tmpPath, $csv2);
        $response2 = $this->postJson('/api/import-csv', [
            'csv_file' => new UploadedFile($tmpPath, 'test2.csv', null, null, true),
        ]);
        $summary2 = $response2->json('summary');
        $this->assertEquals(1, $summary2['total']);
        $this->assertEquals(0, $summary2['imported']);
        $this->assertEquals(1, $summary2['updated']);
    }
}
