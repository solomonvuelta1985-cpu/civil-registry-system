<?php
/**
 * Shared helpers for petition_save.php and petition_update.php.
 *
 * Keeps field extraction, validation, and child-row persistence in one place
 * so save/update never drift apart.
 */

if (!function_exists('ra9048_extract_petition_payload')) {

    /**
     * Extract and sanitize the petition payload from $_POST.
     * Returns an associative array with normalized values (nullable fields are null when empty).
     */
    function ra9048_extract_petition_payload(): array
    {
        $subtype = sanitize_input($_POST['petition_subtype'] ?? '');
        $type    = sanitize_input($_POST['petition_type'] ?? '');

        // Mirror legacy petition_type from subtype if not explicitly provided
        if (!in_array($type, ['CCE', 'CFN'], true)) {
            $type = ($subtype === 'CFN') ? 'CFN' : 'CCE';
        }

        $payload = [
            'petition_type'             => $type,
            'petition_subtype'          => $subtype,
            'petition_number'           => sanitize_input($_POST['petition_number'] ?? '') ?: null,
            'date_of_filing'            => sanitize_input($_POST['date_of_filing'] ?? ''),
            'special_law'               => sanitize_input($_POST['special_law'] ?? '') ?: null,
            'fee_amount'                => floatval($_POST['fee_amount'] ?? 0),
            'document_type'             => sanitize_input($_POST['document_type'] ?? ''),
            'petition_of'               => sanitize_input($_POST['petition_of'] ?? '') ?: null,
            'remarks'                   => sanitize_input($_POST['remarks'] ?? '') ?: null,

            // Petitioner
            'petitioner_names'          => sanitize_input($_POST['petitioner_names'] ?? ''),
            'petitioner_nationality'    => sanitize_input($_POST['petitioner_nationality'] ?? '') ?: null,
            'petitioner_address'        => sanitize_input($_POST['petitioner_address'] ?? '') ?: null,
            'petitioner_id_type'        => sanitize_input($_POST['petitioner_id_type'] ?? '') ?: null,
            'petitioner_id_number'      => sanitize_input($_POST['petitioner_id_number'] ?? '') ?: null,
            'is_self_petition'          => !empty($_POST['is_self_petition']) ? 1 : 0,
            'relation_to_owner'         => sanitize_input($_POST['relation_to_owner'] ?? '') ?: null,

            // Document owner
            'document_owner_names'      => sanitize_input($_POST['document_owner_names'] ?? ''),
            'owner_dob'                 => sanitize_input($_POST['owner_dob'] ?? '') ?: null,
            'owner_birthplace_city'     => sanitize_input($_POST['owner_birthplace_city'] ?? '') ?: null,
            'owner_birthplace_province' => sanitize_input($_POST['owner_birthplace_province'] ?? '') ?: null,
            'owner_birthplace_country'  => sanitize_input($_POST['owner_birthplace_country'] ?? '') ?: null,
            'registry_number'           => sanitize_input($_POST['registry_number'] ?? '') ?: null,
            'father_full_name'          => sanitize_input($_POST['father_full_name'] ?? '') ?: null,
            'mother_full_name'          => sanitize_input($_POST['mother_full_name'] ?? '') ?: null,

            // CFN
            'cfn_ground'                => sanitize_input($_POST['cfn_ground'] ?? '') ?: null,
            'cfn_ground_detail'         => sanitize_input($_POST['cfn_ground_detail'] ?? '') ?: null,

            // Notarization & payment
            'notarized_at'              => sanitize_input($_POST['notarized_at'] ?? '') ?: null,
            'receipt_number'            => sanitize_input($_POST['receipt_number'] ?? '') ?: null,
            'payment_date'              => sanitize_input($_POST['payment_date'] ?? '') ?: null,

            // Posting
            'posting_start_date'        => sanitize_input($_POST['posting_start_date'] ?? '') ?: null,
            'posting_end_date'          => sanitize_input($_POST['posting_end_date'] ?? '') ?: null,
            'posting_location'          => sanitize_input($_POST['posting_location'] ?? '') ?: null,

            // Publication
            'order_date'                => sanitize_input($_POST['order_date'] ?? '') ?: null,
            'publication_date_1'        => sanitize_input($_POST['publication_date_1'] ?? '') ?: null,
            'publication_date_2'        => sanitize_input($_POST['publication_date_2'] ?? '') ?: null,
            'publication_newspaper'     => sanitize_input($_POST['publication_newspaper'] ?? '') ?: null,
            'publication_place'         => sanitize_input($_POST['publication_place'] ?? '') ?: null,
            'opposition_deadline'       => sanitize_input($_POST['opposition_deadline'] ?? '') ?: null,
        ];

        // Auto-fill opposition_deadline from publication_date_2 if missing
        if (empty($payload['opposition_deadline']) && !empty($payload['publication_date_2'])) {
            $ts = strtotime($payload['publication_date_2'] . ' +1 day');
            if ($ts !== false) $payload['opposition_deadline'] = date('Y-m-d', $ts);
        }

        return $payload;
    }

    /**
     * Validate the payload. Returns an array of error strings (empty = OK).
     */
    function ra9048_validate_petition_payload(array $p): array
    {
        $errors = [];

        if (!in_array($p['petition_subtype'], ['CCE_minor', 'CCE_10172', 'CFN'], true)) {
            $errors[] = 'Petition subtype is required.';
        }
        if (empty($p['petition_number'])) {
            $errors[] = 'Petition number is required.';
        } elseif (!preg_match('/^(CCE|CFN)-\d{1,6}-\d{4}$/i', $p['petition_number'])) {
            $errors[] = 'Petition number must be in the form CCE-0001-2026 or CFN-0001-2026.';
        }
        if (empty($p['date_of_filing'])) {
            $errors[] = 'Date of filing is required.';
        }
        if (empty($p['document_owner_names'])) {
            $errors[] = 'Document owner is required.';
        }
        if (empty($p['petitioner_names'])) {
            $errors[] = 'Petitioner name is required.';
        }
        if (!in_array($p['document_type'], ['COLB', 'COM', 'COD'], true)) {
            $errors[] = 'Type of document is required.';
        }

        // CFN-specific
        if ($p['petition_subtype'] === 'CFN' && empty($p['cfn_ground'])) {
            $errors[] = 'Ground for CFN is required.';
        }

        // Petition number prefix must match subtype
        if (!empty($p['petition_number'])) {
            $expectedPrefix = ($p['petition_subtype'] === 'CFN') ? 'CFN-' : 'CCE-';
            if (stripos($p['petition_number'], $expectedPrefix) !== 0) {
                $errors[] = "Petition number prefix must be {$expectedPrefix} for the selected subtype.";
            }
        }

        return $errors;
    }

    /**
     * Replace child rows (corrections + supporting docs) for a petition.
     * Caller controls the transaction.
     */
    function ra9048_replace_child_rows(PDO $pdo_ra, int $petition_id, array $corrections, array $supporting_docs): void
    {
        $pdo_ra->prepare("DELETE FROM petition_corrections WHERE petition_id = :id")
               ->execute([':id' => $petition_id]);

        $pdo_ra->prepare("DELETE FROM petition_supporting_docs WHERE petition_id = :id")
               ->execute([':id' => $petition_id]);

        if (!empty($corrections)) {
            $stmt = $pdo_ra->prepare(
                "INSERT INTO petition_corrections (petition_id, item_no, nature, description, value_from, value_to)
                 VALUES (:pid, :item_no, :nature, :description, :value_from, :value_to)"
            );
            $itemNo = 1;
            foreach ($corrections as $c) {
                $description = sanitize_input($c['description'] ?? '');
                $valueFrom   = sanitize_input($c['value_from'] ?? '');
                $valueTo     = sanitize_input($c['value_to'] ?? '');
                $nature      = sanitize_input($c['nature'] ?? 'CCE');
                if (!in_array($nature, ['CCE', 'CFN'], true)) $nature = 'CCE';

                // Skip blank rows
                if ($description === '' && $valueFrom === '' && $valueTo === '') continue;

                $stmt->execute([
                    ':pid'         => $petition_id,
                    ':item_no'     => $itemNo++,
                    ':nature'      => $nature,
                    ':description' => $description,
                    ':value_from'  => $valueFrom,
                    ':value_to'    => $valueTo,
                ]);
            }
        }

        if (!empty($supporting_docs)) {
            $stmt = $pdo_ra->prepare(
                "INSERT INTO petition_supporting_docs (petition_id, item_no, doc_label)
                 VALUES (:pid, :item_no, :doc_label)"
            );
            $itemNo = 1;
            foreach ($supporting_docs as $d) {
                $label = sanitize_input($d['doc_label'] ?? '');
                if ($label === '') continue;
                $stmt->execute([
                    ':pid'       => $petition_id,
                    ':item_no'   => $itemNo++,
                    ':doc_label' => $label,
                ]);
            }
        }
    }
}
