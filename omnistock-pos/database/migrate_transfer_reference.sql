ALTER TABLE ventas
    ADD COLUMN IF NOT EXISTS referencia_transferencia VARCHAR(50) NULL AFTER cambio;
