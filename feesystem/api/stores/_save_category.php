<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);

$school_id = $data['school_id'] ?? 0;
$category_id = $data['category_id'] ?? 0;
$name = $data['name'] ?? '';
$description = $data['description'] ?? '';

if (!$school_id || !$name) {
    echo json_encode(['success' => false, 'message' => 'Category name is required']);
    exit;
}

try {
    if ($category_id) {
        $stmt = $db->prepare("UPDATE store_categories SET name = ?, description = ? WHERE id = ? AND school_id = ?");
        $stmt->execute([$name, $description, $category_id, $school_id]);
        echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
    } else {
        $stmt = $db->prepare("INSERT INTO store_categories (school_id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$school_id, $name, $description]);
        echo json_encode(['success' => true, 'message' => 'Category added successfully']);
    }
} catch (PDOException $e) {
    error_log("Error in save_category: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>