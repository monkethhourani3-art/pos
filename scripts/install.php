<?php
/**
 * Restaurant POS System Installation Script
 * Automated database setup and environment configuration
 */

echo "\n==================================================\n";
echo "🍽️  Restaurant POS System Installation\n";
echo "==================================================\n\n";

// Check PHP version
echo "📋 Checking requirements...\n";
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    die("❌ Error: PHP 8.2 or higher is required. Current version: " . PHP_VERSION . "\n");
}
echo "✅ PHP version: " . PHP_VERSION . "\n";

// Check required extensions
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'gd', 'curl'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        die("❌ Error: Required extension '{$ext}' is not loaded\n");
    }
}
echo "✅ All required PHP extensions are loaded\n";

// Check if .env file exists
if (!file_exists(__DIR__ . '/../.env')) {
    echo "⚠️  Warning: .env file not found. Creating from .env.example...\n";
    if (file_exists(__DIR__ . '/../.env.example')) {
        copy(__DIR__ . '/../.env.example', __DIR__ . '/../.env');
        echo "✅ Created .env file from template\n";
    } else {
        die("❌ Error: .env.example file not found\n");
    }
}

// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Check database configuration
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_port = $_ENV['DB_PORT'] ?? '3306';
$db_name = $_ENV['DB_DATABASE'] ?? 'restaurant_pos';
$db_user = $_ENV['DB_USERNAME'] ?? 'root';
$db_pass = $_ENV['DB_PASSWORD'] ?? '';

echo "\n📊 Database Configuration:\n";
echo "   Host: {$db_host}\n";
echo "   Port: {$db_port}\n";
echo "   Database: {$db_name}\n";
echo "   Username: {$db_user}\n";

// Test database connection
echo "\n🔗 Testing database connection...\n";
try {
    $dsn = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✅ Database connection successful\n";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Create database if it doesn't exist
echo "\n🗄️  Setting up database...\n";
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$db_name}`");
    echo "✅ Database '{$db_name}' ready\n";
} catch (PDOException $e) {
    die("❌ Failed to create database: " . $e->getMessage() . "\n");
}

// Load and execute schema
echo "\n📋 Importing database schema...\n";
$schema_file = __DIR__ . '/../database/schema.sql';
if (!file_exists($schema_file)) {
    die("❌ Schema file not found: {$schema_file}\n");
}

$schema = file_get_contents($schema_file);

// Remove comments and split into statements
$schema = preg_replace('/--.*$/m', '', $schema);
$statements = array_filter(array_map('trim', explode(';', $schema)));

$imported = 0;
foreach ($statements as $statement) {
    if (empty($statement) || $statement === 'COMMIT') {
        continue;
    }
    
    try {
        $pdo->exec($statement);
        $imported++;
    } catch (PDOException $e) {
        // Skip errors for duplicate entries or non-critical issues
        if (strpos($e->getMessage(), 'Duplicate entry') === false && 
            strpos($e->getMessage(), 'already exists') === false) {
            echo "⚠️  Warning: " . $e->getMessage() . "\n";
        }
    }
}

echo "✅ Database schema imported successfully ({$imported} statements)\n";

// Generate application key
echo "\n🔐 Generating application key...\n";
$key = 'base64:' . base64_encode(random_bytes(32));
$env_content = file_get_contents(__DIR__ . '/../.env');
$env_content = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $env_content);
file_put_contents(__DIR__ . '/../.env', $env_content);
echo "✅ Application key generated\n";

// Set proper permissions
echo "\n🔒 Setting permissions...\n";
$directories = [
    __DIR__ . '/../storage',
    __DIR__ . '/../storage/logs',
    __DIR__ . '/../storage/backups',
    __DIR__ . '/../public/uploads'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    chmod($dir, 0755);
}

echo "✅ Directory permissions set\n";

// Create sample data if requested
echo "\n📝 Do you want to create sample data? (y/N): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) === 'y' || trim($line) === 'Y') {
    echo "Creating sample data...\n";
    
    // Add some sample products, categories, etc.
    $sample_products = [
        [
            'branch_id' => 1,
            'category_id' => 1,
            'name_ar' => 'سلطة فتوش',
            'name_en' => 'Fattoush Salad',
            'description_ar' => 'سلطة لبنانية تقليدية بالخضار المشكلة',
            'description_en' => 'Traditional Lebanese mixed vegetable salad',
            'base_price' => 15000.00
        ],
        [
            'branch_id' => 1,
            'category_id' => 2,
            'name_ar' => 'مندي لحم',
            'name_en' => 'Lamb Mandi',
            'description_ar' => 'أرز بسمتي باللحم والبهارات العربية',
            'description_en' => 'Basmati rice with lamb and Arabic spices',
            'base_price' => 35000.00
        ],
        [
            'branch_id' => 1,
            'category_id' => 3,
            'name_ar' => 'عصير جزر طازج',
            'name_en' => 'Fresh Carrot Juice',
            'description_ar' => 'عصير جزر طبيعي 100%',
            'description_en' => '100% natural carrot juice',
            'base_price' => 8000.00
        ]
    ];
    
    foreach ($sample_products as $product) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (branch_id, category_id, name_ar, name_en, description_ar, description_en, base_price)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(array_values($product));
        } catch (PDOException $e) {
            // Skip if product already exists
        }
    }
    
    echo "✅ Sample data created\n";
}

// Create admin user
echo "\n👤 Creating admin user...\n";
$admin_password = password_hash('admin123', PASSWORD_ARGON2ID);
try {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET password_hash = ? 
        WHERE username = 'admin'
    ");
    $stmt->execute([$admin_password]);
    echo "✅ Admin user password updated\n";
} catch (PDOException $e) {
    echo "⚠️  Could not update admin password: " . $e->getMessage() . "\n";
}

// Final setup checks
echo "\n🔍 Final checks...\n";

// Check if writable directories exist
$writable_dirs = ['storage/logs', 'storage/backups', 'public/uploads'];
foreach ($writable_dirs as $dir) {
    if (!is_writable(__DIR__ . '/../' . $dir)) {
        echo "⚠️  Warning: Directory '{$dir}' is not writable\n";
    }
}

// Test file permissions
$test_file = __DIR__ . '/../storage/logs/install_test.log';
if (file_put_contents($test_file, 'test') !== false) {
    unlink($test_file);
    echo "✅ Log directory is writable\n";
} else {
    echo "⚠️  Warning: Cannot write to log directory\n";
}

echo "\n🎉 Installation completed successfully!\n\n";
echo "==================================================\n";
echo "✅ Restaurant POS System is ready to use!\n";
echo "==================================================\n\n";
echo "📋 Next Steps:\n";
echo "1. Start the development server:\n";
echo "   php -S localhost:8000 -t public\n\n";
echo "2. Or configure your web server (Apache/Nginx) to point to the 'public' directory\n\n";
echo "3. Access the system at: http://localhost:8000\n\n";
echo "4. Login with these credentials:\n";
echo "   Username: admin\n";
echo "   Password: admin123\n\n";
echo "🔗 Important URLs:\n";
echo "   • Login: http://localhost:8000/login\n";
echo "   • Dashboard: http://localhost:8000/dashboard\n";
echo "   • POS System: http://localhost:8000/pos\n\n";
echo "📁 Key Files:\n";
echo "   • Configuration: .env\n";
echo "   • Database: database/schema.sql\n";
echo "   • Logs: storage/logs/app.log\n\n";
echo "🆘 Need Help?\n";
echo "   • Check README.md for detailed documentation\n";
echo "   • View logs: tail -f storage/logs/app.log\n";
echo "   • Re-run installer: php scripts/install.php\n\n";
echo "==================================================\n";
echo "Thank you for choosing Restaurant POS System! 🍽️\n";
echo "==================================================\n\n";
?>