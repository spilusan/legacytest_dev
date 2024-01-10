-- v2
create or replace
PROCEDURE AJWP_DERIVE_SESSIONS AS 
	my_session_id number;
	my_session_counter number;
	my_loop_counter number;
	matchIpAndBrowser varchar2(255);
BEGIN
  
	-- Debug
	--delete from ajwp_log;
	--commit;
	--insert into ajwp_log (t, msg) values (sysdate, 'Start');
	--commit;
	
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
		
		-- Purge history of rows outside current session window
		delete from ajwp_session_history where ash_date_time < (item.event_date_time - 10/24/60);
		commit;
		
		-- Important!
		my_session_id := null;
		
		-- Handle search or supplier impression
		if item.row_type = 'SEARCH' or item.row_type = 'SUPPLIER' then
		
			-- Require both ip and browser (at least)
			if item.ip is not null and item.browser is not null then
				
				-- Prepare stub sql that matches on ip and browser
				matchIpAndBrowser := 'ash_ip = ''' || item.ip || ''' and ash_browser = ''' || item.browser || '''';
			
				-- Search on ip, browser & user
				if item.usr_user_code is not null then
				
					-- Match user by value
					my_session_id := ajwp_read_sess_hist
					(
						matchIpAndBrowser || ' and ash_usr = ' || item.usr_user_code
					);
				else
				
					-- Match user when null (anonymous session)
					my_session_id := ajwp_read_sess_hist
					(
						matchIpAndBrowser || ' and ash_usr is null'
					);
				end if;
				
				-- If no result, maybe user logged in/out since last event?
				-- Try again to catch this scenario.
				if my_session_id is null then
					
					-- if usr is null, check for not null
					if item.usr_user_code is null then
						
						my_session_id := ajwp_read_sess_hist
						(
							matchIpAndBrowser || ' and ash_usr is not null'
						);
						
					-- if usr is not null, check for null
					else
					
						-- try again on ip, browser and usr is null
						my_session_id := ajwp_read_sess_hist
						(
							matchIpAndBrowser || ' and ash_usr is null'
						);
					end if;
				end if;
			end if;
		
		-- Handle inquiry separately because ip and browser are always null (not recorded)
		elsif item.row_type = 'INQUIRY' then
		
			-- Search ignoring ip & browser, but matching on user
			-- NB if user is null, no match (even with nulls) guaranteed by '= null'
			my_session_id := ajwp_read_sess_hist
			(
				'ash_usr = ' || item.usr_user_code
			);
			
		end if; -- End detect existing session
		
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
		
	end loop;
END AJWP_DERIVE_SESSIONS;
