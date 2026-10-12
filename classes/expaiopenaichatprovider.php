<?php
/**
 * File containing the expAiOpenAiChatProvider class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The OpenAI Chat Completions API and every server that speaks it ([Provider_<id>] Type=openai_chat): OpenAI,
 * Hugging Face Inference Providers (router), OpenRouter, Mistral, xAI, Ollama, llama.cpp llama-server, vLLM ...
 * It translates expai's Anthropic-shaped requests and answers both ways:
 *
 *   system                      -> the first message { role: system }
 *   tool_result blocks          -> one { role: tool, tool_call_id, content } each (Mistral: with name)
 *   assistant tool_use blocks   -> { role: assistant, content, tool_calls: [ { id, type: function, function: { name, arguments } } ] }
 *   tools                       -> [ { type: function, function: { name, description, parameters } } ]
 *   tool_choice none / any      -> "none" / "required" (Mistral "any"); servers without tool_choice (Ollama) get no
 *                                  tools on a call that must answer in words
 *   output_schema               -> response_format json_schema (Capabilities[]=json_schema) or json_object
 *   image blocks (base64)       -> user content parts { type: image_url, image_url: { url: data:<type>;base64,... } }
 *                                  (vision models, Capabilities[]=vision; doc/alt-text.md)
 *
 * The answer's message becomes text and tool_use blocks (arguments decoded; arguments that are not valid JSON are
 * reported in invalid_tool_input, and the assistant answers the call with an error asking for valid JSON),
 * finish_reason becomes stop_reason (tool_calls -> tool_use, length -> max_tokens, stop -> end_turn,
 * content_filter -> refusal), usage becomes the normalised tokens (cached tokens are taken out of the input: they
 * are counted as cache_read, as Anthropic reports them) with reasoning tokens and a cost the provider reported
 * (OpenRouter usage.cost). The message as received is kept as native_json and sent back unchanged to the same
 * provider and model (reasoning fields of some providers must be returned).
 *
 * [Provider_<id>] Flavor=openai | huggingface | openrouter | mistral | xai | ollama | llamacpp | generic sets the
 * small differences; everything else comes from the settings (see doc/providers.md).
 */
class expAiOpenAiChatProvider extends expAiProviderBase
{
    /** @var string[] */
    protected $defaultRateLimitHeaders = array( 'x-ratelimit-*', 'retry-after', 'x-request-id' );

    protected function defaultProviderId()
    {
        return 'openai_chat';
    }

    /** @return string the flavor: the small differences of a server */
    public function flavor()
    {
        $flavor = strtolower( trim( (string) $this->setting( 'Flavor', 'generic' ) ) );
        return in_array( $flavor, array( 'openai', 'huggingface', 'openrouter', 'mistral', 'xai', 'ollama', 'llamacpp', 'generic' ), true ) ? $flavor : 'generic';
    }

    /** @return string the chat completions URL from [Provider_<id>] BaseURL */
    public function endpoint()
    {
        $url = rtrim( trim( (string) $this->setting( 'BaseURL', $this->setting( 'Endpoint', '' ) ) ) );
        if ( $url === '' )
            return '';
        $query = '';
        if ( ( $pos = strpos( $url, '?' ) ) !== false )
        {
            $query = substr( $url, $pos );
            $url = substr( $url, 0, $pos );
        }
        $url = rtrim( $url, '/' );
        if ( !preg_match( '~/chat/completions$~', $url ) )
            $url .= '/chat/completions';
        return $url . $query;
    }

    /** @return bool the server accepts tool_choice */
    public function supportsToolChoice()
    {
        if ( $this->hasCapability( 'no_tool_choice' ) )
            return false;
        return $this->flavor() !== 'ollama';
    }

    /** @return mixed a field of a tool definition (array or object, as frames are stored) */
    protected static function field( $item, $name, $default = null )
    {
        if ( is_array( $item ) )
            return array_key_exists( $name, $item ) ? $item[$name] : $default;
        if ( is_object( $item ) )
            return property_exists( $item, $name ) ? $item->$name : $default;
        return $default;
    }

    /** @return array the blocks of a stored message (content_json decoded, or content) */
    protected static function blocks( array $message )
    {
        if ( isset( $message['content_json'] ) )
        {
            $blocks = json_decode( (string) $message['content_json'], true );
            return is_array( $blocks ) ? $blocks : array();
        }
        if ( !isset( $message['content'] ) )
            return array();
        if ( is_string( $message['content'] ) )
            return array( array( 'type' => 'text', 'text' => $message['content'] ) );
        return is_array( $message['content'] ) ? $message['content'] : array();
    }

    /** @return string the key of the provider and model a native_json belongs to */
    public function nativeKey()
    {
        return $this->providerId() . '/' . $this->modelName();
    }

    /** @return array the request body (exposed for tests; contains no key) */
    public function buildPayload( array $request )
    {
        $flavor = $this->flavor();
        $messages = array();
        if ( isset( $request['system'] ) && (string) $request['system'] !== '' )
            $messages[] = array( 'role' => 'system', 'content' => (string) $request['system'] );
        $toolNames = array();
        foreach ( $request['messages'] as $message )
        {
            $role = isset( $message['role'] ) ? (string) $message['role'] : 'user';
            if ( $role === 'assistant' )
            {
                if ( isset( $message['native_json'], $message['native_for'] ) && $message['native_for'] === $this->nativeKey() )
                {
                    $native = json_decode( (string) $message['native_json'], true );
                    if ( is_array( $native ) )
                    {
                        $native['role'] = 'assistant';
                        foreach ( isset( $native['tool_calls'] ) && is_array( $native['tool_calls'] ) ? $native['tool_calls'] : array() as $call )
                            if ( isset( $call['id'], $call['function']['name'] ) )
                                $toolNames[(string) $call['id']] = (string) $call['function']['name'];
                        // the ids the assistant generated for calls without one are the ids of the tool results
                        if ( isset( $native['tool_calls'] ) && is_array( $native['tool_calls'] ) )
                            foreach ( $native['tool_calls'] as $i => $call )
                            {
                                if ( empty( $call['id'] ) && isset( $message['native_ids'][$i] ) )
                                    $native['tool_calls'][$i]['id'] = $message['native_ids'][$i];
                                // arguments that were not JSON (the call was answered with an error) go back as {}
                                $arguments = isset( $call['function']['arguments'] ) ? $call['function']['arguments'] : '{}';
                                if ( is_array( $arguments ) )
                                    $native['tool_calls'][$i]['function']['arguments'] = json_encode( $arguments ?: new stdClass() );
                                elseif ( !is_array( json_decode( (string) $arguments, true ) ) )
                                    $native['tool_calls'][$i]['function']['arguments'] = '{}';
                            }
                        unset( $native['refusal'], $native['annotations'] );
                        if ( !isset( $native['content'] ) )
                            $native['content'] = isset( $native['tool_calls'] ) ? null : '';
                        $messages[] = $native;
                        continue;
                    }
                }
                $text = '';
                $calls = array();
                foreach ( self::blocks( $message ) as $block )
                {
                    $type = isset( $block['type'] ) ? $block['type'] : '';
                    if ( $type === 'text' && isset( $block['text'] ) )
                        $text .= ( $text !== '' ? "\n\n" : '' ) . $block['text'];
                    elseif ( $type === 'tool_use' )
                    {
                        $input = isset( $block['input'] ) && $block['input'] !== array() ? $block['input'] : new stdClass();
                        $calls[] = array( 'id' => (string) $block['id'], 'type' => 'function',
                                          'function' => array( 'name' => (string) $block['name'],
                                                               'arguments' => json_encode( $input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
                        $toolNames[(string) $block['id']] = (string) $block['name'];
                    }
                    // thinking blocks of another provider are not sent
                }
                $out = array( 'role' => 'assistant', 'content' => $calls && $text === '' ? null : $text );
                if ( $calls )
                    $out['tool_calls'] = $calls;
                $messages[] = $out;
                continue;
            }
            // user: tool results first (they answer the calls of the assistant message before), then the text
            $text = '';
            // images (alt text, doc/alt-text.md): the canonical base64 image block becomes an image_url part with a data URL
            $images = array();
            foreach ( self::blocks( $message ) as $block )
                if ( isset( $block['type'], $block['source']['data'], $block['source']['media_type'] ) && $block['type'] === 'image' )
                    $images[] = array( 'type' => 'image_url', 'image_url' => array( 'url' => 'data:' . $block['source']['media_type'] . ';base64,' . $block['source']['data'] ) );
            foreach ( self::blocks( $message ) as $block )
            {
                $type = isset( $block['type'] ) ? $block['type'] : '';
                if ( $type === 'tool_result' )
                {
                    $content = isset( $block['content'] ) ? $block['content'] : '';
                    if ( is_array( $content ) )
                    {
                        $parts = '';
                        foreach ( $content as $part )
                            if ( isset( $part['text'] ) )
                                $parts .= $part['text'];
                        $content = $parts;
                    }
                    $content = (string) $content;
                    if ( !empty( $block['is_error'] ) )
                        $content = 'Error: ' . $content;
                    $id = (string) $block['tool_use_id'];
                    $tool = array( 'role' => 'tool', 'tool_call_id' => $id, 'content' => $content );
                    if ( $flavor === 'mistral' && isset( $toolNames[$id] ) )
                        $tool['name'] = $toolNames[$id];
                    $messages[] = $tool;
                }
                elseif ( $type === 'text' && isset( $block['text'] ) )
                    $text .= ( $text !== '' ? "\n\n" : '' ) . $block['text'];
            }
            if ( $images )
                $messages[] = array( 'role' => 'user', 'content' => array_merge( $images, $text !== '' ? array( array( 'type' => 'text', 'text' => $text ) ) : array() ) );
            elseif ( $text !== '' )
                $messages[] = array( 'role' => 'user', 'content' => $text );
        }

        $payload = array( 'model' => $this->modelName(), 'messages' => $messages );
        $maxTokens = (int) ( isset( $request['max_tokens'] ) ? $request['max_tokens'] : $this->setting( 'MaxTokens', 8000 ) );
        $param = (string) $this->setting( 'MaxTokensParameter', $flavor === 'openai' ? 'max_completion_tokens' : 'max_tokens' );
        if ( $maxTokens > 0 && in_array( $param, array( 'max_tokens', 'max_completion_tokens' ), true ) )
            $payload[$param] = $maxTokens;

        $choice = isset( $request['tool_choice'] ) ? self::field( $request['tool_choice'], 'type', '' ) : '';
        $tools = !empty( $request['tools'] ) ? (array) $request['tools'] : array();
        // a call that must answer in words, on a server without tool_choice: no tools at all
        if ( $choice === 'none' && !$this->supportsToolChoice() )
            $tools = array();
        if ( $tools )
        {
            $payload['tools'] = array();
            foreach ( $tools as $tool )
            {
                $function = array( 'name' => (string) self::field( $tool, 'name', '' ), 'description' => (string) self::field( $tool, 'description', '' ),
                                   'parameters' => self::field( $tool, 'input_schema', new stdClass() ) );
                if ( $this->enabled( 'StrictTools', 'disabled' ) )
                    $function['strict'] = true;
                $payload['tools'][] = array( 'type' => 'function', 'function' => $function );
            }
            if ( $choice !== '' && $this->supportsToolChoice() )
            {
                if ( $choice === 'none' || $choice === 'auto' )
                    $payload['tool_choice'] = $choice;
                elseif ( $choice === 'any' )
                    $payload['tool_choice'] = $flavor === 'mistral' ? 'any' : 'required';
                elseif ( $choice === 'tool' )
                    $payload['tool_choice'] = array( 'type' => 'function', 'function' => array( 'name' => (string) self::field( $request['tool_choice'], 'name', '' ) ) );
            }
            $parallel = strtolower( (string) $this->setting( 'ParallelToolCalls', '' ) );
            if ( in_array( $parallel, array( 'enabled', 'disabled' ), true ) )
                $payload['parallel_tool_calls'] = $parallel === 'enabled';
        }

        if ( !empty( $request['output_schema'] ) )
        {
            if ( $this->hasCapability( 'json_schema' ) )
                $payload['response_format'] = array( 'type' => 'json_schema', 'json_schema' => array( 'name' => 'answer', 'schema' => $request['output_schema'], 'strict' => false ) );
            elseif ( $this->hasCapability( 'json_object' ) )
                $payload['response_format'] = array( 'type' => 'json_object' );
        }

        $effort = strtolower( trim( (string) $this->setting( 'Effort', '' ) ) );
        if ( $effort !== '' && $this->hasCapability( 'reasoning_effort' ) && in_array( $effort, array( 'none', 'minimal', 'low', 'medium', 'high', 'xhigh' ), true ) )
        {
            if ( $flavor === 'openrouter' )
                $payload['reasoning'] = array( 'effort' => $effort === 'none' ? 'minimal' : $effort ) + ( $effort === 'none' ? array( 'exclude' => true ) : array() );
            else
                $payload['reasoning_effort'] = $effort;
        }
        $payload['stream'] = false;

        // #377 Ollama: KeepAlive (an Ollama duration such as 30m, or seconds) keeps the model and its cached prompt
        // prefix in memory after the request, so the next turn of an active thread does not load and read it again
        $keep = trim( (string) $this->setting( 'KeepAlive', '' ) );
        if ( $flavor === 'ollama' && preg_match( '/^\d{1,5}[smh]?$/', $keep ) )
            $payload['keep_alive'] = ctype_digit( $keep ) ? (int) $keep : $keep;

        // [Provider_<id>] / [Model_<alias>] ExtraBody: a JSON object merged into the body (e.g. OpenRouter provider routing)
        $extra = trim( (string) $this->setting( 'ExtraBody', '' ) );
        if ( $extra !== '' )
        {
            $decoded = json_decode( $extra, true );
            if ( is_array( $decoded ) )
            {
                unset( $decoded['model'], $decoded['messages'], $decoded['tools'] );
                $payload = array_replace_recursive( $payload, $decoded );
            }
            else
                eZDebug::writeWarning( 'expai: ExtraBody of provider ' . $this->providerId() . ' is not a JSON object', __METHOD__ );
        }
        return $payload;
    }

    public function send( array $request )
    {
        $endpoint = $this->endpoint();
        if ( $endpoint === '' )
            throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'BaseURL of provider ' . $this->providerId() . ' is not set' );
        if ( $this->modelName() === '' )
            throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'Model is not set' );
        $headers = array_merge( $this->keyHeaders( 'authorization' ), $this->extraHeaders() );
        $payload = $this->buildPayload( $request );
        list( $status, $received, $rateHeaders, $retries ) = $this->post( $endpoint, $headers, $this->encode( $payload ) );
        $data = json_decode( $received, true );
        if ( $status < 200 || $status >= 300 )
            throw $this->httpError( $status, $data, $rateHeaders, $retries );
        $answer = $this->parseAnswer( $data, $status );
        $answer['retries'] = $retries;
        $answer['rate_limits'] = $rateHeaders;
        return $answer;
    }

    /**
     * The expai answer of a Chat Completions response.
     * @param array|mixed $data the decoded response
     * @return array
     * @throws expAiException
     */
    public function parseAnswer( $data, $status = 200 )
    {
        if ( !is_array( $data ) || !isset( $data['choices'][0] ) || !is_array( $data['choices'][0] ) )
        {
            // OpenRouter answers some failures with HTTP 200 and an error object
            if ( is_array( $data ) && isset( $data['error'] ) )
                throw $this->httpError( isset( $data['error']['code'] ) && is_numeric( $data['error']['code'] ) ? (int) $data['error']['code'] : 502, $data, array() );
            throw new expAiException( 'The answer of %provider could not be read.', 'provider ' . $this->providerId() . ': HTTP ' . $status . ', no choices[]', $status,
                                      array( '%provider' => $this->providerLabel() ) );
        }
        $choice = $data['choices'][0];
        if ( isset( $choice['error'] ) && is_array( $choice['error'] ) )
            throw $this->httpError( isset( $choice['error']['code'] ) && is_numeric( $choice['error']['code'] ) ? (int) $choice['error']['code'] : 502,
                                    array( 'error' => $choice['error'] ), array() );
        $message = isset( $choice['message'] ) && is_array( $choice['message'] ) ? $choice['message'] : array();
        $content = array();
        $text = '';
        if ( isset( $message['content'] ) )
        {
            if ( is_string( $message['content'] ) )
                $text = $message['content'];
            elseif ( is_array( $message['content'] ) )
                foreach ( $message['content'] as $part )
                    if ( is_array( $part ) && isset( $part['text'] ) && ( !isset( $part['type'] ) || $part['type'] === 'text' ) )
                        $text .= $part['text'];
        }
        // some local models write their reasoning into the text: <think>...</think> is not part of the answer
        $text = trim( preg_replace( '~<think>.*?</think>~s', '', $text ) );
        if ( $text !== '' )
            $content[] = array( 'type' => 'text', 'text' => $text );
        $invalid = array();
        $generated = array();
        $calls = isset( $message['tool_calls'] ) && is_array( $message['tool_calls'] ) ? $message['tool_calls'] : array();
        foreach ( array_values( $calls ) as $i => $call )
        {
            if ( !is_array( $call ) || !isset( $call['function'] ) || !is_array( $call['function'] ) )
                continue;
            $name = isset( $call['function']['name'] ) ? (string) $call['function']['name'] : '';
            $id = isset( $call['id'] ) && is_scalar( $call['id'] ) && (string) $call['id'] !== '' ? (string) $call['id'] : '';
            if ( $id === '' )
            {
                $id = $this->generateId( $i );
                $generated[$i] = $id;
            }
            $arguments = isset( $call['function']['arguments'] ) ? $call['function']['arguments'] : '';
            if ( is_array( $arguments ) )
                $input = $arguments;
            elseif ( trim( (string) $arguments ) === '' )
                $input = array();
            else
            {
                $input = json_decode( (string) $arguments, true );
                if ( !is_array( $input ) || ( $input && array_keys( $input ) === range( 0, count( $input ) - 1 ) ) )
                {
                    $invalid[$id] = expAiText::cut( (string) $arguments, 300 );
                    $input = array();
                }
            }
            $content[] = array( 'type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input );
        }
        $finish = isset( $choice['finish_reason'] ) ? (string) $choice['finish_reason'] : '';
        $map = array( 'tool_calls' => 'tool_use', 'function_call' => 'tool_use', 'length' => 'max_tokens', 'stop' => 'end_turn',
                      'content_filter' => 'refusal', 'eos' => 'end_turn', 'end_turn' => 'end_turn' );
        $stop = isset( $map[$finish] ) ? $map[$finish] : ( $finish === '' ? 'end_turn' : $finish );
        if ( $finish === 'error' )
            throw $this->httpError( 502, array( 'error' => array( 'message' => 'finish_reason error' ) ), array() );
        // Ollama and others end a turn with tool calls with "stop"
        $hasCalls = (bool) array_filter( $content, function ( $b ) { return $b['type'] === 'tool_use'; } );
        if ( $hasCalls && $stop === 'end_turn' )
            $stop = 'tool_use';

        $usage = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array();
        $prompt = isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : 0;
        $cacheRead = isset( $usage['prompt_tokens_details']['cached_tokens'] ) ? (int) $usage['prompt_tokens_details']['cached_tokens'] : 0;
        $cacheWrite = isset( $usage['prompt_tokens_details']['cache_write_tokens'] ) ? (int) $usage['prompt_tokens_details']['cache_write_tokens'] : 0;
        $reasoning = isset( $usage['completion_tokens_details']['reasoning_tokens'] ) ? (int) $usage['completion_tokens_details']['reasoning_tokens'] : 0;
        $cost = isset( $usage['cost'] ) && is_numeric( $usage['cost'] ) ? (float) $usage['cost'] : null;

        $json = array();
        foreach ( $content as $block )
            $json[] = $block['type'] === 'tool_use' ? array( 'type' => 'tool_use', 'id' => $block['id'], 'name' => $block['name'],
                                                            'input' => $block['input'] ? $block['input'] : new stdClass() ) : $block;
        return array(
            'content' => $content,
            'content_json' => json_encode( $json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE ),
            'native_json' => json_encode( $message ?: new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE ),
            'native_for' => $this->nativeKey(),
            'native_ids' => $generated,
            'stop_reason' => $stop,
            'model' => isset( $data['model'] ) && is_string( $data['model'] ) && $data['model'] !== '' ? $data['model'] : $this->modelName(),
            'usage' => $usage,
            'tokens' => array( 'input' => max( 0, $prompt - $cacheRead - $cacheWrite ), 'output' => isset( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : 0,
                               'cache_write' => $cacheWrite, 'cache_read' => $cacheRead, 'reasoning' => $reasoning, 'reported_cost' => $cost ),
            'invalid_tool_input' => $invalid,
            // the tier that served the request (default, flex, priority ...): a lane is billed at the real tier
            'service_tier' => isset( $data['service_tier'] ) && is_string( $data['service_tier'] ) ? substr( $data['service_tier'], 0, 20 ) : '',
            'provider' => $this->providerId(),
            'http_status' => $status,
            'retries' => 0,
            'rate_limits' => array(),
        );
    }

    /** @return string an id for a tool call that came without one (Mistral: 9 letters and digits) */
    protected function generateId( $index )
    {
        $random = bin2hex( random_bytes( 8 ) );
        if ( $this->flavor() === 'mistral' )
            return substr( preg_replace( '/[^a-zA-Z0-9]/', '', base64_encode( random_bytes( 12 ) ) ) . 'a0b1c2d3e', 0, 9 );
        return 'call_' . $index . '_' . $random;
    }
}
