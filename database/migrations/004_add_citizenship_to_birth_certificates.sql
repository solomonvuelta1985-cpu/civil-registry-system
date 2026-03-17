-- Migration: Add citizenship columns to certificate_of_live_birth table
-- Date: 2026-02-17
-- Description: Adds mother_citizenship and father_citizenship columns

ALTER TABLE certificate_of_live_birth
  ADD COLUMN mother_citizenship VARCHAR(100) NULL AFTER mother_last_name,
  ADD COLUMN father_citizenship VARCHAR(100) NULL AFTER father_last_name;
