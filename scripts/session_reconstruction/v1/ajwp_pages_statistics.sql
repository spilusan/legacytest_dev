CREATE TABLE "SSERVDBA"."AJWP_PAGES_STATISTICS"
  (
    "PST_ID" NUMBER(10,0) NOT NULL ENABLE,
    "PST_SEARCH_DATE_TIME" DATE NOT NULL ENABLE,
    "PST_IP_ADDRESS"             VARCHAR2(90 BYTE),
    "PST_BROWSER"                VARCHAR2(90 BYTE),
    "PST_USR_USER_CODE"          NUMBER(8,0),
    "PST_SEARCH_TEXT"            VARCHAR2(100 BYTE),
    "PST_IS_PRODUCT_SEARCH"      NUMBER(1,0),
    "PST_IS_COMPANY_SEARCH"      NUMBER(1,0),
    "PST_IS_CAT_VIEW_SEARCH"     NUMBER(1,0),
    "PST_COUNTRY"                VARCHAR2(100 BYTE),
    "PST_PORT"                   VARCHAR2(100 BYTE),
    "PST_CATEGORY"               VARCHAR2(200 BYTE),
    "PST_BRAND"                  VARCHAR2(100 BYTE),
    "PST_IS_AUTH_BRAND_SUP"      NUMBER(1,0),
    "PST_IS_SUP_IN_COUNTRY"      NUMBER(1,0),
    "PST_PAGES_MATCHES"          NUMBER(10,0),
    "PST_TRADENET_MATCHES"       NUMBER(10,0),
    "PST_CAT_VIEW_MATCHES"       NUMBER(10,0),
    "PST_IS_TM_VIEWED"           NUMBER(1,0),
    "PST_IS_DONT_DISP_REMINDER"  NUMBER(1,0),
    "PST_IS_PORT_SEARCH_WIDENED" NUMBER(1,0),
    "PST_BRAND_ID"               NUMBER(10,0),
    "PST_COUNTRY_ID"             VARCHAR2(3 BYTE),
    "PST_CATEGORY_ID"            NUMBER(10,0),
    "PST_RESULTS_RETURNED"       NUMBER(10,0),
    "PST_IS_BROWSE_SEARCH"       NUMBER(1,0),
    "PST_IS_SIGNIN_DECLINED"     VARCHAR2(1 BYTE),
    "PST_IS_SURVEY_DECLINED"     VARCHAR2(1 BYTE),
    "PST_SOURCE_OF_SEARCH"       VARCHAR2(50 BYTE),
    "PST_URL_OF_REFERRER"        VARCHAR2(1000 BYTE),
    "PST_APP_VERSION"            VARCHAR2(20 BYTE),
    "PST_USER_AGENT"             VARCHAR2(512 BYTE),
    "PST_GEODATA"                VARCHAR2(4000 BYTE),
    "PST_FULL_QUERY"             VARCHAR2(4000 BYTE),
    "PST_ZONE"                   VARCHAR2(100 BYTE),
    "PST_SESSION_ID"             NUMBER(12,0),
    CONSTRAINT "AJWP_PAGES_STATISTICS_PK" PRIMARY KEY ("PST_ID") USING INDEX PCTFREE 10 INITRANS 2 MAXTRANS 255 COMPUTE STATISTICS STORAGE(INITIAL 131072 NEXT 131072 MINEXTENTS 1 MAXEXTENTS 2147483645 PCTINCREASE 0 FREELISTS 1 FREELIST GROUPS 1 BUFFER_POOL DEFAULT) TABLESPACE "USERS" ENABLE
  )
  PCTFREE 10 PCTUSED 40 INITRANS 1 MAXTRANS 255 NOCOMPRESS LOGGING STORAGE
  (
    INITIAL 131072 NEXT 131072 MINEXTENTS 1 MAXEXTENTS 2147483645 PCTINCREASE 0 FREELISTS 1 FREELIST GROUPS 1 BUFFER_POOL DEFAULT
  )
  TABLESPACE "USERS" ;
CREATE UNIQUE INDEX "SSERVDBA"."AJWP_PAGES_STATISTICS_PK" ON "SSERVDBA"."AJWP_PAGES_STATISTICS"
  (
    "PST_ID"
  )
  PCTFREE 10 INITRANS 2 MAXTRANS 255 COMPUTE STATISTICS STORAGE
  (
    INITIAL 131072 NEXT 131072 MINEXTENTS 1 MAXEXTENTS 2147483645 PCTINCREASE 0 FREELISTS 1 FREELIST GROUPS 1 BUFFER_POOL DEFAULT
  )
  TABLESPACE "USERS" ;
  CREATE INDEX "SSERVDBA"."AJWP_PST_N1" ON "SSERVDBA"."AJWP_PAGES_STATISTICS"
    (
      "PST_SESSION_ID"
    )
    PCTFREE 10 INITRANS 2 MAXTRANS 255 COMPUTE STATISTICS STORAGE
    (
      INITIAL 131072 NEXT 131072 MINEXTENTS 1 MAXEXTENTS 2147483645 PCTINCREASE 0 FREELISTS 1 FREELIST GROUPS 1 BUFFER_POOL DEFAULT
    )
    TABLESPACE "USERS" ;