-- expai (Exponential Intelligence): the tables expai_conversation, expai_draft_log, expai_probe_result and expai_usage_log (sqlite).
-- Generated from expAiSchema::definition() by bin/php/expaischema.php --write-files with the kernel's
-- schema handler; bin/php/expaischema.php --install creates the same tables in a running installation.

CREATE TABLE expai_conversation (
  created INTEGER(11) NOT NULL DEFAULT '0',
  draft_count INTEGER(11) NOT NULL DEFAULT '0',
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  iterations INTEGER(11) NOT NULL DEFAULT '0',
  messages longtext,
  model varchar(100) NOT NULL DEFAULT '',
  model_alias varchar(100) NOT NULL DEFAULT '',
  modified INTEGER(11) NOT NULL DEFAULT '0',
  node_id INTEGER(11) NOT NULL DEFAULT '0',
  provider varchar(40) NOT NULL DEFAULT 'anthropic',
  turn_count INTEGER(11) NOT NULL DEFAULT '0',
  user_id INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX expai_conversation_user_node ON expai_conversation  ( user_id, node_id );
CREATE TABLE expai_usage_log (
  action varchar(40) NOT NULL DEFAULT '',
  cache_read_tokens INTEGER(11) NOT NULL DEFAULT '0',
  cache_write_tokens INTEGER(11) NOT NULL DEFAULT '0',
  conversation_id INTEGER(11) NOT NULL DEFAULT '0',
  created INTEGER(11) NOT NULL DEFAULT '0',
  drafts_written INTEGER(11) NOT NULL DEFAULT '0',
  error_class varchar(60) NOT NULL DEFAULT '',
  error_message varchar(255) NOT NULL DEFAULT '',
  http_status INTEGER(11) NOT NULL DEFAULT '0',
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  input_tokens INTEGER(11) NOT NULL DEFAULT '0',
  iteration INTEGER(11) NOT NULL DEFAULT '0',
  latency_ms INTEGER(11) NOT NULL DEFAULT '0',
  mcp_calls INTEGER(11) NOT NULL DEFAULT '0',
  model varchar(100) NOT NULL DEFAULT '',
  model_alias varchar(100) NOT NULL DEFAULT '',
  node_id INTEGER(11) NOT NULL DEFAULT '0',
  output_tokens INTEGER(11) NOT NULL DEFAULT '0',
  provider varchar(40) NOT NULL DEFAULT 'anthropic',
  rate_limits longtext,
  reasoning_tokens INTEGER(11) NOT NULL DEFAULT '0',
  region varchar(10) NOT NULL DEFAULT '',
  reported_cost_micros INTEGER(11) NOT NULL DEFAULT '0',
  request_debug longtext,
  retention varchar(10) NOT NULL DEFAULT '',
  retries INTEGER(11) NOT NULL DEFAULT '0',
  session_key varchar(40) NOT NULL DEFAULT '',
  stop_reason varchar(30) NOT NULL DEFAULT '',
  tool_calls longtext,
  tool_count INTEGER(11) NOT NULL DEFAULT '0',
  tool_mode varchar(10) NOT NULL DEFAULT '',
  tool_ms INTEGER(11) NOT NULL DEFAULT '0',
  turn INTEGER(11) NOT NULL DEFAULT '0',
  user_id INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX expai_usage_log_created ON expai_usage_log  ( created );
CREATE  INDEX expai_usage_log_provider ON expai_usage_log  ( provider, created );
CREATE  INDEX expai_usage_log_turn ON expai_usage_log  ( conversation_id, turn );
CREATE  INDEX expai_usage_log_user ON expai_usage_log  ( user_id, created );
CREATE TABLE expai_draft_log (
  action varchar(20) NOT NULL DEFAULT '',
  contentobject_id INTEGER(11) NOT NULL DEFAULT '0',
  conversation_id INTEGER(11) NOT NULL DEFAULT '0',
  created INTEGER(11) NOT NULL DEFAULT '0',
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  language_code varchar(20) NOT NULL DEFAULT '',
  model varchar(100) NOT NULL DEFAULT '',
  modified INTEGER(11) NOT NULL DEFAULT '0',
  node_id INTEGER(11) NOT NULL DEFAULT '0',
  parent_node_id INTEGER(11) NOT NULL DEFAULT '0',
  prompt_summary varchar(255) NOT NULL DEFAULT '',
  provider varchar(40) NOT NULL DEFAULT 'anthropic',
  source varchar(20) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'draft',
  user_id INTEGER(11) NOT NULL DEFAULT '0',
  version INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX expai_draft_log_node ON expai_draft_log  ( node_id );
CREATE  INDEX expai_draft_log_object ON expai_draft_log  ( contentobject_id, version );
CREATE  INDEX expai_draft_log_user ON expai_draft_log  ( user_id, status );
CREATE TABLE expai_probe_result (
  created INTEGER(11) NOT NULL DEFAULT '0',
  details longtext,
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  probe_mode varchar(10) NOT NULL DEFAULT '',
  model varchar(100) NOT NULL DEFAULT '',
  model_alias varchar(100) NOT NULL DEFAULT '',
  pass_rate INTEGER(11) NOT NULL DEFAULT '0',
  passed INTEGER(11) NOT NULL DEFAULT '0',
  provider varchar(40) NOT NULL DEFAULT '',
  tasks INTEGER(11) NOT NULL DEFAULT '0',
  tool_mode varchar(10) NOT NULL DEFAULT '',
  user_id INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX expai_probe_result_alias ON expai_probe_result  ( model_alias, created );
