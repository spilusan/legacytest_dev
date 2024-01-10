-- Test queries to test new buyer supplier rate relationships for consistency
--
-- @author  Yuriy Akopov
-- @date    2016-02-26

-- checks if there are no overlapping relationships - only one or no relatioship
-- of any kind is allowed between a buyer and a supplier at a time
-- result is expected to be empty
SELECT
  TO_CHAR(bsr1.bsr_valid_from, 'YYYY-MM-DD HH24:MI:SS') AS bsr1_from,
  TO_CHAR(bsr1.bsr_valid_till, 'YYYY-MM-DD HH24:MI:SS') AS bsr1_till,
  TO_CHAR(bsr2.bsr_valid_from, 'YYYY-MM-DD HH24:MI:SS') AS bsr2_from,
  TO_CHAR(bsr2.bsr_valid_till, 'YYYY-MM-DD HH24:MI:SS') AS bsr2_till,
  bsr1.*,
  bsr2.*
FROM
  buyer_supplier_rate bsr1
  JOIN buyer_supplier_rate bsr2 ON
    bsr1.bsr_spb_branch_code = bsr2.bsr_spb_branch_code
    AND bsr1.bsr_byb_branch_code = bsr2.bsr_byb_branch_code
    AND bsr1.bsr_id <> bsr2.bsr_id
    AND (
      -- two open expiration date rates
      (
        bsr1.bsr_valid_till IS NULL
        AND bsr2.bsr_valid_till IS NULL
      )
      -- one relationship date interval includes another
      OR (
        bsr1.bsr_valid_from <= bsr2.bsr_valid_from
        AND (
          bsr1.bsr_valid_till > bsr2.bsr_valid_till
          OR bsr1.bsr_valid_till IS NULL
        )
      )
      -- one relationship date interval overlaps with another
      OR (
        bsr1.bsr_valid_from <= bsr2.bsr_valid_from
        AND bsr1.bsr_valid_till > bsr2.bsr_valid_from
        AND (
          bsr1.bsr_valid_till < bsr2.bsr_valid_till
          OR bsr2.bsr_valid_till IS NULL
        )
      )
    )
;

-- returns targeted relationships locked by orders outside of their validity range
-- result is expected to be empty
SELECT
  TO_CHAR(ord.ord_submitted_date, 'YYYY-MM-DD HH24:MI:SS') AS ord_submitted_date,
  bsr.*
FROM
  buyer_supplier_rate bsr
  JOIN purchase_order ord ON
                            ord.ord_internal_ref_no = bsr.bsr_locked_ord_internal_ref_no
WHERE
  bsr.bsr_status = 'targeted'
  AND (
    ord.ord_submitted_date < bsr.bsr_valid_from
    OR ord.ord_submitted_date > bsr.bsr_valid_till
  )
;


-- returns targeted relationships created when supplier did not have an active
-- target rate and so wasn't allowed to target new buyers
-- result is expected to be empty
SELECT
  *
FROM
  buyer_supplier_rate bsr
WHERE
  bsr.bsr_status = 'targeted'
  AND NOT EXISTS(
      SELECT
        1
      FROM
        supplier_branch_rate sbr
      WHERE
        sbr.sbr_spb_branch_code = bsr.bsr_spb_branch_code
        AND sbr.sbr_valid_from <= bsr.bsr_valid_from
        AND (
          sbr.sbr_valid_till > bsr.bsr_valid_from
          OR sbr.sbr_valid_till IS NULL
        )
        AND sbr.sbr_rate_target > 0
  )
;

-- returns locked relationships which are locked with wrong rates (not active
-- at the time order was submitted
-- result is expected to be empty
SELECT
  TO_CHAR(ord.ord_submitted_date, 'YYYY-MM-DD HH24:MI:SS') AS ord_submitted_date,
  TO_CHAR(bsr.bsr_valid_from, 'YYYY-MM-DD HH24:MI:SS') AS bsr_from,
  TO_CHAR(bsr.bsr_valid_till, 'YYYY-MM-DD HH24:MI:SS') AS bsr_till,
  bsr.*
FROM
  buyer_supplier_rate bsr
  JOIN purchase_order ord ON
                            ord.ord_internal_ref_no = bsr.bsr_locked_ord_internal_ref_no
WHERE
  bsr.bsr_sbr_id IS NULL
  OR bsr.bsr_sbr_id <> (
    SELECT
      sbr.sbr_id
    FROM
      supplier_branch_rate sbr
    WHERE
      sbr.sbr_spb_branch_code = ord.ord_spb_branch_code
      AND sbr.sbr_valid_from <= ord.ord_submitted_date
      AND (
        sbr.sbr_valid_till > ord.ord_submitted_date
        OR sbr.sbr_valid_till IS NULL
      )
  )
;

-- returns ongoing locked relationships where locked period does not corresspond
-- the settings in the rate (for ended relationships this might be different
-- because they might end when supplier leaves the pricing model)
-- result is expected to be empty
SELECT
  TO_CHAR(ord.ord_submitted_date, 'YYYY-MM-DD HH24:MI:SS') AS ord_submitted_date,
  sbr.sbr_lock_target,
  TO_CHAR(bsr.bsr_valid_from, 'YYYY-MM-DD HH24:MI:SS') AS bsr_from,
  TO_CHAR(bsr.bsr_valid_till, 'YYYY-MM-DD HH24:MI:SS') AS bsr_till,
  bsr.*
FROM
  buyer_supplier_rate bsr
  JOIN supplier_branch_rate sbr ON
    sbr.sbr_id = bsr.bsr_sbr_id
  JOIN purchase_order ord ON
    ord.ord_internal_ref_no = bsr.bsr_locked_ord_internal_ref_no
WHERE
  bsr.bsr_status = 'targeted'
  AND NOT (
    (
      sbr.sbr_lock_target IS NULL
      AND bsr.bsr_valid_till IS NULL
    )
    OR (
      bsr.bsr_valid_till = ord.ord_submitted_date + sbr.sbr_lock_target
    )
  )
;
