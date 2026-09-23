<?php
/**
 * Saving your own company details (1.1.0) and editing verified companies
 * (added in 0.18.0).
 *
 * Companies can be edited but never deleted: the ledger, manifests, and
 * Chain of Custody history reference them by id. There is intentionally no
 * delete function in this file and none should ever be added.
 */

/**
 * Save your own company details (process_own_company.php). There is only
 * ever one row: it's created the first time and updated after that.
 *
 * Values are stored exactly as typed (trimmed). Pages escape them when they
 * show them. Never "sanitise" on the way in: 1.0.x ran FILTER_SANITIZE_STRING
 * here, which stored "Joe's Farm" as "Joe&#39;s Farm" and put that in
 * Agency emails.
 *
 * @param PDO $pdo
 * @param array $input companyName, companyLicense, companyAddress, primaryContactEmail
 * @return array{success: bool, message: string}
 */
function saveOwnCompany(PDO $pdo, array $input)
{
    $fields = [
        'companyName' => 'Please enter your company name.',
        'companyLicense' => 'Please enter your license number.',
        'companyAddress' => 'Please enter your address.',
        'primaryContactEmail' => 'Please enter a valid contact email.',
    ];
    $values = [];
    foreach ($fields as $field => $message) {
        $values[$field] = trim((string) ($input[$field] ?? ''));
        if ($values[$field] === '') {
            return ['success' => false, 'message' => $message];
        }
    }
    if (!filter_var($values['primaryContactEmail'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => $fields['primaryContactEmail']];
    }

    $params = [
        ':companyName' => $values['companyName'],
        ':companyLicense' => $values['companyLicense'],
        ':companyAddress' => $values['companyAddress'],
        ':primaryContactEmail' => $values['primaryContactEmail'],
    ];
    $existingId = $pdo->query("SELECT id FROM OwnCompany ORDER BY id LIMIT 1")->fetchColumn();
    if ($existingId !== false) {
        $stmt = $pdo->prepare("UPDATE OwnCompany SET
                                  company_name = :companyName,
                                  company_license_number = :companyLicense,
                                  company_address = :companyAddress,
                                  primary_contact_email = :primaryContactEmail
                               WHERE id = :id");
        $stmt->execute($params + [':id' => $existingId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO OwnCompany (company_name, company_license_number, company_address, primary_contact_email)
                               VALUES (:companyName, :companyLicense, :companyAddress, :primaryContactEmail)");
        $stmt->execute($params);
    }

    return ['success' => true, 'message' => 'Company details saved.'];
}

/**
 * Add a verified company (Administration → Add Verified Company, and the
 * "+ Add new company…" pop-up in company lists).
 *
 * A license number that's already on file means it's the same company: the
 * result is flagged as a duplicate and carries that company, so a form can
 * simply select it. A contact email that belongs to another company is
 * refused, like when editing.
 *
 * @param PDO $pdo
 * @param array $input companyName, licenseNumber, address, contactName, contactEmail, contactPhone
 * @return array{success: bool, message: string, id: int|null, name: string|null, duplicate: bool, company: array|null}
 */
function addVerifiedCompany(PDO $pdo, array $input)
{
    $fields = [
        'companyName' => 'company name',
        'licenseNumber' => 'license number',
        'address' => 'address',
        'contactName' => 'contact name',
        'contactEmail' => 'contact email',
        'contactPhone' => 'contact phone',
    ];
    $refuse = function ($message) {
        return ['success' => false, 'message' => $message, 'id' => null, 'name' => null, 'duplicate' => false, 'company' => null];
    };

    $values = [];
    foreach ($fields as $field => $label) {
        $values[$field] = trim((string) ($input[$field] ?? ''));
        if ($values[$field] === '') {
            return $refuse("Please enter the $label.");
        }
    }
    if (!filter_var($values['contactEmail'], FILTER_VALIDATE_EMAIL)) {
        return $refuse('Please enter a valid contact email.');
    }

    $details = function (PDO $pdo, $id) {
        $stmt = $pdo->prepare("SELECT id, name, license_number, address, primary_contact_email FROM Companies WHERE id = ?");
        $stmt->execute([$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        $company['id'] = (int) $company['id'];
        return $company;
    };

    // Under the write lock, so the same company can't be added twice at once
    return withWriteLock($pdo, function (PDO $pdo) use ($values, $refuse, $details) {
        $stmt = $pdo->prepare("SELECT id FROM Companies WHERE license_number = ?");
        $stmt->execute([$values['licenseNumber']]);
        $existingId = $stmt->fetchColumn();
        if ($existingId !== false) {
            $company = $details($pdo, $existingId);
            return ['success' => false, 'duplicate' => true, 'id' => $company['id'], 'name' => $company['name'], 'company' => $company,
                    'message' => "{$company['name']} is already a verified company (license {$company['license_number']})."];
        }

        $stmt = $pdo->prepare("SELECT id FROM Companies WHERE primary_contact_email = ?");
        $stmt->execute([$values['contactEmail']]);
        if ($stmt->fetchColumn() !== false) {
            return $refuse('Another company already has that contact email.');
        }

        $pdo->prepare("INSERT INTO Companies (name, license_number, address, primary_contact_name, primary_contact_email, primary_contact_phone)
                       VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$values['companyName'], $values['licenseNumber'], $values['address'],
                       $values['contactName'], $values['contactEmail'], $values['contactPhone']]);
        $company = $details($pdo, $pdo->lastInsertId());
        return ['success' => true, 'duplicate' => false, 'id' => $company['id'], 'name' => $company['name'], 'company' => $company,
                'message' => "Added {$company['name']}."];
    });
}

/**
 * Validate and apply an update to a verified company.
 *
 * @param PDO $pdo
 * @param array $input expects companyId, companyName, licenseNumber,
 *                     address, contactName, contactEmail, contactPhone
 * @return string "Success: ..." or "Error: ..." message for the UI
 */
function updateVerifiedCompany(PDO $pdo, array $input)
{
    $companyId = isset($input['companyId']) ? (int) $input['companyId'] : 0;
    $companyName = trim($input['companyName'] ?? '');
    $licenseNumber = trim($input['licenseNumber'] ?? '');
    $address = trim($input['address'] ?? '');
    $contactName = trim($input['contactName'] ?? '');
    $contactEmail = trim($input['contactEmail'] ?? '');
    $contactPhone = trim($input['contactPhone'] ?? '');

    // Basic input validation, mirrors handle_add_verified_company.php
    if ($companyId <= 0) {
        return "Error: Unknown company.";
    }
    if (empty($companyName) || empty($licenseNumber) || empty($address) || empty($contactName) || empty($contactEmail) || empty($contactPhone)) {
        return "Error: All fields are required.";
    }

    try {
        // The company must exist
        $stmt = $pdo->prepare("SELECT id FROM Companies WHERE id = ?");
        $stmt->execute([$companyId]);
        if (!$stmt->fetch()) {
            return "Error: Unknown company.";
        }

        // The new license number / contact email must not belong to a DIFFERENT
        // company (re-saving this company's own values is fine)
        $stmt = $pdo->prepare("SELECT id FROM Companies WHERE license_number = ? AND id != ?");
        $stmt->execute([$licenseNumber, $companyId]);
        if ($stmt->fetch()) {
            return "Error: Another company already has that license number.";
        }

        $stmt = $pdo->prepare("SELECT id FROM Companies WHERE primary_contact_email = ? AND id != ?");
        $stmt->execute([$contactEmail, $companyId]);
        if ($stmt->fetch()) {
            return "Error: Another company already has that contact email.";
        }

        $stmt = $pdo->prepare("UPDATE Companies
                               SET name = :companyName,
                                   license_number = :licenseNumber,
                                   address = :address,
                                   primary_contact_name = :contactName,
                                   primary_contact_email = :contactEmail,
                                   primary_contact_phone = :contactPhone
                               WHERE id = :companyId");
        $stmt->execute([
            ':companyName' => $companyName,
            ':licenseNumber' => $licenseNumber,
            ':address' => $address,
            ':contactName' => $contactName,
            ':contactEmail' => $contactEmail,
            ':contactPhone' => $contactPhone,
            ':companyId' => $companyId,
        ]);

        return "Success: Company details updated";
    } catch (PDOException $e) {
        return "Error: " . htmlspecialchars($e->getMessage());
    }
}
