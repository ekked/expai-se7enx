-- expai (Exponential Intelligence): expai_draft_log and expai_conversation (mysql).
-- Generated from expAiSchema::definition() by bin/php/expaischema.php --write-files with the kernel's
-- schema handler; bin/php/expaischema.php --install creates the same tables in a running installation.

CREATE TABLE expai_conversation (
  created int(11) NOT NULL DEFAULT '0',
  draft_count int(11) NOT NULL DEFAULT '0',
  id int(11) NOT NULL AUTO_INCREMENT,
  iterations int(11) NOT NULL DEFAULT '0',
  messages longtext,
  model varchar(100) NOT NULL DEFAULT '',
  modified int(11) NOT NULL DEFAULT '0',
  node_id int(11) NOT NULL DEFAULT '0',
  turn_count int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  KEY expai_conversation_user_node ( user_id, node_id )
) ENGINE=InnoDB;

CREATE TABLE expai_usage_log (
  action varchar(40) NOT NULL DEFAULT '',
  cache_read_tokens int(11) NOT NULL DEFAULT '0',
  cache_write_tokens int(11) NOT NULL DEFAULT '0',
  conversation_id int(11) NOT NULL DEFAULT '0',
  created int(11) NOT NULL DEFAULT '0',
  drafts_written int(11) NOT NULL DEFAULT '0',
  error_class varchar(60) NOT NULL DEFAULT '',
  error_message varchar(255) NOT NULL DEFAULT '',
  http_status int(11) NOT NULL DEFAULT '0',
  id int(11) NOT NULL AUTO_INCREMENT,
  input_tokens int(11) NOT NULL DEFAULT '0',
  iteration int(11) NOT NULL DEFAULT '0',
  latency_ms int(11) NOT NULL DEFAULT '0',
  model varchar(100) NOT NULL DEFAULT '',
  node_id int(11) NOT NULL DEFAULT '0',
  output_tokens int(11) NOT NULL DEFAULT '0',
  provider varchar(40) NOT NULL DEFAULT 'anthropic',
  rate_limits longtext,
  request_debug longtext,
  retries int(11) NOT NULL DEFAULT '0',
  session_key varchar(40) NOT NULL DEFAULT '',
  stop_reason varchar(30) NOT NULL DEFAULT '',
  tool_calls longtext,
  tool_count int(11) NOT NULL DEFAULT '0',
  tool_ms int(11) NOT NULL DEFAULT '0',
  turn int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  KEY expai_usage_log_created ( created ),
  KEY expai_usage_log_user ( user_id, created ),
  KEY expai_usage_log_turn ( conversation_id, turn )
) ENGINE=InnoDB;

CREATE TABLE expai_draft_log (
  action varchar(20) NOT NULL DEFAULT '',
  contentobject_id int(11) NOT NULL DEFAULT '0',
  conversation_id int(11) NOT NULL DEFAULT '0',
  created int(11) NOT NULL DEFAULT '0',
  id int(11) NOT NULL AUTO_INCREMENT,
  language_code varchar(20) NOT NULL DEFAULT '',
  model varchar(100) NOT NULL DEFAULT '',
  modified int(11) NOT NULL DEFAULT '0',
  node_id int(11) NOT NULL DEFAULT '0',
  parent_node_id int(11) NOT NULL DEFAULT '0',
  prompt_summary varchar(255) NOT NULL DEFAULT '',
  source varchar(20) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'draft',
  user_id int(11) NOT NULL DEFAULT '0',
  version int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  KEY expai_draft_log_object ( contentobject_id, version ),
  KEY expai_draft_log_user ( user_id, status ),
  KEY expai_draft_log_node ( node_id )
) ENGINE=InnoDB;

