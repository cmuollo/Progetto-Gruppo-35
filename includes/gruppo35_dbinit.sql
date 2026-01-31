-- gruppo35_dbinit.sql
-- Script per inizializzare un ambiente di test per l'app "La Bottega del Barbiere"
-- Uso:
-- 1) esegui questo file come superuser Postgres (es. psql -h localhost -U postgres -f gruppo35_dbinit.sql)
-- 2) oppure connettiti a psql e poi lancia \i gruppo35_dbinit.sql
-- Nota: questo script crea il database 'gruppo35' se non esiste, un ruolo di test 'www', le tabelle
-- utenti e appuntamenti, inserisce alcuni dati di esempio e concede permessi.

-- 1) crea il database gruppo35 se non esiste
DO $$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_database WHERE datname = 'gruppo35') THEN
      PERFORM dblink_exec('dbname=postgres', 'CREATE DATABASE gruppo35');
   END IF;
EXCEPTION WHEN undefined_function THEN
   -- se dblink non è disponibile, proviamo il CREATE DATABASE diretto e ignori l'errore se già esiste
   BEGIN
     EXECUTE 'CREATE DATABASE gruppo35';
   EXCEPTION WHEN others THEN
     -- ignore
   END;
END$$;

-- Creazione ruolo applicazione 'www' se non esiste
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'www') THEN
    CREATE ROLE www WITH LOGIN PASSWORD 'www';
  END IF;
END$$;


-- Abilita estensione per funzioni di hash (crypt)
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- 2) Tabelle
CREATE TABLE IF NOT EXISTS utenti (
  id SERIAL PRIMARY KEY,
  nome TEXT NOT NULL,
  cognome TEXT NOT NULL,
  telefono VARCHAR(20) NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  security_question VARCHAR(255) NOT NULL,
  security_answer_hash TEXT NOT NULL,
  ruolo VARCHAR(20) NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS appuntamenti (
  id SERIAL PRIMARY KEY,
  id_utente INT REFERENCES utenti(id) ON DELETE CASCADE,
  id_servizio INT,
  data_appuntamento DATE NOT NULL,
  ora_appuntamento TIME NOT NULL,
  barber VARCHAR(50) NOT NULL DEFAULT 'Simone',
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT unique_booking_per_barber UNIQUE (data_appuntamento, ora_appuntamento, barber)
);

-- Qui usiamo semplici statement SQL 
ALTER TABLE appuntamenti ADD COLUMN IF NOT EXISTS barber VARCHAR(50) DEFAULT 'Simone';
-- Rimuoviamo eventuali vincoli legacy
ALTER TABLE appuntamenti DROP CONSTRAINT IF EXISTS unique_booking;
ALTER TABLE appuntamenti DROP CONSTRAINT IF EXISTS unique_booking_per_barber;
-- Creiamo il vincolo che impedisce doppie prenotazioni per lo stesso barber nello stesso slot
ALTER TABLE appuntamenti ADD CONSTRAINT unique_booking_per_barber UNIQUE (data_appuntamento, ora_appuntamento, barber);

-- 3) Inserimento utenti di test
-- Admin: Simone
INSERT INTO utenti (nome, cognome, telefono, email, password_hash, security_question, security_answer_hash, ruolo)
VALUES (
  'Simone', 'Barbieri', '3330000000', 'simone@example.com',
  crypt('AdminPass1!', gen_salt('bf')),
  'pet',
  crypt('fido', gen_salt('bf')),
  'admin'
) ON CONFLICT (email) DO NOTHING;

-- Utente Mario
INSERT INTO utenti (nome, cognome, telefono, email, password_hash, security_question, security_answer_hash, ruolo)
VALUES (
  'Mario', 'Rossi', '3331112222', 'mario@example.com',
  crypt('UserPass1!', gen_salt('bf')),
  'school',
  crypt('scuola', gen_salt('bf')),
  'user'
) ON CONFLICT (email) DO NOTHING;

-- Utente Lucio
INSERT INTO utenti (nome, cognome, telefono, email, password_hash, security_question, security_answer_hash, ruolo)
VALUES (
  'Lucio', 'Bianchi', '3332223333', 'lucio@example.com',
  crypt('UserPass2!', gen_salt('bf')),
  'team',
  crypt('juve', gen_salt('bf')),
  'user'
) ON CONFLICT (email) DO NOTHING;

-- 4) Inserimento prenotazioni di esempio (assicurati che le date siano future rispetto alla data corrente)
-- Esempi: Tue-Sat validi (modifica se necessario)
INSERT INTO appuntamenti (id_utente, id_servizio, data_appuntamento, ora_appuntamento, barber, note)
VALUES
  ((SELECT id FROM utenti WHERE email='mario@example.com'), 1, '2026-02-03', '09:15:00', 'Simone', 'Prova - taglio semplice'),
  ((SELECT id FROM utenti WHERE email='lucio@example.com'), 2, '2026-02-03', '09:15:00', 'Massimo', 'Richiesta: solo shampoo'),
  ((SELECT id FROM utenti WHERE email='mario@example.com'), 1, '2026-02-04', '11:30:00', 'Massimo', 'Secondo appuntamento')
ON CONFLICT DO NOTHING;

-- 4b) Rimuovi eventuali duplicati utente/giorno creati in passato
-- Backup consigliato prima di eseguire queste DELETE!
-- Qui manteniamo la riga con id più piccolo per ogni (id_utente, data_appuntamento)
WITH duplicates AS (
  SELECT id,
         ROW_NUMBER() OVER (PARTITION BY id_utente, data_appuntamento ORDER BY id) AS rn
  FROM appuntamenti
)
DELETE FROM appuntamenti
WHERE id IN (SELECT id FROM duplicates WHERE rn > 1);

-- 4d) Crea tabella per log delle cancellazioni (registriamo motivo, chi ha cancellato e i dati dell'appuntamento)
CREATE TABLE IF NOT EXISTS cancellazioni (
  id SERIAL PRIMARY KEY,
  booking_id INT,
  id_utente INT,
  cancelled_by INT,
  reason TEXT,
  data_appuntamento DATE,
  ora_appuntamento TIME,
  barber VARCHAR(50),
  note TEXT,
  cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5) Concedi permessi all'utente applicazione 'www'
GRANT CONNECT ON DATABASE gruppo35 TO www;
GRANT USAGE ON SCHEMA public TO www;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO www;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO www;

-- 6) Query di verifica (output utili)
SELECT id, nome, cognome, email, ruolo FROM utenti;
--SELECT a.id, a.data_appuntamento, a.ora_appuntamento, a.barber, u.nome, u.cognome, u.email
--FROM appuntamenti a JOIN utenti u ON a.id_utente = u.id ORDER BY a.data_appuntamento, a.ora_appuntamento;

-- Fine script
