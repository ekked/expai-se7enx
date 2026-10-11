<?php
/**
 * File containing the expAiUsageLog class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * One request to the model (table expai_usage_log): when, for whom (user id, never a name), where (node, conversation,
 * turn), what for (action), the model, the token usage as the API reported it, the stop reason, HTTP status,
 * latency, the tool-loop iteration, the tool calls of that step (names, duration, success), retries, the error (class
 * and message, never the key) and the drafts written. The request itself is stored only with
 * [UsageSettings] LogRequests=enabled.
 */
class expAiUsageLog extends eZPersistentObject
{
    public static function definition()
    {
        $int = function ( $name ) { return array( 'name' => $name, 'datatype' => 'integer', 'default' => 0, 'required' => true ); };
        $str = function ( $name ) { return array( 'name' => $name, 'datatype' => 'string', 'default' => '', 'required' => true ); };
        return array(
            'fields' => array(
                'id' => $int( 'ID' ),
                'created' => $int( 'Created' ),
                'user_id' => $int( 'UserID' ),
                'node_id' => $int( 'NodeID' ),
                'conversation_id' => $int( 'ConversationID' ),
                'turn' => $int( 'Turn' ),
                'action' => $str( 'Action' ),
                'model' => $str( 'Model' ),
                'input_tokens' => $int( 'InputTokens' ),
                'output_tokens' => $int( 'OutputTokens' ),
                'provider' => array( 'name' => 'Provider', 'datatype' => 'string', 'default' => 'anthropic', 'required' => true ),
                'cache_write_tokens' => $int( 'CacheWriteTokens' ),
                'cache_read_tokens' => $int( 'CacheReadTokens' ),
                'stop_reason' => $str( 'StopReason' ),
                'http_status' => $int( 'HttpStatus' ),
                'latency_ms' => $int( 'LatencyMs' ),
                'iteration' => $int( 'Iteration' ),
                'tool_calls' => $str( 'ToolCalls' ),
                'tool_count' => $int( 'ToolCount' ),
                'tool_ms' => $int( 'ToolMs' ),
                'retries' => $int( 'Retries' ),
                'error_class' => $str( 'ErrorClass' ),
                'error_message' => $str( 'ErrorMessage' ),
                'drafts_written' => $int( 'DraftsWritten' ),
                'session_key' => $str( 'SessionKey' ),
                'rate_limits' => $str( 'RateLimits' ),
                'request_debug' => $str( 'RequestDebug' ),
            ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'function_attributes' => array( 'tools' => 'tools', 'cost' => 'cost', 'total_tokens' => 'totalTokens' ),
            'class_name' => 'expAiUsageLog',
            'sort' => array( 'id' => 'desc' ),
            'name' => 'expai_usage_log',
        );
    }

    /** @return array the tool calls: list of { name, ms, ok } */
    public function tools()
    {
        $list = json_decode( (string) $this->attribute( 'tool_calls' ), true );
        return is_array( $list ) ? $list : array();
    }

    /** @return int input + output + cache write + cache read */
    public function totalTokens()
    {
        return (int) $this->attribute( 'input_tokens' ) + (int) $this->attribute( 'output_tokens' )
             + (int) $this->attribute( 'cache_write_tokens' ) + (int) $this->attribute( 'cache_read_tokens' );
    }

    /** @return float|null the estimated cost, null when the prices of the model are not configured */
    public function cost()
    {
        return expAiUsage::cost( $this->attribute( 'model' ), $this->attribute( 'input_tokens' ), $this->attribute( 'output_tokens' ),
                                 $this->attribute( 'cache_write_tokens' ), $this->attribute( 'cache_read_tokens' ) );
    }
}
