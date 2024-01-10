-- Refresh all tables
drop table ajwp_pages_statistics;
create table ajwp_pages_statistics as select * from pages_statistics where pst_search_date_time >= '06-MAR-10' and pst_search_date_time < '20-MAR-10';
alter table ajwp_pages_statistics add pst_session_id number(19,0);
alter table ajwp_pages_statistics add constraint ajwp_pst_pk primary key (pst_id);
create index ajwp_pst_n1 on ajwp_pages_statistics (pst_session_id);
create index ajwp_pst_n2 on ajwp_pages_statistics (pst_search_text);

drop table ajwp_pages_statistics_supplier;
create table ajwp_pages_statistics_supplier as select * from pages_statistics_supplier where pss_view_date >= '06-MAR-10' and pss_view_date < '20-MAR-10';
alter table ajwp_pages_statistics_supplier add pss_session_id number(19,0);
alter table ajwp_pages_statistics_supplier add constraint ajwp_pss_pk primary key (pss_id);
create index ajwp_pss_n1 on ajwp_pages_statistics_supplier (pss_session_id);

drop table ajwp_pages_inquiry;
create table ajwp_pages_inquiry as select * from pages_inquiry where pin_creation_date >= '06-MAR-10' and pin_creation_date < '20-MAR-10';
alter table ajwp_pages_inquiry add pin_session_id number(19,0);
alter table ajwp_pages_inquiry add constraint ajwp_pin_pk primary key (pin_id);
create index ajwp_pin_n1 on ajwp_pages_inquiry (pin_session_id);

-- Clear all session ids
update ajwp_pages_statistics set pst_session_id = null;
update ajwp_pages_statistics_supplier set pss_session_id = null;
update ajwp_pages_inquiry set pin_session_id = null;
commit;

-- Get sessions that have n distinct action types
select session_id from (
select 'SEARCH' row_type, pst_id row_id, pst_ip_address ip, pst_browser browser, pst_usr_user_code usr_user_code, pst_search_date_time event_date_time, pst_session_id session_id from ajwp_pages_statistics
union all
select 'SUPPLIER', pss_id, pss_viewer_ip_address, pss_browser, pss_usr_user_code, pss_view_date, pss_session_id from ajwp_pages_statistics_supplier
union all
select 'INQUIRY', pin_id, null, null, pin_usr_user_code, pin_creation_date, pin_session_id from ajwp_pages_inquiry
) where session_id is not null group by session_id having count(distinct row_type) = 2;

-- Get events for given session
select to_char(event_date_time, 'yyyy-mm-dd hh24:mi:ss'), a.* from (
select 'SEARCH' row_type, pst_id row_id, pst_ip_address ip, pst_browser browser, pst_usr_user_code usr_user_code, pst_search_date_time event_date_time, pst_session_id session_id from ajwp_pages_statistics
union all
select 'SUPPLIER', pss_id, pss_viewer_ip_address, pss_browser, pss_usr_user_code, pss_view_date, pss_session_id from ajwp_pages_statistics_supplier
union all
select 'INQUIRY', pin_id, null, null, pin_usr_user_code, pin_creation_date, pin_session_id from ajwp_pages_inquiry
) a where session_id = 2402 order by event_date_time;
