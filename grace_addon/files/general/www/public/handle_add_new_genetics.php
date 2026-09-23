<?php
require_once 'init_db.php';
require_once 'genetics_lib.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: add_new_genetics.php");
    exit();
}

try {
    $pdo = initializeDatabase();
    // Genetics are just a name (Breeder and Genetic Lineage were removed in
    // 1.1.0). Names that differ only by capitals or spaces count as the same.
    $result = addGenetics($pdo, $_POST['geneticsName'] ?? '');
} catch (PDOException $e) {
    $result = ['success' => false, 'message' => 'Error adding genetics: ' . $e->getMessage()];
}

if ($result['success']) {
    header("Location: add_new_genetics.php?success=" . urlencode($result['message']));
} else {
    // Back to the form with the message and what was typed
    $data = urlencode(json_encode(['geneticsName' => (string) ($_POST['geneticsName'] ?? '')]));
    header("Location: add_new_genetics.php?error=" . urlencode($result['message']) . "&data=$data");
}
exit();
