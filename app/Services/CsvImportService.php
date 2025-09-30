<?php

namespace App\Services;

use App\Models\User;

class CsvImportService
{
    public function importUsers($filePath)
    : array
    {
        $rows = array_map('str_getcsv', file($filePath));
        $header = array_map('trim', array_shift($rows));

        $summary = ['total' => 0, 'imported' => 0, 'updated' => 0, 'invalid' => 0, 'duplicates' => 0];
        $processedEmails = [];

        foreach ($rows as $row) {
            $summary['total']++;
            $data = array_combine($header, $row);

            if ( !isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) ) {
                $summary['invalid']++;
                continue;
            }

            $email = strtolower(trim($data['email']));
            if ( in_array($email, $processedEmails) ) {
                $summary['duplicates']++;
                continue;
            }
            $processedEmails[] = $email;

            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $data['name'] ?? '']
            );

            $summary[$user->wasRecentlyCreated ? 'imported' : 'updated']++;
        }

        return $summary;
    }
}
