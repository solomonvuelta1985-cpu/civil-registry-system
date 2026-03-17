<?php
/**
 * One-time Migration Script
 * Moves existing PDF files from flat uploads/ into organized subfolders:
 *   uploads/{type}/{registration_year}/filename.pdf
 *
 * Updates pdf_filename and pdf_filepath in all certificate tables.
 *
 * Usage: php migrate_pdfs.php
 *   or visit: http://localhost/iscan/database/migrations/migrate_pdfs.php
 */

require_once __DIR__ . '/../../includes/config.php';

echo "<pre>\n";
echo "=== PDF Migration Script ===\n";
echo "Moving files from flat uploads/ to organized subfolders\n\n";

$tables = [
    'birth' => [
        'table' => 'certificate_of_live_birth',
        'date_field' => 'date_of_registration'
    ],
    'death' => [
        'table' => 'certificate_of_death',
        'date_field' => 'date_of_registration'
    ],
    'marriage' => [
        'table' => 'certificate_of_marriage',
        'date_field' => 'date_of_registration'
    ],
    'marriage_license' => [
        'table' => 'application_for_marriage_license',
        'date_field' => 'date_of_application'
    ]
];

$moved = 0;
$skipped = 0;
$errors = 0;

foreach ($tables as $type => $config) {
    echo "--- Processing: {$config['table']} (type: {$type}) ---\n";

    $stmt = $pdo->prepare("SELECT id, pdf_filename, pdf_filepath, {$config['date_field']} FROM {$config['table']} WHERE pdf_filename IS NOT NULL AND pdf_filename != ''");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "  Found " . count($records) . " records with PDF files\n";

    foreach ($records as $record) {
        $old_filename = $record['pdf_filename'];

        // Skip if already in a subfolder (already migrated)
        if (strpos($old_filename, '/') !== false) {
            echo "  [SKIP] ID {$record['id']}: Already migrated ({$old_filename})\n";
            $skipped++;
            continue;
        }

        // Determine registration year
        $date_value = $record[$config['date_field']];
        if (!empty($date_value)) {
            $year = date('Y', strtotime($date_value));
        } else {
            $year = date('Y');
            echo "  [WARN] ID {$record['id']}: No date, using current year ({$year})\n";
        }

        // Build new path
        $new_relative = $type . '/' . $year . '/' . $old_filename;
        $old_full_path = UPLOAD_DIR . $old_filename;
        $new_dir = UPLOAD_DIR . $type . '/' . $year . '/';
        $new_full_path = $new_dir . $old_filename;

        // Check if source file exists
        if (!file_exists($old_full_path)) {
            echo "  [ERR]  ID {$record['id']}: Source file not found ({$old_filename})\n";
            $errors++;
            continue;
        }

        // Create target directory
        if (!is_dir($new_dir)) {
            mkdir($new_dir, 0755, true);
            echo "  [DIR]  Created: {$type}/{$year}/\n";
        }

        // Move file
        if (rename($old_full_path, $new_full_path)) {
            // Update database
            $update = $pdo->prepare("UPDATE {$config['table']} SET pdf_filename = :new_filename, pdf_filepath = :new_filepath WHERE id = :id");
            $update->execute([
                ':new_filename' => $new_relative,
                ':new_filepath' => $new_full_path,
                ':id' => $record['id']
            ]);

            echo "  [OK]   ID {$record['id']}: {$old_filename} -> {$new_relative}\n";
            $moved++;
        } else {
            echo "  [ERR]  ID {$record['id']}: Failed to move {$old_filename}\n";
            $errors++;
        }
    }

    echo "\n";
}

echo "=== Migration Complete ===\n";
echo "Moved: {$moved} | Skipped: {$skipped} | Errors: {$errors}\n";
echo "</pre>\n";
