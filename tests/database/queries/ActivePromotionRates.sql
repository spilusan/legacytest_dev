-- Test queries to test new historical rates for consistency
--
-- @author  Yuriy Akopov
-- @date    2016-02-26

-- checks if there are any rates which end in the future
-- (as opposed to have an open expiration date of NULL)
-- result is expected to be empty
SELECT
  *
FROM
  supplier_branch_rate
WHERE
  sbr_valid_till > SYSDATE
;

-- returns suppliers that have more than 1 active rate at a time
-- result is expected to be empty
SELECT
  TO_CHAR(sbr1.sbr_valid_from, 'YYYY-MM-DD HH24:MI:SS') AS sbr1_from,
  TO_CHAR(sbr1.sbr_valid_till, 'YYYY-MM-DD HH24:MI:SS') AS sbr1_till,
  TO_CHAR(sbr2.sbr_valid_from, 'YYYY-MM-DD HH24:MI:SS') AS sbr2_from,
  TO_CHAR(sbr2.sbr_valid_till, 'YYYY-MM-DD HH24:MI:SS') AS sbr2_till,
  sbr1.*,
  sbr2.*
FROM
  supplier_branch_rate sbr1
  JOIN supplier_branch_rate sbr2 ON
     sbr1.sbr_spb_branch_code = sbr2.sbr_spb_branch_code
     AND sbr1.sbr_id <> sbr2.sbr_id
     AND (
       -- two open expiration date rates
       (
         sbr1.sbr_valid_till IS NULL
         AND sbr2.sbr_valid_till IS NULL
       )
       -- one rate date interval includes another
       OR (
         sbr1.sbr_valid_from <= sbr2.sbr_valid_from
         AND (
           sbr1.sbr_valid_till > sbr2.sbr_valid_till
           OR sbr1.sbr_valid_till IS NULL
         )
       )
       -- one rate date interval overlaps with another
       OR (
         sbr1.sbr_valid_from <= sbr2.sbr_valid_from
         AND sbr1.sbr_valid_till > sbr2.sbr_valid_from
         AND (
           sbr1.sbr_valid_till < sbr2.sbr_valid_till
           OR sbr2.sbr_valid_till IS NULL
         )
       )
     )
;

-- returns records that have target rate defined which didn't come from a contract
-- results is expected to be empty
SELECT
  *
FROM
  supplier_branch_rate
WHERE
  sbr_sf_source_type <> 'contract'
  AND sbr_rate_target IS NOT NULL
;

-- returns records that have target rated defined and lower than the standard rate
-- result is expected to be empty
SELECT
  *
FROM
  supplier_branch_rate
WHERE
  sbr_rate_target IS NOT NULL
  AND sbr_rate_target <= sbr_rate_standard
;

-- returns suppliers where active historical rate exists but is not equal to
-- their legacy rate in SPB_MONETIZATION_PERCENT
-- result is expected to be empty
SELECT
  spb.spb_branch_code,
  spb.spb_monetization_percent,
  sbr.sbr_rate_standard,
  sbr.sbr_valid_from,
  sbr.sbr_sf_source_type
FROM
  supplier_branch_rate sbr
  JOIN supplier_branch spb ON
                             spb.spb_branch_code = sbr.sbr_spb_branch_code
WHERE
  sbr.sbr_valid_till IS NULL
  AND sbr.sbr_rate_standard <> spb.spb_monetization_percent
;
