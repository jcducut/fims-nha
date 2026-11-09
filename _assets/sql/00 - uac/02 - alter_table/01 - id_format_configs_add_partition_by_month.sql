-- ============================================================
-- ALTER: uac.id_format_configs
-- Add partition_by_month column
--
-- Problem this solves:
--   When use_month = TRUE, generate_next_id() previously used
--   the month as a sequence partition key AND as a format token.
--   This caused each month to start its own counter from 1,
--   e.g. JEV-26-01-0001 ... JEV-26-01-0050 → JEV-26-02-0001
--   (February restarted at 0001 even though January ended at 0050).
--
-- With partition_by_month = FALSE:
--   Month is shown in the formatted ID (JEV-26-02-0051 ✓)
--   but the sequence is shared across the whole year.
--   The counter only resets when the year changes (YEARLY strategy).
--
-- DEFAULT TRUE preserves the existing behaviour for all other configs.
-- ============================================================

ALTER TABLE uac.id_format_configs
    ADD COLUMN IF NOT EXISTS partition_by_month BOOLEAN NOT NULL DEFAULT TRUE;

COMMENT ON COLUMN uac.id_format_configs.partition_by_month IS
    'Controls whether the month is used as a sequence partition key. '
    'TRUE (default): each month gets its own counter (resets to sequence_start every month). '
    'FALSE: month appears in the formatted ID but the counter runs continuously across '
    'all months within the same year — only the year acts as a reset boundary.';


-- ============================================================
-- REPLACE: uac.generate_next_id
-- Separates "format month" from "partition month"
-- ============================================================

DROP FUNCTION IF EXISTS "uac"."generate_next_id"(int4, varchar, date) CASCADE;

CREATE OR REPLACE FUNCTION "uac"."generate_next_id"(
    "p_config_id"       int4,
    "p_department_code" varchar  = NULL::character varying,
    "p_reference_date"  date     = CURRENT_DATE
)
RETURNS "pg_catalog"."varchar" AS $BODY$
DECLARE
    v_config        RECORD;
    v_sequence      RECORD;
    v_year          INT;
    v_month         INT;     -- month used for FORMAT output
    v_seq_month     INT;     -- month used for SEQUENCE partitioning (may be NULL)
    v_quarter       INT;
    v_next_seq      INT;
    v_generated_id  VARCHAR(200);
    v_year_str      VARCHAR(20);
    v_month_str     VARCHAR(20);
    v_seq_str       VARCHAR(50);
BEGIN
    -- ── Load config ─────────────────────────────────────────────────────
    SELECT * INTO v_config
    FROM uac.id_format_configs
    WHERE config_id = p_config_id AND is_active = TRUE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Invalid or inactive config_id: %', p_config_id;
    END IF;

    -- ── Year context ─────────────────────────────────────────────────────
    IF v_config.use_year THEN
        IF v_config.use_fiscal_year THEN
            v_year := uac.get_fiscal_year(p_reference_date);
        ELSE
            v_year := EXTRACT(YEAR FROM p_reference_date);
        END IF;
    END IF;

    -- ── Month context ─────────────────────────────────────────────────────
    -- v_month      → always set when we need month in the formatted output
    -- v_seq_month  → used as the sequence partition key; NULL when
    --                partition_by_month = FALSE so all months share one row
    IF v_config.use_month OR v_config.reset_strategy = 'MONTHLY' THEN
        v_month := EXTRACT(MONTH FROM p_reference_date);
    END IF;

    IF v_config.reset_strategy = 'MONTHLY' THEN
        -- MONTHLY strategy always partitions by month regardless of the flag
        v_seq_month := v_month;
    ELSIF v_config.partition_by_month AND v_month IS NOT NULL THEN
        v_seq_month := v_month;
    ELSE
        v_seq_month := NULL;   -- continuous counter across months
    END IF;

    -- ── Quarter context ───────────────────────────────────────────────────
    IF v_config.reset_strategy = 'QUARTERLY' THEN
        v_quarter := CEIL(EXTRACT(MONTH FROM p_reference_date) / 3.0);
    END IF;

    -- ── Get or create sequence record (row-level lock) ────────────────────
    SELECT * INTO v_sequence
    FROM uac.id_sequences
    WHERE config_id = p_config_id
        AND (year_value     = v_year        OR (year_value     IS NULL AND v_year        IS NULL))
        AND (month_value    = v_seq_month   OR (month_value    IS NULL AND v_seq_month   IS NULL))
        AND (quarter_value  = v_quarter     OR (quarter_value  IS NULL AND v_quarter     IS NULL))
        AND (department_code = p_department_code OR (department_code IS NULL AND p_department_code IS NULL))
    FOR UPDATE;

    IF NOT FOUND THEN
        INSERT INTO uac.id_sequences (
            config_id, year_value, month_value, quarter_value,
            department_code, current_sequence
        ) VALUES (
            p_config_id, v_year, v_seq_month, v_quarter,
            p_department_code, v_config.sequence_start
        )
        RETURNING * INTO v_sequence;
        v_next_seq := v_config.sequence_start;
    ELSE
        v_next_seq := v_sequence.current_sequence + 1;
    END IF;

    -- ── Build formatted ID ────────────────────────────────────────────────
    v_generated_id := '';

    -- Prefix
    IF v_config.use_prefix AND v_config.prefix_value IS NOT NULL THEN
        v_generated_id := v_config.prefix_value;
    END IF;

    -- Year
    IF v_config.use_year THEN
        CASE v_config.year_format
            WHEN 'YYYY'    THEN v_year_str := v_year::VARCHAR;
            WHEN 'YY'      THEN v_year_str := RIGHT(v_year::VARCHAR, 2);
            WHEN 'FY-YYYY' THEN v_year_str := 'FY' || v_year::VARCHAR;
            WHEN 'FY-YY'   THEN v_year_str := 'FY' || RIGHT(v_year::VARCHAR, 2);
            ELSE                v_year_str := v_year::VARCHAR;
        END CASE;
        IF v_generated_id != '' THEN
            v_generated_id := v_generated_id || v_config.separator;
        END IF;
        v_generated_id := v_generated_id || v_year_str;
    END IF;

    -- Month (uses v_month — the display month, always from the reference date)
    IF v_config.use_month THEN
        CASE v_config.month_format
            WHEN 'MM'    THEN v_month_str := LPAD(v_month::VARCHAR, 2, '0');
            WHEN 'M'     THEN v_month_str := v_month::VARCHAR;
            WHEN 'MON'   THEN v_month_str := UPPER(TO_CHAR(p_reference_date, 'Mon'));
            WHEN 'MONTH' THEN v_month_str := UPPER(TO_CHAR(p_reference_date, 'Month'));
            ELSE              v_month_str := LPAD(v_month::VARCHAR, 2, '0');
        END CASE;
        IF v_generated_id != '' THEN
            v_generated_id := v_generated_id || v_config.separator;
        END IF;
        v_generated_id := v_generated_id || v_month_str;
    END IF;

    -- Department
    IF v_config.use_department AND p_department_code IS NOT NULL THEN
        IF v_generated_id != '' THEN
            v_generated_id := v_generated_id || v_config.separator;
        END IF;
        v_generated_id := v_generated_id || p_department_code;
    END IF;

    -- Sequence number
    v_seq_str := LPAD(v_next_seq::VARCHAR, v_config.sequence_length, '0');
    IF v_generated_id != '' THEN
        v_generated_id := v_generated_id || v_config.separator;
    END IF;
    v_generated_id := v_generated_id || v_seq_str;

    -- Suffix
    IF v_config.use_suffix AND v_config.suffix_value IS NOT NULL THEN
        v_generated_id := v_generated_id || v_config.separator || v_config.suffix_value;
    END IF;

    -- ── Persist updated sequence ──────────────────────────────────────────
    UPDATE uac.id_sequences
    SET current_sequence  = v_next_seq,
        last_generated_id = v_generated_id,
        last_generated_at = CURRENT_TIMESTAMP,
        updated_at        = CURRENT_TIMESTAMP
    WHERE sequence_id = v_sequence.sequence_id;

    RETURN v_generated_id;
END;
$BODY$
LANGUAGE plpgsql VOLATILE COST 100;

COMMENT ON FUNCTION "uac"."generate_next_id"(int4, varchar, date) IS
'Generates the next formatted ID based on a format configuration.

Parameters:
  p_config_id       — config to use (uac.id_format_configs.config_id)
  p_department_code — optional department code when config has use_department = true
  p_reference_date  — date for year / month / quarter context (defaults to today)

Key behaviour change (partition_by_month):
  When partition_by_month = FALSE the sequence counter is shared across all months
  within the same year. The month token still appears in the formatted output, but
  the underlying uac.id_sequences row is keyed only by year — so the counter never
  resets mid-year.

Example:
  SELECT uac.generate_next_id(1, NULL, CURRENT_DATE);';
