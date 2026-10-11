-- expai (Exponential Intelligence): the tables expai_conversation, expai_draft_log, expai_probe_result and expai_usage_log (postgresql).
-- Generated from expAiSchema::definition() by bin/php/expaischema.php --write-files with the kernel's
-- schema handler; bin/php/expaischema.php --install creates the same tables in a running installation.

CREATE SEQUENCE IF NOT EXISTS expai_conversation_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS expai_conversation (
  created integer DEFAULT 0 NOT NULL,
  draft_count integer DEFAULT 0 NOT NULL,
  id integer DEFAULT nextval('expai_conversation_id_seq'::text) NOT NULL,
  iterations integer DEFAULT 0 NOT NULL,
  messages text,
  model character varying(100) DEFAULT ''::character varying NOT NULL,
  model_alias character varying(100) DEFAULT ''::character varying NOT NULL,
  modified integer DEFAULT 0 NOT NULL,
  node_id integer DEFAULT 0 NOT NULL,
  provider character varying(40) DEFAULT 'anthropic'::character varying NOT NULL,
  turn_count integer DEFAULT 0 NOT NULL,
  user_id integer DEFAULT 0 NOT NULL
);

CREATE INDEX expai_conversation_user_node ON expai_conversation USING btree ( user_id, node_id );
ALTER TABLE ONLY expai_conversation ADD CONSTRAINT expai_conversation_pkey PRIMARY KEY ( id );
CREATE SEQUENCE IF NOT EXISTS expai_usage_log_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS expai_usage_log (
  "action" character varying(40) DEFAULT ''::character varying NOT NULL,
  cache_read_tokens integer DEFAULT 0 NOT NULL,
  cache_write_tokens integer DEFAULT 0 NOT NULL,
  conversation_id integer DEFAULT 0 NOT NULL,
  created integer DEFAULT 0 NOT NULL,
  drafts_written integer DEFAULT 0 NOT NULL,
  error_class character varying(60) DEFAULT ''::character varying NOT NULL,
  error_message character varying(255) DEFAULT ''::character varying NOT NULL,
  fallback_reason character varying(20) DEFAULT ''::character varying NOT NULL,
  http_status integer DEFAULT 0 NOT NULL,
  id integer DEFAULT nextval('expai_usage_log_id_seq'::text) NOT NULL,
  input_tokens integer DEFAULT 0 NOT NULL,
  iteration integer DEFAULT 0 NOT NULL,
  latency_ms integer DEFAULT 0 NOT NULL,
  mcp_calls integer DEFAULT 0 NOT NULL,
  model character varying(100) DEFAULT ''::character varying NOT NULL,
  model_alias character varying(100) DEFAULT ''::character varying NOT NULL,
  node_id integer DEFAULT 0 NOT NULL,
  output_tokens integer DEFAULT 0 NOT NULL,
  provider character varying(40) DEFAULT 'anthropic'::character varying NOT NULL,
  rate_limits text,
  reasoning_tokens integer DEFAULT 0 NOT NULL,
  region character varying(10) DEFAULT ''::character varying NOT NULL,
  reported_cost_micros integer DEFAULT 0 NOT NULL,
  request_debug text,
  requested_model_alias character varying(100) DEFAULT ''::character varying NOT NULL,
  retention character varying(10) DEFAULT ''::character varying NOT NULL,
  retries integer DEFAULT 0 NOT NULL,
  session_key character varying(40) DEFAULT ''::character varying NOT NULL,
  stop_reason character varying(30) DEFAULT ''::character varying NOT NULL,
  tool_calls text,
  tool_count integer DEFAULT 0 NOT NULL,
  tool_mode character varying(10) DEFAULT ''::character varying NOT NULL,
  tool_ms integer DEFAULT 0 NOT NULL,
  turn integer DEFAULT 0 NOT NULL,
  user_id integer DEFAULT 0 NOT NULL
);

CREATE INDEX expai_usage_log_created ON expai_usage_log USING btree ( created );
CREATE INDEX expai_usage_log_provider ON expai_usage_log USING btree ( provider, created );
CREATE INDEX expai_usage_log_user ON expai_usage_log USING btree ( user_id, created );
CREATE INDEX expai_usage_log_turn ON expai_usage_log USING btree ( conversation_id, turn );
ALTER TABLE ONLY expai_usage_log ADD CONSTRAINT expai_usage_log_pkey PRIMARY KEY ( id );
CREATE SEQUENCE IF NOT EXISTS expai_draft_log_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS expai_draft_log (
  "action" character varying(20) DEFAULT ''::character varying NOT NULL,
  contentobject_id integer DEFAULT 0 NOT NULL,
  conversation_id integer DEFAULT 0 NOT NULL,
  created integer DEFAULT 0 NOT NULL,
  id integer DEFAULT nextval('expai_draft_log_id_seq'::text) NOT NULL,
  language_code character varying(20) DEFAULT ''::character varying NOT NULL,
  model character varying(100) DEFAULT ''::character varying NOT NULL,
  modified integer DEFAULT 0 NOT NULL,
  node_id integer DEFAULT 0 NOT NULL,
  parent_node_id integer DEFAULT 0 NOT NULL,
  prompt_summary character varying(255) DEFAULT ''::character varying NOT NULL,
  provider character varying(40) DEFAULT 'anthropic'::character varying NOT NULL,
  source character varying(20) DEFAULT ''::character varying NOT NULL,
  status character varying(20) DEFAULT 'draft'::character varying NOT NULL,
  user_id integer DEFAULT 0 NOT NULL,
  "version" integer DEFAULT 0 NOT NULL
);

CREATE INDEX expai_draft_log_object ON expai_draft_log USING btree ( contentobject_id, "version" );
CREATE INDEX expai_draft_log_user ON expai_draft_log USING btree ( user_id, status );
CREATE INDEX expai_draft_log_node ON expai_draft_log USING btree ( node_id );
ALTER TABLE ONLY expai_draft_log ADD CONSTRAINT expai_draft_log_pkey PRIMARY KEY ( id );
CREATE SEQUENCE IF NOT EXISTS expai_probe_result_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS expai_probe_result (
  created integer DEFAULT 0 NOT NULL,
  details text,
  id integer DEFAULT nextval('expai_probe_result_id_seq'::text) NOT NULL,
  probe_mode character varying(10) DEFAULT ''::character varying NOT NULL,
  model character varying(100) DEFAULT ''::character varying NOT NULL,
  model_alias character varying(100) DEFAULT ''::character varying NOT NULL,
  pass_rate integer DEFAULT 0 NOT NULL,
  passed integer DEFAULT 0 NOT NULL,
  provider character varying(40) DEFAULT ''::character varying NOT NULL,
  tasks integer DEFAULT 0 NOT NULL,
  tool_mode character varying(10) DEFAULT ''::character varying NOT NULL,
  user_id integer DEFAULT 0 NOT NULL
);

CREATE INDEX expai_probe_result_alias ON expai_probe_result USING btree ( model_alias, created );
ALTER TABLE ONLY expai_probe_result ADD CONSTRAINT expai_probe_result_pkey PRIMARY KEY ( id );
