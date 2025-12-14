<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}

$err = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $base_price = floatval($_POST['base_price']);
  $price_per_kg_excess = floatval($_POST['price_per_kg_excess']);
  $price_dry = floatval($_POST['price_dry']);
  $price_iron = floatval($_POST['price_iron']);

  if ($base_price <= 0) {
    $err = "Base price must be greater than 0.";
  } elseif ($price_per_kg_excess < 0) {
    $err = "Excess price per kg cannot be negative.";
  } else {
    $stmt = $conn->prepare("
      INSERT INTO price_list (base_price, price_per_kg_excess, price_dry, price_iron, updated_at)
      VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('dddd', $base_price, $price_per_kg_excess, $price_dry, $price_iron);

    if ($stmt->execute()) {
      $success = "✅ Price list updated successfully!";
    } else {
      $err = "Failed to update price list: " . $stmt->error;
    }
  }
}

// Fetch latest prices
$current = $conn->query("SELECT * FROM price_list ORDER BY id DESC LIMIT 1")->fetch_assoc();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Price List - Admin</title>
<link rel="stylesheet" href="../css/admin-theme.css">
<style>
body {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color: #1f2937;
  min-height: 100vh;
  padding: 20px;
}
.card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.15);
  max-width: 700px;
  margin: 50px auto;
  padding: 35px 40px;
  animation: fadeIn 0.5s ease;
}
.card h2 {
  color: #1e40af;
  text-align: center;
  margin-bottom: 20px;
  font-size: 28px;
}
label {
  display: block;
  margin-top: 15px;
  font-weight: 600;
  color: #374151;
}
input[type="number"] {
  width: 100%;
  padding: 12px;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  margin-top: 6px;
  background: #f9fafb;
  transition: all 0.3s ease;
  font-size: 15px;
}
input:focus {
  border-color: #2563eb;
  outline: none;
  background: white;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}
button {
  background: #2563eb;
  color: white;
  font-weight: 600;
  padding: 12px 20px;
  border: none;
  border-radius: 8px;
  margin-top: 25px;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 10px rgba(37,99,235,0.3);
  width: 100%;
}
button:hover {
  background: #1e40af;
  transform: translateY(-2px);
}
.success, .error {
  padding: 14px 18px;
  border-radius: 8px;
  margin-bottom: 20px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
}
.success {
  background: #d1fae5;
  color: #065f46;
  border-left: 4px solid #10b981;
}
.error {
  background: #fee2e2;
  color: #991b1b;
  border-left: 4px solid #ef4444;
}
.current-prices {
  background: #f9fafb;
  padding: 15px;
  border-radius: 8px;
  margin-top: 25px;
  border: 2px solid #e5e7eb;
}
.current-prices p {
  margin: 8px 0;
  font-weight: 600;
  color: #374151;
}
.info-box {
  background: #fef3c7;
  border-left: 4px solid #f59e0b;
  padding: 15px;
  border-radius: 8px;
  margin: 15px 0;
  font-size: 14px;
  color: #92400e;
}
.pricing-example {
  background: #f0f9ff;
  border-left: 4px solid #2563eb;
  padding: 15px;
  border-radius: 8px;
  margin: 15px 0;
  font-size: 13px;
}
.pricing-example h4 {
  margin: 0 0 10px 0;
  color: #1e40af;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
  <h2>🧾 Manage Price List</h2>

  <?php if($err): ?><div class="error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="info-box">
    <strong>💡 Pricing Model:</strong> Base price covers up to 6kg. Excess weight is charged per kg.
  </div>

  <form method="post">
    <label>Base Price (₱) - Covers up to 6kg</label>
    <input type="number" name="base_price" step="0.01" min="0" value="<?= htmlspecialchars($current['base_price'] ?? 180) ?>" required>
    <small style="color: #6b7280; display: block; margin-top: 5px;">This is the flat rate for laundry up to 6kg</small>

    <label>Excess Price Per Kg (₱) - Over 6kg</label>
    <input type="number" name="price_per_kg_excess" step="0.01" min="0" value="<?= htmlspecialchars($current['price_per_kg_excess'] ?? 30) ?>" required>
    <small style="color: #6b7280; display: block; margin-top: 5px;">Additional charge for each kg over the 6kg threshold</small>

    <label>Dry Service Price (₱)</label>
    <input type="number" name="price_dry" step="0.01" min="0" value="<?= htmlspecialchars($current['price_dry'] ?? 50) ?>" required>
    <small style="color: #6b7280; display: block; margin-top: 5px;">Additional charge for drying service</small>

    <label>Iron Service Price (₱)</label>
    <input type="number" name="price_iron" step="0.01" min="0" value="<?= htmlspecialchars($current['price_iron'] ?? 30) ?>" required>
    <small style="color: #6b7280; display: block; margin-top: 5px;">Additional charge for ironing service</small>

    <button type="submit">💾 Update Prices</button>
  </form>

  <?php if ($current): ?>
  <div class="current-prices">
    <h3 style="color:#1e40af;">📊 Current Prices</h3>
    <p><strong>Base Price (up to 6kg):</strong> ₱<?= number_format($current['base_price'], 2) ?></p>
    <p><strong>Excess (per kg over 6kg):</strong> ₱<?= number_format($current['price_per_kg_excess'] ?? 30, 2) ?>/kg</p>
    <p><strong>Dry Service:</strong> ₱<?= number_format($current['price_dry'], 2) ?></p>
    <p><strong>Iron Service:</strong> ₱<?= number_format($current['price_iron'], 2) ?></p>
    <p><em>Last Updated: <?= htmlspecialchars($current['updated_at']) ?></em></p>
  </div>

  <div class="pricing-example">
    <h4>📐 Pricing Examples:</h4>
    <?php
    $base = $current['base_price'];
    $excess = $current['price_per_kg_excess'] ?? 30;
    $dry = $current['price_dry'];
    $iron = $current['price_iron'];
    ?>
    <p><strong>5kg (Wash only):</strong> ₱<?= number_format($base, 2) ?></p>
    <p><strong>6kg (Wash only):</strong> ₱<?= number_format($base, 2) ?></p>
    <p><strong>8kg (Wash only):</strong> ₱<?= number_format($base + (2 * $excess), 2) ?> (Base ₱<?= number_format($base, 2) ?> + 2kg excess @ ₱<?= number_format($excess, 2) ?>/kg)</p>
    <p><strong>10kg (Wash only):</strong> ₱<?= number_format($base + (4 * $excess), 2) ?> (Base ₱<?= number_format($base, 2) ?> + 4kg excess @ ₱<?= number_format($excess, 2) ?>/kg)</p>
    <p><strong>8kg (Wash + Dry + Iron):</strong> ₱<?= number_format($base + (2 * $excess) + $dry + $iron, 2) ?></p>
  </div>
  <?php endif; ?>
</div>

</body>
</html>