create or replace
PROCEDURE AJWP_DERIVE_SESSIONS AS 
	my_session_id number(19,0);
	my_session_counter number(19,0);
	my_loop_counter number(19,0);
BEGIN
  
	-- Debug
	delete from ajwp_log;
	commit;
	insert into ajwp_log (t, msg) values (sysdate, 'Start');
	commit;
	
	-- Purge history
	delete from ajwp_session_history;
	commit;
	
	-- Initialise session counter
	my_session_counter := 0;
	
	-- Used for debug
	my_loop_counter := 0;
	
	-- Loop on session events
	for item in
	(
		-- Select from 3 tables
		select event_date_time, ip, browser, usr_user_code, row_type, row_id from
		(
		  select 'SEARCH' row_type, pst_id row_id, pst_ip_address ip, pst_browser browser, pst_usr_user_code usr_user_code, pst_search_date_time event_date_time from ajwp_pages_statistics
		  union all
		  select 'SUPPLIER', pss_id, pss_viewer_ip_address, pss_browser, pss_usr_user_code, pss_view_date from ajwp_pages_statistics_supplier
		  union all
		  select 'INQUIRY', pin_id, null, null, pin_usr_user_code, pin_creation_date from ajwp_pages_inquiry
		)
		
		-- Exclude crawlers, taking care not to exclude rows where browser is null
		where (browser != 'crawler' or browser is null)
		
		-- Important: order by time ascending
		order by event_date_time
	)
	loop	
		-- Debug
		--insert into ajwp_log (t, msg) values (sysdate, 'Loop start');
		--commit;
		
		-- To process row, either ip and browser must both be populated, or user must be populated (or both) ...
		-- i.e. user / user & ip / user & browser / user & ip & browser / ip & browser
		if (item.ip is not null and item.browser is not null) or item.usr_user_code is not null then
		  
			-- Purge history of rows outside current session window
			delete from ajwp_session_history where ash_date_time < (item.event_date_time - 10/24/60);
			commit;
			
			-- Reset session id
			my_session_id := null;
			
			-- Attempt to read matching session from history
			begin
			
				select ash_session_id into my_session_id from
				(
					select rownum rn, a.* from ajwp_session_history a
					where 
						-- Filter by browser, ip and user
						-- but only if not null (i.e. no filter applied for variable if it is null)
						(ash_usr = item.usr_user_code or item.usr_user_code is null)
						and (ash_ip = item.ip or item.ip is null)
						and (ash_browser = item.browser or item.browser is null)
						
					-- Order by most recent first
					order by ash_date_time desc
					
				-- Just the 1st one
				) where rn <= 1;
				
			exception
			
				-- Catch error: 0 rows matching history query
				when NO_DATA_FOUND then
					null;
			end;
			
			-- If no matching session is found, create a new one
			if my_session_id is null then
				my_session_counter := my_session_counter + 1;
				my_session_id := my_session_counter;
			end if;
			
			-- Tag this event with session id
			ajwp_tag_session(item.row_type, item.row_id, my_session_id);    
			
			-- Insert session action into session history
			insert into ajwp_session_history (ash_date_time, ash_ip, ash_browser, ash_usr, ash_row_type, ash_row_id, ash_session_id) 
				values (item.event_date_time, item.ip, item.browser, item.usr_user_code, item.row_type, item.row_id, my_session_id);
			commit;
			
			-- Debug
			my_loop_counter := my_loop_counter + 1;
			if mod(my_loop_counter, 1000) = 0 then
				insert into ajwp_log (t, msg) values (sysdate, 'Loop end');
				commit;
			end if;
			
		end if;
		-- End of main processing loop: unqualifying rows are ignored
    
	end loop;
  
END AJWP_DERIVE_SESSIONS;
