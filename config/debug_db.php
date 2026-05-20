<?php
// debug_db.php - Test database connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Testing Database Connection</h3>";

try {
    // Test database.php directly
    require_once 'config/database.php';
    
    echo "✓ database.php loaded successfully<br>";
    
    $database = new Database();
    echo "✓ Database class instantiated<br>";
    
    $db = $database->getConnection();
    
    if ($db) {
        echo "✓ Database connection successful!<br>";
        echo "Connection object type: " . get_class($db) . "<br>";
        
        // Test a simple query
        $stmt = $db->query("SELECT 1 as test");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✓ Test query executed successfully: " . $result['test'] . "<br>";
        
        // Test if we can access the database
        try {
            $stmt = $db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "✓ Database tables found: " . count($tables) . "<br>";
            echo "Tables: " . implode(', ', $tables) . "<br>";
        } catch (Exception $e) {
            echo "✗ Could not list tables: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "✗ Database connection failed - returned null<br>";
    }
} catch (Exception $e) {
    echo "✗ Database connection error: " . $e->getMessage() . "<br>";
}

echo "<h3>Testing Config File</h3>";

// Test config.php
try {
    require_once 'config/config.php';
    
    if (isset($db)) {
        echo "✓ config.php created \$db variable successfully<br>";
        echo "DB variable type: " . gettype($db) . "<br>";
        if (is_object($db)) {
            echo "DB object class: " . get_class($db) . "<br>";
        }
    } else {
        echo "✗ config.php did not create \$db variable<br>";
    }
    
} catch (Exception $e) {
    echo "✗ Config file error: " . $e->getMessage() . "<br>";
}
?>