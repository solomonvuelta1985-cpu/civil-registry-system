-- Migration 016: Backfill created_by for certificates where it is NULL
-- Matches each certificate to the activity_log entry that recorded its creation,
-- using the registry_no embedded in the log details text.

-- ─── Certificate of Live Birth ───────────────────────────────────────────────
UPDATE certificate_of_live_birth c
JOIN (
    SELECT
        SUBSTRING_INDEX(details, 'Registry No. ', -1) AS registry_no,
        user_id
    FROM activity_logs
    WHERE action = 'CREATE_CERTIFICATE'
      AND details LIKE 'Created Certificate of Live Birth:%'
      AND user_id IS NOT NULL
) al ON al.registry_no = c.registry_no
SET c.created_by = al.user_id
WHERE c.created_by IS NULL;

-- ─── Certificate of Marriage ──────────────────────────────────────────────────
UPDATE certificate_of_marriage c
JOIN (
    SELECT
        SUBSTRING_INDEX(details, 'Registry No. ', -1) AS registry_no,
        user_id
    FROM activity_logs
    WHERE action = 'CREATE_CERTIFICATE'
      AND details LIKE 'Created Certificate of Marriage:%'
      AND user_id IS NOT NULL
) al ON al.registry_no = c.registry_no
SET c.created_by = al.user_id
WHERE c.created_by IS NULL;

-- ─── Certificate of Death ─────────────────────────────────────────────────────
UPDATE certificate_of_death c
JOIN (
    SELECT
        SUBSTRING_INDEX(details, 'Registry No. ', -1) AS registry_no,
        user_id
    FROM activity_logs
    WHERE action = 'CREATE_CERTIFICATE'
      AND details LIKE 'Created Certificate of Death:%'
      AND user_id IS NOT NULL
) al ON al.registry_no = c.registry_no
SET c.created_by = al.user_id
WHERE c.created_by IS NULL;
