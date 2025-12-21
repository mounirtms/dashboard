<?php
/**
 * Script to reset admin passwords for Magento 2
 * Usage: php reset_admin_passwords.php
 */

// Database configuration
$host = '127.0.0.1';
$port = 3307;
$username = 'root';
$password = 'YourNewStrongPassword'; // Replace with actual password
$database = 'technadminy7_dBT8x12y22';

// Admin credentials from the image
$adminCredentials = [
    'PimMaritime' => '97UrJq7',
    'Cheraga' => '7pCSYrTH',
    'Hydra' => 'YPTb2dC',
    'Oran' => 'aZMy343U',
    'Ghardaia' => 'vr7suqC3',
    'Setif' => '56MVfzAJ',
    'Blida' => 'HNvJW6U7',
    'Mostaganem' => '8Pt66Cm4',
    'Rouiba' => 'jAH4DdeH',
    'OuledFayet' => 'ATWF2Mox',
    'DelyBrahim' => 'LKSTbg2G',
    'Draria' => 'dfT9d2VB',
    'Boumerdes' => 'Gd09d6v7',
    'BirEldjir' => 'fpJN5eVc',
    'Constantine' => 'GT5mv2Lg',
    'Djelfa' => 'NSCW8PTu',
    'Batna' => '7AxaJmvX',
    'Belabes' => '83LC25fQ',
    'AinBeniane' => '5CA7Tjbu',
    'Annaba' => 'w254vQXA'
];

// Connect to database
try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Reset each admin password
    foreach ($adminCredentials as $adminUsername => $adminPassword) {
        // Hash the password using Magento's method (using bcrypt)
        // Format: password_hash:salt:version
        $salt = substr(hash('sha256', uniqid()), 0, 32);
        $hashedPassword = crypt($adminPassword, '$2y$10$' . $salt . '$');
        $finalHash = $hashedPassword . ':' . $salt . ':1';
        
        // Update the admin user record
        $sql = "UPDATE admin_user 
                SET password = :password 
                WHERE username = :username";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':password' => $finalHash,
            ':username' => $adminUsername
        ]);
        
        echo "Password reset for: {$adminUsername}\n";
    }
    
    echo "All admin passwords have been reset successfully.\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}