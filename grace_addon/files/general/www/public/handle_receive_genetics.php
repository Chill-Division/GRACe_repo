<?php
require_once 'init_db.php';
require_once 'receive_lib.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: receive_genetics.php");
    exit();
}

try {
    $pdo = initializeDatabase();
    $result = receivePlants($pdo, $_POST);
} catch (PDOException $e) {
    $result = ['success' => false, 'message' => 'Error inserting plants: ' . $e->getMessage()];
}

if ($result['success']) {
    header("Location: receive_genetics.php?success=" . urlencode($result['message']));
} else {
    // Back to the form with the message and what was typed
    $typed = array_intersect_key($_POST, array_flip(['plantCount', 'geneticsName']));
    header("Location: receive_genetics.php?error=" . urlencode($result['message']) . "&data=" . urlencode(json_encode($typed)));
}
exit();
