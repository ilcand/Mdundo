<?php
// example.php
require_once 'HolidayRepository.php';

// configure PDO (MySQL example)
$dsn = "mysql:host=127.0.0.1;dbname=mdundo;charset=utf8mb4";
$user = "root";
$pass = "";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = new PDO($dsn, $user, $pass, $options);
$repo = new HolidayRepository($pdo);

// 1) Add a holiday
try {
    $success = $repo->addEmployeeHoliday(123, '2025-08-14', 'Summer break');
    echo $success ? "Holiday added\n" : "Failed to add\n";
} catch (RepositoryException $e) {
    echo "Error adding holiday: " . $e->getMessage() . PHP_EOL;
}

// 2) Compute remaining holidays
try {
    $summary = $repo->computeRemainingHolidays(123, '2025-08-15');
} catch (RepositoryException $e) {
    $summary = null;
    echo "Error computing remaining holidays: " . $e->getMessage() . PHP_EOL;
}

// 3) List all employees holiday stats
try {
    $list = $repo->listEmployeesHolidays('2025-01-01', '2025-12-31');
} catch (RepositoryException $e) {
    $list = [];
    echo "Error listing: " . $e->getMessage() . PHP_EOL;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mdundo Holiday Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h1 class="mb-4 text-center">🌍 Mdundo Holiday Dashboard</h1>

    <?php if ($summary): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Employee #123 (John Doe)</h5>
                <p class="card-text mb-1"><strong>Year:</strong> <?= htmlspecialchars($summary['year']) ?></p>
                <p class="card-text mb-1"><strong>Entitlement:</strong> <?= htmlspecialchars($summary['entitlement']) ?> days</p>
                <p class="card-text mb-1"><strong>Taken:</strong> <?= htmlspecialchars($summary['taken']) ?> days</p>
                <p class="card-text"><strong>Remaining:</strong> <?= htmlspecialchars($summary['remaining']) ?> days</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">All Employees (2025)</h5>
            <div class="table-responsive">
                <table class="table table-striped align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Personal Holidays</th>
                            <th>Public Holidays</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($list)): ?>
                        <?php foreach ($list as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['employee_id']) ?></td>
                                <td><?= htmlspecialchars($row['first_name']) ?></td>
                                <td><?= htmlspecialchars($row['last_name']) ?></td>
                                <td><?= htmlspecialchars($row['personal_holidays']) ?></td>
                                <td><?= htmlspecialchars($row['public_holidays']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-muted">No data available</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
