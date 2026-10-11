-- expai (Exponential Intelligence): expai_draft_log and expai_conversation (sqlite).
-- Generated from expAiSchema::definition() by bin/php/expaischema.php --write-files with the kernel's
-- schema handler; bin/php/expaischema.php --install creates the same tables in a running installation.

CREATE TABLE expai_conversation (
  created INTEGER(11) NOT NULL DEFAULT '0',
  draft_count INTEGER(11) NOT NULL DEFAULT '0',
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  iterations INTEGER(11) NOT NULL DEFAULT '0',
  messages longtext,
  model varchar(100) NOT NULL DEFAULT '',
  modified INTEGER(11) NOT NULL DEFAULT '0',
  node_id INTEGER(11) NOT NULL DEFAULT '0',
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
  model varchar(100) NOT NULL DEFAULT '',
  node_id INTEGER(11) NOT NULL DEFAULT '0',
  output_tokens INTEGER(11) NOT NULL DEFAULT '0',
  provider varchar(40) NOT NULL DEFAULT 'anthropic',
  rate_limits longtext,
  request_debug longtext,
  retries INTEGER(11) NOT NULL DEFAULT '0',
  session_key varchar(40) NOT NULL DEFAULT '',
  stop_reason varchar(30) NOT NULL DEFAULT '',
  tool_calls longtext,
  tool_count INTEGER(11) NOT NULL DEFAULT '0',
  tool_ms INTEGER(11) NOT NULL DEFAULT '0',
  turn INTEGER(11) NOT NULL DEFAULT '0',
  user_id INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX expai_usage_log_created ON expai_usage_log  ( created );
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
  source varchar(20) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'draft',
  user_id INTEGER(11) NOT NULL DEFAULT '0',
  version INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX expai_draft_log_node ON expai_draft_log  ( node_id );
CREATE  INDEX expai_draft_log_object ON expai_draft_log  ( contentobject_id, version );
CREATE  INDEX expai_draft_log_user ON expai_draft_log  ( user_id, status );
