-- =====================================================================
-- ONLINE AUTOMATED STUDENT CLEARANCE SYSTEM (OASCS)
-- PostgreSQL Schema for Supabase
-- Run this in: Supabase Dashboard → SQL Editor → New Query
-- =====================================================================

-- Drop existing tables (safe order respecting foreign keys)
DROP TABLE IF EXISTS notifications     CASCADE;
DROP TABLE IF EXISTS audit_logs        CASCADE;
DROP TABLE IF EXISTS clearance_items   CASCADE;
DROP TABLE IF EXISTS clearance_requests CASCADE;
DROP TABLE IF EXISTS departments       CASCADE;
DROP TABLE IF EXISTS users             CASCADE;

-- ── 1. departments ────────────────────────────────────────────────────────────
CREATE TABLE departments (
    department_id   TEXT PRIMARY KEY,
    department_name TEXT NOT NULL UNIQUE,
    officer_id      TEXT  -- references users(user_id), added after users table
);

-- ── 2. users ──────────────────────────────────────────────────────────────────
CREATE TABLE users (
    user_id         TEXT PRIMARY KEY,
    full_name       TEXT NOT NULL,
    email           TEXT UNIQUE,
    matric_no       TEXT UNIQUE,
    password_hash   TEXT NOT NULL,
    role            TEXT NOT NULL CHECK (role IN ('student', 'officer', 'admin')),
    department_id   TEXT REFERENCES departments(department_id) ON DELETE SET NULL,
    academic_dept   TEXT,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Add FK from departments → users now that users exists
ALTER TABLE departments
    ADD CONSTRAINT fk_dept_officer
    FOREIGN KEY (officer_id) REFERENCES users(user_id) ON DELETE SET NULL;

-- ── 3. clearance_requests ─────────────────────────────────────────────────────
CREATE TABLE clearance_requests (
    request_id  TEXT PRIMARY KEY,
    student_id  TEXT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    level       TEXT CHECK (level IN ('100L', '200L', '300L', '400L', '500L')),
    status      TEXT DEFAULT 'pending'
                     CHECK (status IN ('pending', 'in_progress', 'approved', 'rejected')),
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    updated_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ── 4. clearance_items ────────────────────────────────────────────────────────
CREATE TABLE clearance_items (
    item_id          TEXT PRIMARY KEY,
    request_id       TEXT NOT NULL REFERENCES clearance_requests(request_id) ON DELETE CASCADE,
    department_id    TEXT NOT NULL REFERENCES departments(department_id) ON DELETE RESTRICT,
    status           TEXT DEFAULT 'pending'
                          CHECK (status IN ('pending', 'approved', 'rejected')),
    rejection_reason TEXT,
    document_path    TEXT DEFAULT NULL,
    uploaded_at      TIMESTAMPTZ DEFAULT NULL,
    reviewed_by      TEXT REFERENCES users(user_id) ON DELETE SET NULL,
    reviewed_at      TIMESTAMPTZ
);

-- ── 5. audit_logs ─────────────────────────────────────────────────────────────
CREATE TABLE audit_logs (
    log_id             TEXT PRIMARY KEY,
    user_id            TEXT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    action             TEXT NOT NULL CHECK (action IN (
                           'SUBMIT_REQUEST', 'APPROVE_ITEM', 'REJECT_ITEM',
                           'RESUBMIT_ITEM', 'ADMIN_OVERRIDE', 'GENERATE_CERTIFICATE'
                       )),
    affected_table     TEXT,
    affected_record_id TEXT,
    timestamp          TIMESTAMPTZ DEFAULT NOW()
);

-- ── 6. notifications ──────────────────────────────────────────────────────────
CREATE TABLE notifications (
    notification_id TEXT PRIMARY KEY,
    user_id         TEXT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    message         TEXT NOT NULL,
    email_sent      SMALLINT DEFAULT 0 CHECK (email_sent IN (0, 1)),
    sent_at         TIMESTAMPTZ,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ── Seed: Departments ─────────────────────────────────────────────────────────
INSERT INTO departments (department_id, department_name, officer_id) VALUES
('d1111111-1111-1111-1111-111111111111', 'Academic Affairs', NULL),
('d2222222-2222-2222-2222-222222222222', 'Bursary',          NULL),
('d3333333-3333-3333-3333-333333333333', 'Student Affairs',  NULL),
('d4444444-4444-4444-4444-444444444444', 'College',          NULL),
('d5555555-5555-5555-5555-555555555555', 'ICT',              NULL),
('d6666666-6666-6666-6666-666666666666', 'Counselling',      NULL),
('d7777777-7777-7777-7777-777777777777', 'Library',          NULL),
('d8888888-8888-8888-8888-888888888888', 'Medical',          NULL);
