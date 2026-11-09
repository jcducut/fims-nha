/*
 Navicat Premium Data Transfer

 Source Server         : postgres
 Source Server Type    : PostgreSQL
 Source Server Version : 170007 (170007)
 Source Host           : localhost:5432
 Source Catalog        : govflow360_nha
 Source Schema         : uac

 Target Server Type    : PostgreSQL
 Target Server Version : 170007 (170007)
 File Encoding         : 65001

 Date: 11/05/2026 11:35:09
*/


-- ----------------------------
-- Sequence structure for audit_logs_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."audit_logs_id_seq" CASCADE;
CREATE SEQUENCE "uac"."audit_logs_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for chat_history_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."chat_history_id_seq" CASCADE;
CREATE SEQUENCE "uac"."chat_history_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for chat_sessions_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."chat_sessions_id_seq" CASCADE;
CREATE SEQUENCE "uac"."chat_sessions_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for department_systems_department_system_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."department_systems_department_system_id_seq" CASCADE;
CREATE SEQUENCE "uac"."department_systems_department_system_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for departments_department_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."departments_department_id_seq" CASCADE;
CREATE SEQUENCE "uac"."departments_department_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for file_share_activity_activity_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."file_share_activity_activity_id_seq" CASCADE;
CREATE SEQUENCE "uac"."file_share_activity_activity_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for file_share_permissions_permission_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."file_share_permissions_permission_id_seq" CASCADE;
CREATE SEQUENCE "uac"."file_share_permissions_permission_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for file_shares_share_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."file_shares_share_id_seq" CASCADE;
CREATE SEQUENCE "uac"."file_shares_share_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for id_format_configs_config_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."id_format_configs_config_id_seq" CASCADE;
CREATE SEQUENCE "uac"."id_format_configs_config_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for id_sequences_sequence_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."id_sequences_sequence_id_seq" CASCADE;
CREATE SEQUENCE "uac"."id_sequences_sequence_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for offices_office_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."offices_office_id_seq" CASCADE;
CREATE SEQUENCE "uac"."offices_office_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for plantilla_items_plantilla_item_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."plantilla_items_plantilla_item_id_seq" CASCADE;
CREATE SEQUENCE "uac"."plantilla_items_plantilla_item_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for position_categories_position_category_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."position_categories_position_category_id_seq" CASCADE;
CREATE SEQUENCE "uac"."position_categories_position_category_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for positions_position_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."positions_position_id_seq" CASCADE;
CREATE SEQUENCE "uac"."positions_position_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for shared_folder_access_access_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."shared_folder_access_access_id_seq" CASCADE;
CREATE SEQUENCE "uac"."shared_folder_access_access_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for systems_system_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."systems_system_id_seq" CASCADE;
CREATE SEQUENCE "uac"."systems_system_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for user_level_assignments_assignment_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."user_level_assignments_assignment_id_seq" CASCADE;
CREATE SEQUENCE "uac"."user_level_assignments_assignment_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for user_offices_user_office_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."user_offices_user_office_id_seq" CASCADE;
CREATE SEQUENCE "uac"."user_offices_user_office_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Sequence structure for users_user_id_seq
-- ----------------------------
DROP SEQUENCE IF EXISTS "uac"."users_user_id_seq" CASCADE;
CREATE SEQUENCE "uac"."users_user_id_seq" 
INCREMENT 1
MINVALUE  1
MAXVALUE 2147483647
START 1
CACHE 1;

-- ----------------------------
-- Table structure for audit_logs
-- ----------------------------
DROP TABLE IF EXISTS "uac"."audit_logs" CASCADE;
CREATE TABLE "uac"."audit_logs" (
  "id" int4 NOT NULL DEFAULT nextval('"uac".audit_logs_id_seq'::regclass),
  "date_time" timestamp(6) NOT NULL,
  "script" varchar(255) COLLATE "pg_catalog"."default",
  "user" varchar(255) COLLATE "pg_catalog"."default",
  "action" varchar(255) COLLATE "pg_catalog"."default",
  "table" varchar(255) COLLATE "pg_catalog"."default",
  "field" varchar(255) COLLATE "pg_catalog"."default",
  "key_value" text COLLATE "pg_catalog"."default",
  "old_value" text COLLATE "pg_catalog"."default",
  "new_value" text COLLATE "pg_catalog"."default"
)
;
COMMENT ON TABLE "uac"."audit_logs" IS 'PHPMaker compatible - Immutable audit trail (STRICT - DO NOT MODIFY)';

-- ----------------------------
-- Table structure for chat_history
-- ----------------------------
DROP TABLE IF EXISTS "uac"."chat_history" CASCADE;
CREATE TABLE "uac"."chat_history" (
  "id" int4 NOT NULL DEFAULT nextval('"uac".chat_history_id_seq'::regclass),
  "user_id" int4 NOT NULL,
  "session_id" int4,
  "user_message" text COLLATE "pg_catalog"."default" NOT NULL,
  "ai_response" text COLLATE "pg_catalog"."default" NOT NULL,
  "files" jsonb DEFAULT '[]'::jsonb,
  "created_at" timestamp(6) DEFAULT CURRENT_TIMESTAMP,
  "updated_at" timestamp(6) DEFAULT CURRENT_TIMESTAMP
)
;
COMMENT ON COLUMN "uac"."chat_history"."user_id" IS 'ID of the user who sent the message';
COMMENT ON COLUMN "uac"."chat_history"."session_id" IS 'ID of the chat session';
COMMENT ON COLUMN "uac"."chat_history"."user_message" IS 'The message sent by the user';
COMMENT ON COLUMN "uac"."chat_history"."ai_response" IS 'The response from the AI assistant';
COMMENT ON COLUMN "uac"."chat_history"."files" IS 'JSON array of uploaded files in the conversation';
COMMENT ON COLUMN "uac"."chat_history"."created_at" IS 'Timestamp when the conversation was created';
COMMENT ON COLUMN "uac"."chat_history"."updated_at" IS 'Timestamp when the conversation was last updated';
COMMENT ON TABLE "uac"."chat_history" IS 'Stores chat conversations between users and AI assistant';

-- ----------------------------
-- Table structure for chat_sessions
-- ----------------------------
DROP TABLE IF EXISTS "uac"."chat_sessions" CASCADE;
CREATE TABLE "uac"."chat_sessions" (
  "id" int4 NOT NULL DEFAULT nextval('"uac".chat_sessions_id_seq'::regclass),
  "user_id" int4 NOT NULL,
  "session_key" varchar(128) COLLATE "pg_catalog"."default" NOT NULL,
  "title" text COLLATE "pg_catalog"."default" DEFAULT 'New Chat'::text,
  "summary" text COLLATE "pg_catalog"."default",
  "metadata" jsonb DEFAULT '{}'::jsonb,
  "message_count" int4 DEFAULT 0,
  "is_active" bool DEFAULT true,
  "created_at" timestamp(6) DEFAULT CURRENT_TIMESTAMP,
  "updated_at" timestamp(6) DEFAULT CURRENT_TIMESTAMP,
  "last_message_at" timestamp(6) DEFAULT CURRENT_TIMESTAMP
)
;
COMMENT ON COLUMN "uac"."chat_sessions"."user_id" IS 'Owner user id';
COMMENT ON COLUMN "uac"."chat_sessions"."session_key" IS 'Stable session key persisted in local storage';
COMMENT ON COLUMN "uac"."chat_sessions"."title" IS 'User friendly title';
COMMENT ON COLUMN "uac"."chat_sessions"."summary" IS 'Optional short summary generated by AI';
COMMENT ON COLUMN "uac"."chat_sessions"."metadata" IS 'Arbitrary JSON for context (e.g., department, task, context window)';
COMMENT ON COLUMN "uac"."chat_sessions"."message_count" IS 'Cached number of messages in the session';
COMMENT ON COLUMN "uac"."chat_sessions"."is_active" IS 'Flag for archived sessions';
COMMENT ON COLUMN "uac"."chat_sessions"."last_message_at" IS 'Timestamp of last activity';
COMMENT ON TABLE "uac"."chat_sessions" IS 'Logical chat sessions per user with metadata to build system prompts.';

-- ----------------------------
-- Table structure for department_systems
-- ----------------------------
DROP TABLE IF EXISTS "uac"."department_systems" CASCADE;
CREATE TABLE "uac"."department_systems" (
  "department_system_id" int4 NOT NULL DEFAULT nextval('"uac".department_systems_department_system_id_seq'::regclass),
  "department_id" int4 NOT NULL,
  "system_id" int4 NOT NULL,
  "is_active" bool DEFAULT true,
  "access_level" varchar(50) COLLATE "pg_catalog"."default" DEFAULT 'FULL'::character varying,
  "assigned_by" int4,
  "assigned_at" timestamptz(6) DEFAULT CURRENT_TIMESTAMP
)
;
COMMENT ON TABLE "uac"."department_systems" IS 'System access permissions per department';

-- ----------------------------
-- Table structure for departments
-- ----------------------------
DROP TABLE IF EXISTS "uac"."departments" CASCADE;
CREATE TABLE "uac"."departments" (
  "department_id" int4 NOT NULL DEFAULT nextval('"uac".departments_department_id_seq'::regclass),
  "department_name" varchar(150) COLLATE "pg_catalog"."default" NOT NULL,
  "description" text COLLATE "pg_catalog"."default",
  "parent_department_id" int4,
  "department_head_id" int4,
  "department_code" varchar(50) COLLATE "pg_catalog"."default",
  "department_acronym" varchar(20) COLLATE "pg_catalog"."default",
  "office_location" varchar(200) COLLATE "pg_catalog"."default",
  "contact_number" varchar(50) COLLATE "pg_catalog"."default",
  "email" varchar(100) COLLATE "pg_catalog"."default",
  "is_active" bool DEFAULT true,
  "created_at" timestamptz(6) DEFAULT CURRENT_TIMESTAMP,
  "updated_at" timestamptz(6) DEFAULT CURRENT_TIMESTAMP,
  "rc_id" int4,
  "office_id" int4,
  "department_type" varchar(50) COLLATE "pg_catalog"."default",
  "hierarchy_level" int4 DEFAULT 1,
  "is_budget_unit" bool DEFAULT false
)
;
COMMENT ON COLUMN "uac"."departments"."parent_department_id" IS 'Self-referencing for hierarchical structure';
COMMENT ON COLUMN "uac"."departments"."rc_id" IS 'Responsibility Center Code for financial tracking';
COMMENT ON COLUMN "uac"."departments"."office_id" IS 'Physical office where department is located';
COMMENT ON COLUMN "uac"."departments"."department_type" IS 'Organizational level: MAIN = Main Department, DIVISION = Division, SECTION = Section, UNIT = Unit, REGIONAL = Regional Office, DISTRICT = District Office';
COMMENT ON COLUMN "uac"."departments"."hierarchy_level" IS 'Depth level in org hierarchy: 1 = Top level, 2 = Division, 3 = Section, 4 = Unit';
COMMENT ON COLUMN "uac"."departments"."is_budget_unit" IS 'TRUE if department holds and manages budget allocations';
COMMENT ON TABLE "uac"."departments" IS 'PHPMaker base + GovFlow360 extensions - Organizational departments';

-- ----------------------------
-- Table structure for file_share_activity
-- ----------------------------
DROP TABLE IF EXISTS "uac"."file_share_activity" CASCADE;
CREATE TABLE "uac"."file_share_activity" (
  "activity_id" int4 NOT NULL DEFAULT nextval('"uac".file_share_activity_activity_id_seq'::regclass),
  "share_id" int4 NOT NULL,
  "user_id" int4 NOT NULL,
  "activity_type" varchar(20) COLLATE "pg_catalog"."default" NOT NULL,
  "activity_details" jsonb,
  "ip_address" inet,
  "user_agent" text COLLATE "pg_catalog"."default",
  "created_at" timestamp(6) DEFAULT CURRENT_TIMESTAMP
)
;
COMMENT ON TABLE "uac"."file_share_activity" IS 'Audit trail for file sharing activities';

-- ----------------------------
-- Table structure for file_share_permissions
-- ----------------------------
DROP TABLE IF EXISTS "uac"."file_share_permissions" CASCADE;
CREATE TABLE "uac"."file_share_permissions" (
  "permission_id" int4 NOT NULL DEFAULT nextval('"uac".file_share_permissions_permission_id_seq'::regclass),
  "share_id" int4 NOT NULL,
  "user_id" int4 NOT NULL,
  "permission_level" varchar(20) COLLATE "pg_catalog"."default" NOT NULL DEFAULT 'read'::character varying,
  "granted_by" int4 NOT NULL,
  "granted_at" timestamp(6) DEFAULT CURRENT_TIMESTAMP,
  "is_active" bool DEFAULT true
)
;
COMMENT ON COLUMN "uac"."file_share_permissions"."permission_level" IS 'read: view only, write: edit, full: manage permissions';
COMMENT ON TABLE "uac"."file_share_permissions" IS 'User permissions for shared files';

-- ----------------------------
-- Table structure for file_shares
-- ----------------------------
DROP TABLE IF EXISTS "uac"."file_shares" CASCADE;
CREATE TABLE "uac"."file_shares" (
  "share_id" int4 NOT NULL DEFAULT nextval('"uac".file_shares_share_id_seq'::regclass),
  "owner_user_id" int4 NOT NULL,
  "file_path" text COLLATE "pg_catalog"."default" NOT NULL,
  "file_name" varchar(255) COLLATE "pg_catalog"."default" NOT NULL,
  "file_type" varchar(10) COLLATE "pg_catalog"."default" NOT NULL,
  "share_type" varchar(20) COLLATE "pg_catalog"."default" NOT NULL DEFAULT 'specific'::character varying,
  "share_token" varchar(64) COLLATE "pg_catalog"."default",
  "is_active" bool DEFAULT true,
  "created_at" timestamp(6) DEFAULT CURRENT_TIMESTAMP,
  "expires_at" timestamp(6),
  "description" text COLLATE "pg_catalog"."default",
  "download_enabled" bool DEFAULT true,
  "edit_enabled" bool DEFAULT false
)
;
COMMENT ON COLUMN "uac"."file_shares"."file_path" IS 'S3 path relative to user directory (e.g., documents/report.docx)';
COMMENT ON COLUMN "uac"."file_shares"."share_token" IS 'Unique token for link-based sharing';
COMMENT ON COLUMN "uac"."file_shares"."edit_enabled" IS 'Whether ONLYOFFICE editing is allowed';
COMMENT ON TABLE "uac"."file_shares" IS 'Main table for tracking shared files and folders';

-- ----------------------------
-- Table structure for id_format_configs
-- ----------------------------
DROP TABLE IF EXISTS "uac"."id_format_configs" CASCADE;
CREATE TABLE "uac"."id_format_configs" (
  "config_id" int4 NOT NULL DEFAULT nextval('"uac".id_format_configs_config_id_seq'::regclass),
  "module_code" varchar(20) COLLATE "pg_catalog"."default" NOT NULL,
  "document_type" varchar(100) COLLATE "pg_catalog"."default" NOT NULL,
  "config_name" varchar(200) COLLATE "pg_catalog"."default" NOT NULL,
  "description" text COLLATE "pg_catalog"."default",
  "use_prefix" bool DEFAULT false,
  "prefix_value" varchar(50) COLLATE "pg_catalog"."default",
  "use_year" bool DEFAULT false,
  "year_format" varchar(20) COLLATE "pg_catalog"."default",
  "use_fiscal_year" bool DEFAULT false,
  "use_month" bool DEFAULT false,
  "month_format" varchar(20) COLLATE "pg_catalog"."default",
  "use_department" bool DEFAULT false,
  "department_field" varchar(100) COLLATE "pg_catalog"."default",
  "sequence_length" int4 DEFAULT 4,
  "sequence_start" int4 DEFAULT 1,
  "use_suffix" bool DEFAULT false,
  "suffix_value" varchar(50) COLLATE "pg_catalog"."default",
  "separator" varchar(10) COLLATE "pg_catalog"."default" DEFAULT '-'::character varying,
  "reset_strategy" varchar(20) COLLATE "pg_catalog"."default" DEFAULT 'YEARLY'::character varying,
  "validation_regex" varchar(500) COLLATE "pg_catalog"."default",
  "example_output" varchar(200) COLLATE "pg_catalog"."default",
  "is_active" bool DEFAULT true,
  "effective_date" date DEFAULT CURRENT_DATE,
  "obsolete_date" date,
  "created_by" int4,
  "created_at" timestamp(6) DEFAULT CURRENT_TIMESTAMP,
  "updated_at" timestamp(6) DEFAULT CURRENT_TIMESTAMP
)
;
COMMENT ON COLUMN "uac"."id_format_configs"."year_format" IS 'Options: YYYY (2025), YY (25), FY-YYYY (FY2025), FY-YY (FY25)';
COMMENT ON COLUMN "uac"."id_format_configs"."month_format" IS 'Options: MM (01), M (1), MON (JAN), MONTH (JANUARY)';
COMMENT ON COLUMN "uac"."id_format_configs"."sequence_length" IS 'Zero-padding length for sequence number. 4 = 0001, 5 = 00001';
COMMENT ON COLUMN "uac"."id_format_configs"."reset_strategy" IS 'When to reset sequence to 1: NEVER, YEARLY, FISCAL, MONTHLY, QUARTERLY';
COMMENT ON TABLE "uac"."id_format_configs" IS 'Configurable ID/code format definitions for all modules. 
Build custom formats using optional components: prefix + year + month + department + sequence + suffix.
Examples: PR-2025-0001, GSO-PO-2025-001, DV-FY2025-001';

-- ----------------------------
-- Table structure for id_reset_strategies
-- ----------------------------
DROP TABLE IF EXISTS "uac"."id_reset_strategies" CASCADE;
CREATE TABLE "uac"."id_reset_strategies" (
  "strategy_code" varchar(20) COLLATE "pg_catalog"."default" NOT NULL,
  "strategy_name" varchar(100) COLLATE "pg_catalog"."default" NOT NULL,
  "description" text COLLATE "pg_catalog"."default",
  "sort_order" int4 DEFAULT 0
)
;

-- ----------------------------
-- Table structure for id_sequences
-- ----------------------------
DROP TABLE IF EXISTS "uac"."id_sequences" CASCADE;
CREATE TABLE "uac"."id_sequences" (
  "sequence_id" int4 NOT NULL DEFAULT nextval('"uac".id_sequences_sequence_id_seq'::regclass),
  "config_id" int4 NOT NULL,
  "year_value" int4,
  "month_value" int4,
  "quarter_value" int4,
  "department_code" varchar(50) COLLATE "pg_catalog"."default",
  "current_sequence" int4 NOT NULL DEFAULT 0,
  "last_generated_id" varchar(200) COLLATE "pg_catalog"."default",
  "last_generated_at" timestamp(6),
  "created_at" timestamp(6) DEFAULT CURRENT_TIMESTAMP,
  "updated_at" timestamp(6) DEFAULT CURRENT_TIMESTAMP
)
;
COMMENT ON TABLE "uac"."id_sequences" IS 'Tracks current sequence numbers per format configuration and context.
Context can include year, month, quarter, and department depending on format config.';

-- ----------------------------
-- Table structure for notifications
-- ----------------------------
DROP TABLE IF EXISTS "uac"."notifications" CASCADE;
CREATE TABLE "uac"."notifications" (
  "id" varchar(50) COLLATE "pg_catalog"."default" NOT NULL,
  "timestamp" timestamptz(6),
  "type" varchar(20) COLLATE "pg_catalog"."default",
  "target" varchar(50) COLLATE "pg_catalog"."default",
  "user_id" int4,
  "subject" varchar(200) COLLATE "pg_catalog"."default",
  "body" text COLLATE "pg_catalog"."default",
  "link" varchar(255) COLLATE "pg_catalog"."default",
  "from_system" varchar(20) COLLATE "pg_catalog"."default",
  "is_read" bool DEFAULT false,
  "read_users" text COLLATE "pg_catalog"."default",
  "created_at" timestamptz(6) DEFAULT CURRENT_TIMESTAMP
)
;
COMMENT ON COLUMN "uac"."notifications"."user_id" IS 'No FK constraint - allows notifications even if user deleted';
COMMENT ON TABLE "uac"."notifications" IS 'PHPMaker compatible - System notifications (STRICT - DO NOT MODIFY)';

-- ----------------------------
-- Table structure for offices
-- ----------------------------
DROP TABLE IF EXISTS "uac"."offices" CASCADE;
CREATE TABLE "uac"."offices" (
  "office_id" int4 NOT NULL DEFAULT nextval('"uac".offices_office_id_seq'::regclass),
  "office_name" varchar(150) COLLATE "pg_catalog"."default" NOT NULL,
  "office_code" varchar(20) COLLATE "pg_catalog"."default" NOT NULL,
  "office_type" varchar(50) COLLATE "pg_catalog"."default",
  "location" text COLLATE "pg_catalog"."default",
  "address" text COLLATE "pg_catalog"."default",
  "region" varchar(50) COLLATE "pg_catalog"."default",
  "province" varchar(100) COLLATE "pg_catalog"."default",
  "city_municipality" varchar(100) COLLATE "pg_catalog"."default",
  "office_contact_number" varchar(50) COLLATE "pg_catalog"."default",
  "office_email" varchar(100) COLLATE "pg_catalog"."default",
  "office_telephone" varchar(50) COLLATE "pg_catalog"."default",
  "office_fax" varchar(50) COLLATE "pg_catalog"."default",
  "parent_office_id" int4,
  "is_active" bool DEFAULT true,
  "date_opened" date,
  "date_closed" date,
  "created_by" int4,
  "created_at" timestamptz(6) DEFAULT CURRENT_TIMESTAMP,
  "modified_by" int4,
  "modified_at" timestamptz(6) DEFAULT CURRENT_TIMESTAMP,
  "remarks" text COLLATE "pg_catalog"."default",
  "metadata" jsonb,
  "rc_id" int4,
  "region_code" varchar(10) COLLATE "pg_catalog"."default"
)
;
COMMENT ON TABLE "uac"."offices" IS 'NHA office locations and buildings with RCA code linkage';

-- ----------------------------
-- Table structure for plantilla_items
-- ----------------------------
DROP TABLE IF EXISTS "uac"."plantilla_items" CASCADE;
CREATE TABLE "uac"."plantilla_items" (
  "plantilla_item_id" int4 NOT NULL DEFAULT nextval('"uac".plantilla_items_plantilla_item_id_seq'::regclass),
  "department_id" int4 NOT NULL,
  "position_id" int4 NOT NULL,
  "item_number" varchar(50) COLLATE "pg_catalog"."default" NOT NULL,
  "item_description" varchar(200) COLLATE "pg_catalog"."default",
  "authorized_count" int4 DEFAULT 1,
  "is_active" bool DEFAULT true,
  "effective_date" date NOT NULL,
  "abolition_date" date,
  "annual_budget_allocation" numeric(15,2),
  "monthly_salary_allocation" numeric(15,2),
  "remarks" text COLLATE "pg_catalog"."default",
  "created_by" int4,
  "approved_by" int4,
  "created_at" timestamptz(6) DEFAULT CURRENT_TIMESTAMP,
  "updated_at" timestamptz(6) DEFAULT CURRENT_TIMESTAMP
)
;
COMMENT ON COLUMN "uac"."plantilla_items"."item_number" IS 'Unique plantilla item number per department (e.g., GSO-2025-001)';
COMMENT ON COLUMN "uac"."plantilla_items"."authorized_count" IS 'Number of persons authorized for this position in this department';
COMMENT ON COLUMN "uac"."plantilla_items"."effective_date" IS 'Date when this authorization became effective';
COMMENT ON COLUMN "uac"."plantilla_items"."abolition_date" IS 'Date when position was abolished (for historical tracking)';
COMMENT ON TABLE "uac"."plantilla_items" IS 'Authorized positions per department - the official plantilla/staffing pattern';

-- ----------------------------
-- Table structure for position_categories
-- ----------------------------
DROP TABLE IF EXISTS "uac"."position_categories" CASCADE;
CREATE TABLE "uac"."position_categories" (
  "position_category_id" int4 NOT NULL DEFAULT nextval('"uac".position_categories_position_category_id_seq'::regclass),
  "category_name" varchar(100) COLLATE "pg_catalog"."default" NOT NULL,
  "description" text COLLATE "pg_catalog"."default",
  "is_active" bool DEFAULT true,
  "created_at" timestamptz(6) DEFAULT CURRENT_TIMESTAMP
)
;
COMMENT ON TABLE "uac"."position_categories" IS 'GovFlow360 extension - Position classifications (First/Second/Third Level, Elective, etc.)';

-- ----------------------------
-- Table structure for positions
-- ----------------------------
DROP TABLE IF EXISTS "uac"."positions" CASCADE;
CREATE TABLE "uac"."positions" (
  "position_id" int4 NOT NULL DEFAULT nextval('"uac".positions_position_id_seq'::regclass),
  "position_category_id" int4,
  "position_code" varchar(50) COLLATE "pg_catalog"."default" NOT NULL,
  "position_title" varchar(200) COLLATE "pg_catalog"."default" NOT NULL,
  "position_level" int4,
  "salary_grade" int4,
  "step_increment" int4 DEFAULT 1,
  "job_description" text COLLATE "pg_catalog"."default",
  "qualification_standards" text COLLATE "pg_catalog"."default",
  "competency_requirements" jsonb,
  "is_active" bool DEFAULT true,
  "created_by" int4,
  "created_at" timestamptz(6) DEFAULT CURRENT_TIMESTAMP,
  "updated_at" timestamptz(6) DEFAULT CURRENT_TIMESTAMP
)
;
COMMENT ON COLUMN "uac"."positions"."position_code" IS 'Unique identifier like ADMIN-ASST-III, ACCT-OFFICER-IV';
COMMENT ON COLUMN "uac"."positions"."salary_grade" IS 'Philippine government salary grade (1-33 per Salary Standardization Law)';
COMMENT ON COLUMN "uac"."positions"."step_increment" IS 'Salary step within grade (1-8 per Philippine salary standardization)';
COMMENT ON COLUMN "uac"."positions"."qualification_standards" IS 'CSC-mandated qualifications: education, experience, training, eligibility';
COMMENT ON TABLE "uac"."positions" IS 'Position templates (generic definitions) - Example: "Administrative Assistant III" can exist in multiple departments';

-- ----------------------------
-- Table structure for psgc
-- ----------------------------
DROP TABLE IF EXISTS "uac"."psgc" CASCADE;
CREATE TABLE "uac"."psgc" (
  "code_10" varchar(20) COLLATE "pg_catalog"."default" NOT NULL,
  "name" varchar(100) COLLATE "pg_catalog"."default",
  "psgc_code" varchar(12) COLLATE "pg_catalog"."default",
  "level" varchar(20) COLLATE "pg_catalog"."default",
  "od_name" varchar(100) COLLATE "pg_catalog"."default",
  "city_class" varchar(5) COLLATE "pg_catalog"."default",
  "income_class" varchar(20) COLLATE "pg_catalog"."default",
  "rural_urban" varchar(10) COLLATE "pg_catalog"."default",
  "population_2015" int4,
  "population_2020" int4,
  "status" varchar(20) COLLATE "pg_catalog"."default",
  "display" varchar(255) COLLATE "pg_catalog"."default"
)
;

-- ----------------------------
-- Table structure for shared_folder_access
-- ----------------------------
DROP TABLE IF EXISTS "uac"."shared_folder_access" CASCADE;
CREATE TABLE "uac"."shared_folder_access" (
  "access_id" int4 NOT NULL DEFAULT nextval('"uac".shared_folder_access_access_id_seq'::regclass),
  "user_id" int4 NOT NULL,
  "share_id" int4 NOT NULL,
  "last_accessed" timestamp(6) DEFAULT CURRENT_TIMESTAMP,
  "is_favorite" bool DEFAULT false
)
;
COMMENT ON TABLE "uac"."shared_folder_access" IS 'Virtual shared folder access for users';

-- ----------------------------
-- Table structure for systems
-- ----------------------------
DROP TABLE IF EXISTS "uac"."systems" CASCADE;
CREATE TABLE "uac"."systems" (
  "system_id" int4 NOT NULL DEFAULT nextval('"uac".systems_system_id_seq'::regclass),
  "system_name" varchar(50) COLLATE "pg_catalog"."default" NOT NULL,
  "system_code" varchar(100) COLLATE "pg_catalog"."default" NOT NULL,
  "description" text COLLATE "pg_catalog"."default",
  "level_permissions" jsonb,
  "is_active" bool DEFAULT true,
  "url" varchar(255) COLLATE "pg_catalog"."default"
)
;
COMMENT ON TABLE "uac"."systems" IS 'PHPMaker compatible - System/module definitions';

-- ----------------------------
-- Table structure for user_level_assignments
-- ----------------------------
DROP TABLE IF EXISTS "uac"."user_level_assignments" CASCADE;
CREATE TABLE "uac"."user_level_assignments" (
  "assignment_id" int4 NOT NULL DEFAULT nextval('"uac".user_level_assignments_assignment_id_seq'::regclass),
  "system_id" int4,
  "user_id" int4 NOT NULL,
  "user_level_id" int4 NOT NULL,
  "assigned_by" int4,
  "created_at" timestamp(6)
)
;
COMMENT ON TABLE "uac"."user_level_assignments" IS 'PHPMaker compatible - User level assignments (STRICT - DO NOT MODIFY)';

-- ----------------------------
-- Table structure for user_level_permissions
-- ----------------------------
DROP TABLE IF EXISTS "uac"."user_level_permissions" CASCADE;
CREATE TABLE "uac"."user_level_permissions" (
  "user_level_id" int4 NOT NULL,
  "table_name" varchar(80) COLLATE "pg_catalog"."default" NOT NULL,
  "permission" int4 NOT NULL
)
;
COMMENT ON COLUMN "uac"."user_level_permissions"."permission" IS 'Bitmask: 1=View, 2=Add, 4=Edit, 8=Delete, etc.';
COMMENT ON TABLE "uac"."user_level_permissions" IS 'PHPMaker compatible - Table-level permissions with bitmask (STRICT - DO NOT MODIFY)';

-- ----------------------------
-- Table structure for user_levels
-- ----------------------------
DROP TABLE IF EXISTS "uac"."user_levels" CASCADE;
CREATE TABLE "uac"."user_levels" (
  "user_level_id" int4 NOT NULL,
  "name" varchar(50) COLLATE "pg_catalog"."default" NOT NULL,
  "description" text COLLATE "pg_catalog"."default",
  "system_id" int4
)
;
COMMENT ON TABLE "uac"."user_levels" IS 'PHPMaker compatible - User permission levels';

-- ----------------------------
-- Table structure for user_offices
-- ----------------------------
DROP TABLE IF EXISTS "uac"."user_offices" CASCADE;
CREATE TABLE "uac"."user_offices" (
  "user_office_id" int4 NOT NULL DEFAULT nextval('"uac".user_offices_user_office_id_seq'::regclass),
  "user_id" int4 NOT NULL,
  "office_id" int4 NOT NULL,
  "is_primary" bool DEFAULT false,
  "access_start_date" date NOT NULL DEFAULT CURRENT_DATE,
  "access_end_date" date,
  "is_active" bool DEFAULT true,
  "assigned_by" int4,
  "assigned_at" timestamptz(6) DEFAULT CURRENT_TIMESTAMP
)
;
COMMENT ON TABLE "uac"."user_offices" IS 'User to office assignments - users can access multiple offices';

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS "uac"."users" CASCADE;
CREATE TABLE "uac"."users" (
  "user_id" int4 NOT NULL DEFAULT nextval('"uac".users_user_id_seq'::regclass),
  "username" varchar(50) COLLATE "pg_catalog"."default" NOT NULL,
  "email" varchar(100) COLLATE "pg_catalog"."default" NOT NULL,
  "password_hash" varchar(255) COLLATE "pg_catalog"."default" NOT NULL,
  "first_name" varchar(50) COLLATE "pg_catalog"."default",
  "middle_name" varchar(50) COLLATE "pg_catalog"."default",
  "last_name" varchar(50) COLLATE "pg_catalog"."default",
  "suffix" varchar(10) COLLATE "pg_catalog"."default",
  "mobile_number" varchar(20) COLLATE "pg_catalog"."default",
  "photo" varchar(255) COLLATE "pg_catalog"."default",
  "user_level_id" varchar(255) COLLATE "pg_catalog"."default",
  "is_active" bool DEFAULT true,
  "date_created" timestamp(6) DEFAULT CURRENT_TIMESTAMP,
  "last_login" timestamp(6),
  "department_id" int4,
  "reports_to_user_id" int4,
  "profile" text COLLATE "pg_catalog"."default"
)
;
COMMENT ON COLUMN "uac"."users"."suffix" IS 'Name suffix: Jr., Sr., III, IV, etc.';
COMMENT ON COLUMN "uac"."users"."user_level_id" IS 'PHPMaker format: VARCHAR to store comma-separated level IDs';
COMMENT ON COLUMN "uac"."users"."department_id" IS 'Current primary department assignment (convenience field - detailed employment in HRIS)';
COMMENT ON COLUMN "uac"."users"."profile" IS 'Flexible JSONB/TEXT field for non-employment user preferences and settings';
COMMENT ON TABLE "uac"."users" IS 'PHPMaker compatible - Authentication and basic user info (employment details in HRIS schema)';

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."audit_logs_id_seq"
OWNED BY "uac"."audit_logs"."id";
SELECT setval('"uac"."audit_logs_id_seq"', 2, true);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."chat_history_id_seq"
OWNED BY "uac"."chat_history"."id";
SELECT setval('"uac"."chat_history_id_seq"', 1, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."chat_sessions_id_seq"
OWNED BY "uac"."chat_sessions"."id";
SELECT setval('"uac"."chat_sessions_id_seq"', 1, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."department_systems_department_system_id_seq"
OWNED BY "uac"."department_systems"."department_system_id";
SELECT setval('"uac"."department_systems_department_system_id_seq"', 44, true);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."departments_department_id_seq"
OWNED BY "uac"."departments"."department_id";
SELECT setval('"uac"."departments_department_id_seq"', 954, true);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."file_share_activity_activity_id_seq"
OWNED BY "uac"."file_share_activity"."activity_id";
SELECT setval('"uac"."file_share_activity_activity_id_seq"', 1, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."file_share_permissions_permission_id_seq"
OWNED BY "uac"."file_share_permissions"."permission_id";
SELECT setval('"uac"."file_share_permissions_permission_id_seq"', 1, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."file_shares_share_id_seq"
OWNED BY "uac"."file_shares"."share_id";
SELECT setval('"uac"."file_shares_share_id_seq"', 1, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."id_format_configs_config_id_seq"
OWNED BY "uac"."id_format_configs"."config_id";
SELECT setval('"uac"."id_format_configs_config_id_seq"', 14, true);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."id_sequences_sequence_id_seq"
OWNED BY "uac"."id_sequences"."sequence_id";
SELECT setval('"uac"."id_sequences_sequence_id_seq"', 1, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."offices_office_id_seq"
OWNED BY "uac"."offices"."office_id";
SELECT setval('"uac"."offices_office_id_seq"', 1, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."plantilla_items_plantilla_item_id_seq"
OWNED BY "uac"."plantilla_items"."plantilla_item_id";
SELECT setval('"uac"."plantilla_items_plantilla_item_id_seq"', 1, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."position_categories_position_category_id_seq"
OWNED BY "uac"."position_categories"."position_category_id";
SELECT setval('"uac"."position_categories_position_category_id_seq"', 8, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."positions_position_id_seq"
OWNED BY "uac"."positions"."position_id";
SELECT setval('"uac"."positions_position_id_seq"', 1, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."shared_folder_access_access_id_seq"
OWNED BY "uac"."shared_folder_access"."access_id";
SELECT setval('"uac"."shared_folder_access_access_id_seq"', 1, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."systems_system_id_seq"
OWNED BY "uac"."systems"."system_id";
SELECT setval('"uac"."systems_system_id_seq"', 12, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."user_level_assignments_assignment_id_seq"
OWNED BY "uac"."user_level_assignments"."assignment_id";
SELECT setval('"uac"."user_level_assignments_assignment_id_seq"', 1, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."user_offices_user_office_id_seq"
OWNED BY "uac"."user_offices"."user_office_id";
SELECT setval('"uac"."user_offices_user_office_id_seq"', 1, false);

-- ----------------------------
-- Alter sequences owned by
-- ----------------------------
ALTER SEQUENCE "uac"."users_user_id_seq"
OWNED BY "uac"."users"."user_id";
SELECT setval('"uac"."users_user_id_seq"', 1, true);

-- ----------------------------
-- Primary Key structure for table audit_logs
-- ----------------------------
ALTER TABLE "uac"."audit_logs" ADD CONSTRAINT "audit_logs_pkey" PRIMARY KEY ("id");

-- ----------------------------
-- Primary Key structure for table chat_history
-- ----------------------------
ALTER TABLE "uac"."chat_history" ADD CONSTRAINT "chat_history_pkey" PRIMARY KEY ("id");

-- ----------------------------
-- Primary Key structure for table chat_sessions
-- ----------------------------
ALTER TABLE "uac"."chat_sessions" ADD CONSTRAINT "chat_sessions_pkey" PRIMARY KEY ("id");

-- ----------------------------
-- Primary Key structure for table department_systems
-- ----------------------------
ALTER TABLE "uac"."department_systems" ADD CONSTRAINT "department_systems_pkey" PRIMARY KEY ("department_system_id");

-- ----------------------------
-- Primary Key structure for table departments
-- ----------------------------
ALTER TABLE "uac"."departments" ADD CONSTRAINT "departments_pkey" PRIMARY KEY ("department_id");

-- ----------------------------
-- Primary Key structure for table file_share_activity
-- ----------------------------
ALTER TABLE "uac"."file_share_activity" ADD CONSTRAINT "file_share_activity_pkey" PRIMARY KEY ("activity_id");

-- ----------------------------
-- Primary Key structure for table file_share_permissions
-- ----------------------------
ALTER TABLE "uac"."file_share_permissions" ADD CONSTRAINT "file_share_permissions_pkey" PRIMARY KEY ("permission_id");

-- ----------------------------
-- Primary Key structure for table file_shares
-- ----------------------------
ALTER TABLE "uac"."file_shares" ADD CONSTRAINT "file_shares_pkey" PRIMARY KEY ("share_id");

-- ----------------------------
-- Primary Key structure for table id_format_configs
-- ----------------------------
ALTER TABLE "uac"."id_format_configs" ADD CONSTRAINT "id_format_configs_pkey" PRIMARY KEY ("config_id");

-- ----------------------------
-- Primary Key structure for table id_reset_strategies
-- ----------------------------
ALTER TABLE "uac"."id_reset_strategies" ADD CONSTRAINT "id_reset_strategies_pkey" PRIMARY KEY ("strategy_code");

-- ----------------------------
-- Primary Key structure for table id_sequences
-- ----------------------------
ALTER TABLE "uac"."id_sequences" ADD CONSTRAINT "id_sequences_pkey" PRIMARY KEY ("sequence_id");

-- ----------------------------
-- Primary Key structure for table notifications
-- ----------------------------
ALTER TABLE "uac"."notifications" ADD CONSTRAINT "notifications_pkey" PRIMARY KEY ("id");

-- ----------------------------
-- Primary Key structure for table offices
-- ----------------------------
ALTER TABLE "uac"."offices" ADD CONSTRAINT "offices_pkey" PRIMARY KEY ("office_id");

-- ----------------------------
-- Primary Key structure for table plantilla_items
-- ----------------------------
ALTER TABLE "uac"."plantilla_items" ADD CONSTRAINT "plantilla_items_pkey" PRIMARY KEY ("plantilla_item_id");

-- ----------------------------
-- Primary Key structure for table position_categories
-- ----------------------------
ALTER TABLE "uac"."position_categories" ADD CONSTRAINT "position_categories_pkey" PRIMARY KEY ("position_category_id");

-- ----------------------------
-- Primary Key structure for table positions
-- ----------------------------
ALTER TABLE "uac"."positions" ADD CONSTRAINT "positions_pkey" PRIMARY KEY ("position_id");

-- ----------------------------
-- Primary Key structure for table psgc
-- ----------------------------
ALTER TABLE "uac"."psgc" ADD CONSTRAINT "psgc_pkey" PRIMARY KEY ("code_10");

-- ----------------------------
-- Primary Key structure for table shared_folder_access
-- ----------------------------
ALTER TABLE "uac"."shared_folder_access" ADD CONSTRAINT "shared_folder_access_pkey" PRIMARY KEY ("access_id");

-- ----------------------------
-- Primary Key structure for table systems
-- ----------------------------
ALTER TABLE "uac"."systems" ADD CONSTRAINT "systems_pkey" PRIMARY KEY ("system_id");

-- ----------------------------
-- Primary Key structure for table user_level_assignments
-- ----------------------------
ALTER TABLE "uac"."user_level_assignments" ADD CONSTRAINT "user_level_assignments_pkey" PRIMARY KEY ("assignment_id");

-- ----------------------------
-- Primary Key structure for table user_level_permissions
-- ----------------------------
ALTER TABLE "uac"."user_level_permissions" ADD CONSTRAINT "user_level_permissions_pkey" PRIMARY KEY ("user_level_id", "table_name");

-- ----------------------------
-- Primary Key structure for table user_levels
-- ----------------------------
ALTER TABLE "uac"."user_levels" ADD CONSTRAINT "user_levels_pkey" PRIMARY KEY ("user_level_id");

-- ----------------------------
-- Primary Key structure for table user_offices
-- ----------------------------
ALTER TABLE "uac"."user_offices" ADD CONSTRAINT "user_offices_pkey" PRIMARY KEY ("user_office_id");

-- ----------------------------
-- Primary Key structure for table users
-- ----------------------------
ALTER TABLE "uac"."users" ADD CONSTRAINT "users_pkey" PRIMARY KEY ("user_id");

-- ----------------------------
-- Unique Constraints
-- ----------------------------
ALTER TABLE "uac"."users" ADD CONSTRAINT "users_username_key" UNIQUE ("username");
ALTER TABLE "uac"."users" ADD CONSTRAINT "users_email_key" UNIQUE ("email");
ALTER TABLE "uac"."systems" ADD CONSTRAINT "systems_system_code_key" UNIQUE ("system_code");
ALTER TABLE "uac"."chat_sessions" ADD CONSTRAINT "chat_sessions_session_key_key" UNIQUE ("session_key");
ALTER TABLE "uac"."file_shares" ADD CONSTRAINT "file_shares_share_token_key" UNIQUE ("share_token");

-- ----------------------------
-- Indexes
-- ----------------------------

-- audit_logs
CREATE INDEX "idx_audit_logs_user" ON "uac"."audit_logs" ("user");
CREATE INDEX "idx_audit_logs_action" ON "uac"."audit_logs" ("action");
CREATE INDEX "idx_audit_logs_table" ON "uac"."audit_logs" ("table");
CREATE INDEX "idx_audit_logs_date_time" ON "uac"."audit_logs" ("date_time" DESC);

-- chat_history
CREATE INDEX "idx_chat_history_user_id" ON "uac"."chat_history" ("user_id");
CREATE INDEX "idx_chat_history_session_id" ON "uac"."chat_history" ("session_id");
CREATE INDEX "idx_chat_history_created_at" ON "uac"."chat_history" ("created_at" DESC);

-- chat_sessions
CREATE INDEX "idx_chat_sessions_user_id" ON "uac"."chat_sessions" ("user_id");
CREATE INDEX "idx_chat_sessions_is_active" ON "uac"."chat_sessions" ("is_active");
CREATE INDEX "idx_chat_sessions_last_message_at" ON "uac"."chat_sessions" ("last_message_at" DESC);

-- department_systems
CREATE INDEX "idx_department_systems_department_id" ON "uac"."department_systems" ("department_id");
CREATE INDEX "idx_department_systems_system_id" ON "uac"."department_systems" ("system_id");
CREATE INDEX "idx_department_systems_is_active" ON "uac"."department_systems" ("is_active");

-- departments
CREATE INDEX "idx_departments_parent_id" ON "uac"."departments" ("parent_department_id");
CREATE INDEX "idx_departments_department_code" ON "uac"."departments" ("department_code");
CREATE INDEX "idx_departments_is_active" ON "uac"."departments" ("is_active");
CREATE INDEX "idx_departments_office_id" ON "uac"."departments" ("office_id");
CREATE INDEX "idx_departments_rc_id" ON "uac"."departments" ("rc_id");
CREATE INDEX "idx_departments_department_type" ON "uac"."departments" ("department_type");
CREATE INDEX "idx_departments_hierarchy_level" ON "uac"."departments" ("hierarchy_level");
CREATE INDEX "idx_departments_is_budget_unit" ON "uac"."departments" ("is_budget_unit") WHERE is_budget_unit = true;

-- file_share_activity
CREATE INDEX "idx_file_share_activity_share_id" ON "uac"."file_share_activity" ("share_id");
CREATE INDEX "idx_file_share_activity_user_id" ON "uac"."file_share_activity" ("user_id");
CREATE INDEX "idx_file_share_activity_activity_type" ON "uac"."file_share_activity" ("activity_type");
CREATE INDEX "idx_file_share_activity_created_at" ON "uac"."file_share_activity" ("created_at" DESC);

-- file_share_permissions
CREATE INDEX "idx_file_share_permissions_share_id" ON "uac"."file_share_permissions" ("share_id");
CREATE INDEX "idx_file_share_permissions_user_id" ON "uac"."file_share_permissions" ("user_id");
CREATE INDEX "idx_file_share_permissions_is_active" ON "uac"."file_share_permissions" ("is_active") WHERE is_active = true;

-- file_shares
CREATE INDEX "idx_file_shares_owner_user_id" ON "uac"."file_shares" ("owner_user_id");
CREATE INDEX "idx_file_shares_is_active" ON "uac"."file_shares" ("is_active") WHERE is_active = true;
CREATE INDEX "idx_file_shares_share_type" ON "uac"."file_shares" ("share_type");
CREATE INDEX "idx_file_shares_file_type" ON "uac"."file_shares" ("file_type");
CREATE INDEX "idx_file_shares_expires_at" ON "uac"."file_shares" ("expires_at") WHERE expires_at IS NOT NULL;

-- id_format_configs
CREATE INDEX "idx_id_format_configs_module_code" ON "uac"."id_format_configs" ("module_code");
CREATE INDEX "idx_id_format_configs_document_type" ON "uac"."id_format_configs" ("document_type");
CREATE INDEX "idx_id_format_configs_is_active" ON "uac"."id_format_configs" ("is_active");

-- id_sequences
CREATE INDEX "idx_id_sequences_config_id" ON "uac"."id_sequences" ("config_id");
CREATE INDEX "idx_id_sequences_year_value" ON "uac"."id_sequences" ("year_value");

-- notifications
CREATE INDEX "idx_notifications_user_id" ON "uac"."notifications" ("user_id");
CREATE INDEX "idx_notifications_is_read" ON "uac"."notifications" ("is_read") WHERE is_read = false;
CREATE INDEX "idx_notifications_type" ON "uac"."notifications" ("type");
CREATE INDEX "idx_notifications_target" ON "uac"."notifications" ("target");
CREATE INDEX "idx_notifications_created_at" ON "uac"."notifications" ("created_at" DESC);

-- offices
CREATE INDEX "idx_offices_office_code" ON "uac"."offices" ("office_code");
CREATE INDEX "idx_offices_is_active" ON "uac"."offices" ("is_active") WHERE is_active = true;
CREATE INDEX "idx_offices_office_type" ON "uac"."offices" ("office_type");
CREATE INDEX "idx_offices_parent_office_id" ON "uac"."offices" ("parent_office_id");
CREATE INDEX "idx_offices_region_code" ON "uac"."offices" ("region_code");
CREATE INDEX "idx_offices_rc_id" ON "uac"."offices" ("rc_id");

-- plantilla_items
CREATE INDEX "idx_plantilla_items_department_id" ON "uac"."plantilla_items" ("department_id");
CREATE INDEX "idx_plantilla_items_position_id" ON "uac"."plantilla_items" ("position_id");
CREATE INDEX "idx_plantilla_items_is_active" ON "uac"."plantilla_items" ("is_active");
CREATE INDEX "idx_plantilla_items_item_number" ON "uac"."plantilla_items" ("item_number");
CREATE INDEX "idx_plantilla_items_effective_date" ON "uac"."plantilla_items" ("effective_date");

-- position_categories
CREATE INDEX "idx_position_categories_is_active" ON "uac"."position_categories" ("is_active");

-- positions
CREATE INDEX "idx_positions_position_code" ON "uac"."positions" ("position_code");
CREATE INDEX "idx_positions_position_category_id" ON "uac"."positions" ("position_category_id");
CREATE INDEX "idx_positions_salary_grade" ON "uac"."positions" ("salary_grade");
CREATE INDEX "idx_positions_is_active" ON "uac"."positions" ("is_active");

-- psgc
CREATE INDEX "idx_psgc_level" ON "uac"."psgc" ("level");
CREATE INDEX "idx_psgc_name" ON "uac"."psgc" ("name");
CREATE INDEX "idx_psgc_psgc_code" ON "uac"."psgc" ("psgc_code");

-- shared_folder_access
CREATE INDEX "idx_shared_folder_access_user_id" ON "uac"."shared_folder_access" ("user_id");
CREATE INDEX "idx_shared_folder_access_share_id" ON "uac"."shared_folder_access" ("share_id");
CREATE INDEX "idx_shared_folder_access_is_favorite" ON "uac"."shared_folder_access" ("is_favorite") WHERE is_favorite = true;

-- systems
CREATE INDEX "idx_systems_system_code" ON "uac"."systems" ("system_code");
CREATE INDEX "idx_systems_is_active" ON "uac"."systems" ("is_active");

-- user_level_assignments
CREATE INDEX "idx_user_level_assignments_user_id" ON "uac"."user_level_assignments" ("user_id");
CREATE INDEX "idx_user_level_assignments_user_level_id" ON "uac"."user_level_assignments" ("user_level_id");
CREATE INDEX "idx_user_level_assignments_system_id" ON "uac"."user_level_assignments" ("system_id");
CREATE INDEX "idx_user_level_assignments_assigned_by" ON "uac"."user_level_assignments" ("assigned_by");

-- user_level_permissions
CREATE INDEX "idx_user_level_permissions_user_level_id" ON "uac"."user_level_permissions" ("user_level_id");

-- user_levels
CREATE INDEX "idx_user_levels_system_id" ON "uac"."user_levels" ("system_id");

-- user_offices
CREATE INDEX "idx_user_offices_user_id" ON "uac"."user_offices" ("user_id");
CREATE INDEX "idx_user_offices_office_id" ON "uac"."user_offices" ("office_id");
CREATE INDEX "idx_user_offices_is_active" ON "uac"."user_offices" ("is_active") WHERE is_active = true;
CREATE INDEX "idx_user_offices_is_primary" ON "uac"."user_offices" ("is_primary") WHERE is_primary = true;

-- users
CREATE INDEX "idx_users_department_id" ON "uac"."users" ("department_id");
CREATE INDEX "idx_users_username" ON "uac"."users" ("username");
CREATE INDEX "idx_users_email" ON "uac"."users" ("email");
CREATE INDEX "idx_users_is_active" ON "uac"."users" ("is_active");
CREATE INDEX "idx_users_last_login" ON "uac"."users" ("last_login" DESC);
CREATE INDEX "idx_users_reports_to_user_id" ON "uac"."users" ("reports_to_user_id");
