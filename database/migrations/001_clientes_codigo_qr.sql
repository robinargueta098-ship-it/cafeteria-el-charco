-- 001 — Código personal (QR) del cliente para sumar cafés escaneando.
-- Aplicar una sola vez sobre una base creada con un schema.sql anterior.
-- Los códigos se asignan solos (Cliente::obtenerCodigoQr) la primera vez
-- que cada cliente abre su cuenta; no hay que rellenar nada a mano.
ALTER TABLE clientes
  ADD COLUMN codigo_qr VARCHAR(16) NULL UNIQUE AFTER notas;
