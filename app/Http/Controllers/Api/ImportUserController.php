<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class ImportUserController extends Controller
{
    public function import(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('csv_file')->getRealPath();
        $handle = fopen($path, 'r');
        if ( !$handle ) {
            return response()->json(['error' => 'Cannot open file'], 500);
        }

        $header = fgetcsv($handle);
        if ( !$header ) {
            return response()->json(['error' => 'Empty CSV'], 422);
        }

        // required columns
        $required = ['email', 'name'];
        if ( array_diff($required, $header) ) {
            return response()->json(['error' => 'Missing required columns (email,name)'], 422);
        }

        $summary = ['total' => 0, 'imported' => 0, 'updated' => 0, 'invalid' => 0, 'duplicates' => 0];
        $seen = [];

        while (($row = fgetcsv($handle)) !== false) {
            $summary['total']++;
            $data = array_combine($header, $row);

            // missing columns -> invalid
            if ( empty($data['email']) || empty($data['name']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) ) {
                $summary['invalid']++;
                continue;
            }

            $email = strtolower(trim($data['email']));
            if ( in_array($email, $seen) ) {
                $summary['duplicates']++;
                continue;
            }
            $seen[] = $email;

            $existing = User::where('email', $email)->first();
            if ( $existing ) {
                $existing->update([
                    'name'  => $data['name'],
                    'phone' => $data['phone'] ?? null,
                ]);
                $summary['updated']++;
            } else {
                User::create([
                    'name'  => $data['name'],
                    'email' => $email,
                    'phone' => $data['phone'] ?? null,
                ]);
                $summary['imported']++;
            }
        }
        fclose($handle);

        return response()->json(['success' => true, 'summary' => $summary]);
    }
}
