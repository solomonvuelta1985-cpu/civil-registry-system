-- Migration 055: allow non-binary source transcription statuses for live-birth sex.

ALTER TABLE `certificate_of_live_birth`
    MODIFY COLUMN `child_sex` ENUM('Male', 'Female', 'No Entry', 'Not Readable') NULL DEFAULT NULL;
