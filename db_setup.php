<?php
/**
 * DATNIX SOLUTIONS - One-Click Database Setup & Verification Tool
 * Runs securely on InfinityFree server to initialize MySQL tables from database.sql
 */

header('Content-Type: text/html; charset=UTF-8');

$configFile = __DIR__ . '/config/config.php';
if (!file_exists($configFile)) {
    die('<h3 style="color:red;">Error: config/config.php not found!</h3>');
}

$config = require $configFile;
$db = $config['db'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>DATNIX SOLUTIONS - Database Initializer</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #F4F6FA; color: #0F172A; padding: 40px 20px; }
    .box { max-width: 650px; margin: 0 auto; background: white; border-radius: 10px; border: 1px solid #E2E8F0; padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    h2 { color: #092D78; margin-top: 0; display: flex; align-items: center; gap: 10px; }
    .badge { background: #FF7900; color: white; font-size: 11px; padding: 3px 8px; border-radius: 4px; }
    .step { padding: 12px 16px; margin: 10px 0; border-radius: 6px; font-size: 13px; }
    .step-ok { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
    .step-err { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    .step-info { background: #EEF3FD; color: #092D78; border: 1px solid #CBD5E1; }
    .btn { display: inline-block; background: #092D78; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; margin-top: 15px; }
    .btn:hover { background: #061C4C; }
    .btn-orange { background: #FF7900; }
    .btn-orange:hover { background: #E06800; }
  </style>
</head>
<body>
<div class="box">
  <h2>DATNIX SOLUTIONS <span class="badge">INSTALLER</span></h2>
  <p style="font-size: 13px; color: #64748B;">Target Host: <strong><?php echo htmlspecialchars($db['host']); ?></strong> | Database: <strong><?php echo htmlspecialchars($db['database']); ?></strong></p>

<?php
if (isset($_POST['run_install'])) {
    try {
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
        ]);

        echo '<div class="step step-ok"><strong>[PASS]</strong> Connected to MySQL database successfully!</div>';

        $sqlFile = __DIR__ . '/database.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("database.sql file not found in root directory.");
        }

        $sql = file_get_contents($sqlFile);
        
        // Execute queries
        $pdo->exec($sql);
        echo '<div class="step step-ok"><strong>[PASS]</strong> Executed database.sql schema successfully!</div>';

        // Check tables created
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo '<div class="step step-info"><strong>Active Tables in Database (' . count($tables) . '):</strong><br>' . implode(', ', $tables) . '</div>';

        echo '<div style="margin-top: 25px;">';
        echo '<p style="color: #059669; font-weight: 600;">Setup complete! Your website and admin panel are ready to use.</p>';
        echo '<a href="/" class="btn" style="margin-right: 10px;">Visit Public Website &rarr;</a>';
        echo '<a href="/admin/login.php" class="btn btn-orange">Open Admin Login &rarr;</a>';
        echo '</div>';

    } catch (Throwable $e) {
        echo '<div class="step step-err">';
        echo '<strong>[ERROR] Connection or SQL Failed:</strong><br>' . htmlspecialchars($e->getMessage());
        echo '<p style="margin-top: 8px; font-size: 12px;"><strong>Tip:</strong> Make sure you have created the database <code>' . htmlspecialchars($db['database']) . '</code> in your InfinityFree cPanel under <em>MySQL Databases</em> first.</p>';
        echo '</div>';
        echo '<form method="POST"><button type="submit" name="run_install" class="btn">Retry Installation</button></form>';
    }
} else {
?>
  <div class="step step-info">
    This installer will connect to <strong><?php echo htmlspecialchars($db['host']); ?></strong> and initialize all 9 tables from <code>database.sql</code>.
  </div>
  <form method="POST">
    <button type="submit" name="run_install" class="btn btn-orange" style="border:none; cursor:pointer;">
      Initialize Database Now &rarr;
    </button>
  </form>
<?php
}
?>
</div>
</body>
</html>
