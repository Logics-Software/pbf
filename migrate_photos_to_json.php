<?php
/**
 * Migration script to convert single photo paths to JSON array format
 * Run this script once after updating the database schema
 */

require_once __DIR__ . '/includes/db.php';

echo "Starting photo migration...\n";

try {
    $pdo = get_pdo_connection();
    
    // Check if the foto column is already JSON type
    $stmt = $pdo->query("SHOW COLUMNS FROM masterbarang LIKE 'foto'");
    $column = $stmt->fetch();
    
    if (!$column) {
        echo "Error: foto column not found in masterbarang table\n";
        exit(1);
    }
    
    if ($column['Type'] !== 'json') {
        echo "Error: foto column is not JSON type. Please run the database schema update first.\n";
        exit(1);
    }
    
    // Get all records with non-null foto values
    $stmt = $pdo->query("SELECT id, foto FROM masterbarang WHERE foto IS NOT NULL AND foto != ''");
    $records = $stmt->fetchAll();
    
    echo "Found " . count($records) . " records with photos to migrate\n";
    
    $updated = 0;
    $skipped = 0;
    
    foreach ($records as $record) {
        $id = $record['id'];
        $foto = $record['foto'];
        
        // Check if it's already in JSON format
        if (str_starts_with($foto, '[') && str_ends_with($foto, ']')) {
            // Try to decode to verify it's valid JSON
            $decoded = json_decode($foto, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                echo "Record ID $id: Already in JSON format, skipping\n";
                $skipped++;
                continue;
            }
        }
        
        // Convert single photo path to JSON array
        $jsonFoto = json_encode([$foto]);
        
        // Update the record
        $updateStmt = $pdo->prepare("UPDATE masterbarang SET foto = ? WHERE id = ?");
        $updateStmt->execute([$jsonFoto, $id]);
        
        echo "Record ID $id: Converted '$foto' to JSON array\n";
        $updated++;
    }
    
    echo "\nMigration completed!\n";
    echo "Updated: $updated records\n";
    echo "Skipped: $skipped records\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>
