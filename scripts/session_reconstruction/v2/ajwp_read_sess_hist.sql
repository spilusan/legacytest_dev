create or replace function ajwp_read_sess_hist(where_sql in varchar2)
return number is
	my_sql varchar2(255);
	my_session_id number;
begin
	my_sql :=
		'select ash_session_id from
		(
			select rownum rn, a.* from ajwp_session_history a
			where
			
				' || where_sql || '
				
			-- Order by most recent first
			order by ash_date_time desc
			
		-- Just the 1st one
		) where rn <= 1';
	
	execute immediate my_sql into my_session_id;
	return my_session_id;
exception
	when NO_DATA_FOUND then
		return null;
end ajwp_read_sess_hist;
