create or replace
PROCEDURE AJWP_TAG_SESSION 
(
  MY_ROW_TYPE IN VARCHAR2  
, MY_ROW_ID IN NUMBER  
, MY_SESSION_ID IN NUMBER  
) AS 
BEGIN
	if MY_ROW_TYPE = 'SEARCH' then
		update ajwp_pages_statistics set pst_session_id = MY_SESSION_ID where pst_id = MY_ROW_ID;
	elsif MY_ROW_TYPE = 'SUPPLIER' then
		update ajwp_pages_statistics_supplier set pss_session_id = MY_SESSION_ID where pss_id = MY_ROW_ID;
	elsif MY_ROW_TYPE = 'INQUIRY' then
		update ajwp_pages_inquiry set pin_session_id = MY_SESSION_ID where pin_id = MY_ROW_ID;
	end if;
	
	commit;
	
END AJWP_TAG_SESSION;
