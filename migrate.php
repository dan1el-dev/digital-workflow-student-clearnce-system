<?php
/**
 * Database Migration and Seeding Script (SQLite)
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "Starting database migration...\n";

try {
    // 1. Read and execute schema.sql
    $schema_file = __DIR__ . '/schema.sql';
    if (!file_exists($schema_file)) {
        throw new Exception("schema.sql not found at $schema_file");
    }
    
    $sql = file_get_contents($schema_file);
    
    // SQLite can execute multiple statements in one call via exec()
    $pdo->exec($sql);
    echo "Schema created successfully!\n";
    
    // 2. Seed Default Admin
    $admin_id = 'a1111111-1111-1111-1111-111111111111';
    $admin_email = 'admin@oascs.edu.ng';
    $admin_pass_hash = password_hash('AdminPass123!', PASSWORD_BCRYPT, ['cost' => 12]);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (user_id, full_name, email, matric_no, password_hash, role, department_id)
        VALUES (?, 'System Administrator', ?, NULL, ?, 'admin', NULL)
    ");
    $stmt->execute([$admin_id, $admin_email, $admin_pass_hash]);
    echo "Admin user seeded: $admin_email\n";
    
    // 3. Seed Default Student
    $student_id = 's1111111-1111-1111-1111-111111111111';
    $student_matric = '19/10204';
    $student_pass_hash = password_hash('studentpass', PASSWORD_BCRYPT, ['cost' => 12]);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (user_id, full_name, email, matric_no, password_hash, role, department_id, academic_dept)
        VALUES (?, 'Adewale Babatunde', NULL, ?, ?, 'student', NULL, 'Computer Science')
    ");
    $stmt->execute([$student_id, $student_matric, $student_pass_hash]);
    echo "Student user seeded: $student_matric\n";
    
    // 4. Seed Departmental Officers
    $officers = [
        [
            'id' => 'o1111111-1111-1111-1111-111111111111',
            'email' => 'academic@oascs.edu.ng',
            'name' => 'Prof. James Audu',
            'dept_id' => 'd1111111-1111-1111-1111-111111111111'
        ],
        [
            'id' => 'o2222222-2222-2222-2222-222222222222',
            'email' => 'bursary@oascs.edu.ng',
            'name' => 'Alhaji Musa Bello',
            'dept_id' => 'd2222222-2222-2222-2222-222222222222'
        ],
        [
            'id' => 'o3333333-3333-3333-3333-333333333333',
            'email' => 'studentaffairs@oascs.edu.ng',
            'name' => 'Mrs. Grace Adebayo',
            'dept_id' => 'd3333333-3333-3333-3333-333333333333'
        ],
        [
            'id' => 'o4444444-4444-4444-4444-444444444444',
            'email' => 'college@oascs.edu.ng',
            'name' => 'Dr. Ngozi Okafor',
            'dept_id' => 'd4444444-4444-4444-4444-444444444444'
        ],
        [
            'id' => 'o5555555-5555-5555-5555-555555555555',
            'email' => 'ict@oascs.edu.ng',
            'name' => 'Mr. Victor Okafor',
            'dept_id' => 'd5555555-5555-5555-5555-555555555555'
        ],
        [
            'id' => 'o6666666-6666-6666-6666-666666666666',
            'email' => 'counselling@oascs.edu.ng',
            'name' => 'Dr. Toyin Phillips',
            'dept_id' => 'd6666666-6666-6666-6666-666666666666'
        ],
        [
            'id' => 'o7777777-7777-7777-7777-777777777777',
            'email' => 'library@oascs.edu.ng',
            'name' => 'Mrs. Angela Nduka',
            'dept_id' => 'd7777777-7777-7777-7777-777777777777'
        ],
        [
            'id' => 'o8888888-8888-8888-8888-888888888888',
            'email' => 'medical@oascs.edu.ng',
            'name' => 'Dr. Kunle Coker',
            'dept_id' => 'd8888888-8888-8888-8888-888888888888'
        ]
    ];
    
    $user_stmt = $pdo->prepare("
        INSERT INTO users (user_id, full_name, email, matric_no, password_hash, role, department_id)
        VALUES (?, ?, ?, NULL, ?, 'officer', ?)
    ");
    
    $dept_stmt = $pdo->prepare("
        UPDATE departments 
        SET officer_id = ? 
        WHERE department_id = ?
    ");
    
    $officer_pass_hash = password_hash('staffpass', PASSWORD_BCRYPT, ['cost' => 12]);
    
    foreach ($officers as $off) {
        $off_id = $off['id'];
        $user_stmt->execute([$off_id, $off['name'], $off['email'], $officer_pass_hash, $off['dept_id']]);
        $dept_stmt->execute([$off_id, $off['dept_id']]);
        echo "Officer user seeded: {$off['email']} for department ID: {$off['dept_id']}\n";
    }
    
    echo "\nDatabase migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
