-- Migration a executer une fois sur une base existante creee avec l'ancien schema.
-- Aligne volume_bloc sur ce que l'application ecrit reellement : des volumes
-- a decimales (ex : 5.6 L), la ou l'ancien INT arrondissait (5.6 -> 6).
-- Les valeurs INT deja en base sont conservees telles quelles (12 -> 12.00).
-- Idempotent : relancer ne change rien.

USE gonflages_db;

ALTER TABLE gonflages
    MODIFY volume_bloc DECIMAL(6,2);
