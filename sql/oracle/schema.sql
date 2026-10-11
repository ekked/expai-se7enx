-- expai (Exponential Intelligence): the tables expai_conversation, expai_draft_log, expai_probe_result and expai_usage_log (oracle).
-- Generated from expAiSchema::definition() by bin/php/expaischema.php --write-files with the kernel's
-- schema handler; bin/php/expaischema.php --install creates the same tables in a running installation.

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_expai_conversation';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE expai_conversation (
  created INTEGER DEFAULT 0 NOT NULL,
  draft_count INTEGER DEFAULT 0 NOT NULL,
  id INTEGER NOT NULL,
  iterations INTEGER DEFAULT 0 NOT NULL,
  messages CLOB,
  model VARCHAR2(100),
  model_alias VARCHAR2(100),
  modified INTEGER DEFAULT 0 NOT NULL,
  node_id INTEGER DEFAULT 0 NOT NULL,
  provider VARCHAR2(40) DEFAULT 'anthropic',
  turn_count INTEGER DEFAULT 0 NOT NULL,
  user_id INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER expai_conversation_id_tr
BEFORE INSERT ON expai_conversation FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_expai_conversation.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX expai_conversation_user_node ON expai_conversation ( user_id, node_id );
BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_expai_usage_log';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE expai_usage_log (
  action VARCHAR2(40),
  cache_read_tokens INTEGER DEFAULT 0 NOT NULL,
  cache_write_tokens INTEGER DEFAULT 0 NOT NULL,
  conversation_id INTEGER DEFAULT 0 NOT NULL,
  created INTEGER DEFAULT 0 NOT NULL,
  drafts_written INTEGER DEFAULT 0 NOT NULL,
  error_class VARCHAR2(60),
  error_message VARCHAR2(255),
  fallback_reason VARCHAR2(20),
  http_status INTEGER DEFAULT 0 NOT NULL,
  id INTEGER NOT NULL,
  input_tokens INTEGER DEFAULT 0 NOT NULL,
  iteration INTEGER DEFAULT 0 NOT NULL,
  latency_ms INTEGER DEFAULT 0 NOT NULL,
  mcp_calls INTEGER DEFAULT 0 NOT NULL,
  model VARCHAR2(100),
  model_alias VARCHAR2(100),
  node_id INTEGER DEFAULT 0 NOT NULL,
  output_tokens INTEGER DEFAULT 0 NOT NULL,
  provider VARCHAR2(40) DEFAULT 'anthropic',
  rate_limits CLOB,
  reasoning_tokens INTEGER DEFAULT 0 NOT NULL,
  region VARCHAR2(10),
  reported_cost_micros INTEGER DEFAULT 0 NOT NULL,
  request_debug CLOB,
  requested_model_alias VARCHAR2(100),
  retention VARCHAR2(10),
  retries INTEGER DEFAULT 0 NOT NULL,
  session_key VARCHAR2(40),
  stop_reason VARCHAR2(30),
  tool_calls CLOB,
  tool_count INTEGER DEFAULT 0 NOT NULL,
  tool_mode VARCHAR2(10),
  tool_ms INTEGER DEFAULT 0 NOT NULL,
  turn INTEGER DEFAULT 0 NOT NULL,
  user_id INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER expai_usage_log_id_tr
BEFORE INSERT ON expai_usage_log FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_expai_usage_log.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX expai_usage_log_created ON expai_usage_log ( created );
CREATE INDEX expai_usage_log_provider ON expai_usage_log ( provider, created );
CREATE INDEX expai_usage_log_turn ON expai_usage_log ( conversation_id, turn );
CREATE INDEX expai_usage_log_user ON expai_usage_log ( user_id, created );
BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_expai_draft_log';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE expai_draft_log (
  action VARCHAR2(20),
  contentobject_id INTEGER DEFAULT 0 NOT NULL,
  conversation_id INTEGER DEFAULT 0 NOT NULL,
  created INTEGER DEFAULT 0 NOT NULL,
  id INTEGER NOT NULL,
  language_code VARCHAR2(20),
  model VARCHAR2(100),
  modified INTEGER DEFAULT 0 NOT NULL,
  node_id INTEGER DEFAULT 0 NOT NULL,
  parent_node_id INTEGER DEFAULT 0 NOT NULL,
  prompt_summary VARCHAR2(255),
  provider VARCHAR2(40) DEFAULT 'anthropic',
  source VARCHAR2(20),
  status VARCHAR2(20) DEFAULT 'draft',
  user_id INTEGER DEFAULT 0 NOT NULL,
  version INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER expai_draft_log_id_tr
BEFORE INSERT ON expai_draft_log FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_expai_draft_log.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX expai_draft_log_node ON expai_draft_log ( node_id );
CREATE INDEX expai_draft_log_object ON expai_draft_log ( contentobject_id, version );
CREATE INDEX expai_draft_log_user ON expai_draft_log ( user_id, status );
BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_expai_probe_result';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE expai_probe_result (
  created INTEGER DEFAULT 0 NOT NULL,
  details CLOB,
  id INTEGER NOT NULL,
  probe_mode VARCHAR2(10),
  model VARCHAR2(100),
  model_alias VARCHAR2(100),
  pass_rate INTEGER DEFAULT 0 NOT NULL,
  passed INTEGER DEFAULT 0 NOT NULL,
  provider VARCHAR2(40),
  tasks INTEGER DEFAULT 0 NOT NULL,
  tool_mode VARCHAR2(10),
  user_id INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER expai_probe_result_id_tr
BEFORE INSERT ON expai_probe_result FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_expai_probe_result.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX expai_probe_result_alias ON expai_probe_result ( model_alias, created );
