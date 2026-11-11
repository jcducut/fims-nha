-- UPDATE: fims.sl_masters
-- Links the seeded SL masters (see "01 - table/23 - fims_sl_masters.sql") to their GL control
-- accounts. Done here rather than in the table file because fims.chart_of_accounts is only
-- seeded after the "01 - table" phase. The EXISTS guard keeps this safe if a code is missing.
UPDATE fims.sl_masters sm
SET account_code = v.account_code
FROM (VALUES
    ('SL-AP-DVDEMO01',  '2-01-01-010'),
    ('SL-AP-GENERAL',   '2-01-01-010'),
    ('SL-CASH-MDS01',   '1-01-02-020'),
    ('SL-DUEBIR-WTAX',  '2-02-01-010'),
    ('SL-RETENTION-01', '2-04-01-040')
) AS v (sl_code, account_code)
WHERE sm.sl_code = v.sl_code
  AND EXISTS (SELECT 1 FROM fims.chart_of_accounts c WHERE c.account_code = v.account_code);
