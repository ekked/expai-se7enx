<?php
/**
 * File containing the expAiLimits class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The limits of expai.ini [Limits], checked before every request to the model (expAiUsageRecorder) and shown on the
 * dashboard. Each is optional (empty or 0: no limit). Usage is read from expai_usage_log (the same numbers as
 * expai/log); costs use the [Pricing] estimate. Days and months follow the site's time zone.
 *
 *   MaxTokensPerConversation   the panel conversation (resets with "New conversation")
 *   MaxTokensPerSession        the login session (resets when the user logs in again)
 *   MaxTokensPerUserPerDay, MaxCostPerUserPerDay       (reset at midnight)
 *   MaxTokensPerUserPerMonth, MaxCostPerUserPerMonth   (reset on the 1st of the month)
 *   MaxTokensPerSitePerMonth, MaxCostPerSitePerMonth   all users together (reset on the 1st)
 *   MaxRequestsPerTurn         model calls per editor message (expAiAssistant)
 *   ProviderMaxTokensPerMonth[<provider>], ProviderMaxCostPerMonth[<provider>]
 *                              all users together on one provider (reset on the 1st)
 */
class expAiLimits
{
    /** @return string a key of the current session (a hash: the session id itself is never stored) */
    public static function sessionKey()
    {
        $id = session_id();
        return $id ? substr( sha1( 'expai' . $id ), 0, 40 ) : '';
    }

    /** @return array the limit definitions: key => array( setting, unit, scope, period ) */
    public static function definitions()
    {
        return array(
            'conversation' => array( 'MaxTokensPerConversation', 'tokens', 'user', 'conversation' ),
            'session' => array( 'MaxTokensPerSession', 'tokens', 'user', 'session' ),
            'day_tokens' => array( 'MaxTokensPerUserPerDay', 'tokens', 'user', 'day' ),
            'day_cost' => array( 'MaxCostPerUserPerDay', 'cost', 'user', 'day' ),
            'month_tokens' => array( 'MaxTokensPerUserPerMonth', 'tokens', 'user', 'month' ),
            'month_cost' => array( 'MaxCostPerUserPerMonth', 'cost', 'user', 'month' ),
            'site_month_tokens' => array( 'MaxTokensPerSitePerMonth', 'tokens', 'site', 'month' ),
            'site_month_cost' => array( 'MaxCostPerSitePerMonth', 'cost', 'site', 'month' ),
        );
    }

    /**
     * The per-provider limits that are set: [Limits] ProviderMaxTokensPerMonth[<provider>], ProviderMaxCostPerMonth[<provider>].
     * @return array key (provider_month_tokens:<id> ...) => array( setting, unit, scope (site), period (month), provider, max )
     */
    public static function providerDefinitions( ?expAiConfig $config = null )
    {
        $config = $config ?: expAiConfig::instance();
        $out = array();
        foreach ( array( 'ProviderMaxTokensPerMonth' => array( 'provider_month_tokens', 'tokens' ), 'ProviderMaxCostPerMonth' => array( 'provider_month_cost', 'cost' ) ) as $setting => $def )
        {
            $values = $config->get( 'Limits', $setting, array() );
            foreach ( is_array( $values ) ? $values : array() as $provider => $max )
                if ( is_numeric( $max ) && (float) $max > 0 && preg_match( '/^[a-z0-9_-]{1,40}$/', (string) $provider ) )
                    $out[$def[0] . ':' . $provider] = array( $setting . '[' . $provider . ']', $def[1], 'site', 'month', (string) $provider, (float) $max );
        }
        return $out;
    }

    /** @return int the start of the period (Unix time) */
    public static function periodStart( $period )
    {
        if ( $period === 'day' )
            return mktime( 0, 0, 0 );
        if ( $period === 'month' )
            return mktime( 0, 0, 0, (int) date( 'n' ), 1 );
        return 0;
    }

    /** @return int|null when the period ends (Unix time), null for conversation and session */
    public static function periodEnd( $period )
    {
        if ( $period === 'day' )
            return mktime( 0, 0, 0, (int) date( 'n' ), (int) date( 'j' ) + 1 );
        if ( $period === 'month' )
            return mktime( 0, 0, 0, (int) date( 'n' ) + 1, 1 );
        return null;
    }

    /**
     * The state of every limit for the current user (and the site).
     * @param array $context user_id, conversation_id
     * @return array key => array( key, setting, unit, scope, period, used, max (0: none), percent, level (ok|warn|high|over), reset (Unix time or null) )
     */
    public static function status( array $context = array() )
    {
        $config = expAiConfig::instance();
        $userID = isset( $context['user_id'] ) ? (int) $context['user_id'] : (int) eZUser::currentUserID();
        $out = array();
        $cache = array();
        foreach ( self::definitions() as $key => $def )
        {
            list( $setting, $unit, $scope, $period ) = $def;
            $raw = $config->get( 'Limits', $setting, '' );
            $max = is_numeric( $raw ) ? (float) $raw : 0.0;
            $filters = array();
            if ( $scope === 'user' )
                $filters['user_id'] = $userID;
            if ( $period === 'conversation' )
            {
                if ( empty( $context['conversation_id'] ) )
                {
                    $out[$key] = array( 'key' => $key, 'setting' => $setting, 'unit' => $unit, 'scope' => $scope, 'period' => $period,
                                        'used' => 0, 'max' => $max, 'percent' => 0, 'level' => 'ok', 'reset' => null );
                    continue;
                }
                $filters['conversation_id'] = (int) $context['conversation_id'];
            }
            elseif ( $period === 'session' )
                $filters['session_key'] = self::sessionKey() ?: '-';
            else
                $filters['from'] = self::periodStart( $period );
            $cacheKey = md5( serialize( $filters ) );
            if ( !isset( $cache[$cacheKey] ) )
            {
                $agg = expAiUsage::aggregate( $filters );
                $cache[$cacheKey] = $agg['totals'];
            }
            $t = $cache[$cacheKey];
            $used = $unit === 'cost' ? (float) $t['cost'] : (float) ( $t['input'] + $t['output'] + $t['cache_write'] + $t['cache_read'] );
            $percent = $max > 0 ? min( 100, round( 100 * $used / $max, 1 ) ) : 0;
            $level = $max <= 0 ? 'ok' : ( $used >= $max ? 'over' : ( $percent >= 90 ? 'high' : ( $percent >= 75 ? 'warn' : 'ok' ) ) );
            $out[$key] = array( 'key' => $key, 'setting' => $setting, 'unit' => $unit, 'scope' => $scope, 'period' => $period,
                                'used' => $unit === 'cost' ? round( $used, 4 ) : (int) $used, 'max' => $max, 'percent' => $percent,
                                'level' => $level, 'reset' => self::periodEnd( $period ), 'cost_known' => $t['cost_known'],
                                'unpriced' => $unit === 'cost' && isset( $t['unpriced'] ) ? (int) $t['unpriced'] : 0 );
        }
        foreach ( self::providerDefinitions( $config ) as $key => $def )
        {
            list( $setting, $unit, $scope, $period, $provider, $max ) = $def;
            if ( isset( $context['provider'] ) && $context['provider'] !== $provider && !empty( $context['only_provider'] ) )
                continue;
            $filters = array( 'provider' => $provider, 'from' => self::periodStart( $period ) );
            $cacheKey = md5( serialize( $filters ) );
            if ( !isset( $cache[$cacheKey] ) )
            {
                $agg = expAiUsage::aggregate( $filters );
                $cache[$cacheKey] = $agg['totals'];
            }
            $t = $cache[$cacheKey];
            $used = $unit === 'cost' ? (float) $t['cost'] : (float) ( $t['input'] + $t['output'] + $t['cache_write'] + $t['cache_read'] );
            $percent = min( 100, round( 100 * $used / $max, 1 ) );
            $level = $used >= $max ? 'over' : ( $percent >= 90 ? 'high' : ( $percent >= 75 ? 'warn' : 'ok' ) );
            $out[$key] = array( 'key' => $key, 'setting' => $setting, 'unit' => $unit, 'scope' => $scope, 'period' => $period, 'provider' => $provider,
                                'used' => $unit === 'cost' ? round( $used, 4 ) : (int) $used, 'max' => $max, 'percent' => $percent,
                                'level' => $level, 'reset' => self::periodEnd( $period ), 'cost_known' => $t['cost_known'],
                                'unpriced' => $unit === 'cost' && isset( $t['unpriced'] ) ? (int) $t['unpriced'] : 0 );
        }
        return $out;
    }

    /**
     * Refuses a request when a limit is reached, naming the limit and when it resets.
     * @param array $context user_id, conversation_id
     * @throws expAiException
     */
    public static function check( array $context )
    {
        $config = expAiConfig::instance();
        $any = false;
        foreach ( self::definitions() as $def )
        {
            $raw = $config->get( 'Limits', $def[0], '' );
            if ( is_numeric( $raw ) && (float) $raw > 0 )
                $any = true;
        }
        if ( self::providerDefinitions( $config ) )
            $any = true;
        if ( !$any )
            return;
        foreach ( self::status( $context ) as $limit )
        {
            if ( $limit['level'] !== 'over' )
                continue;
            // a provider's limit stops only requests to that provider
            if ( isset( $limit['provider'] ) && ( !isset( $context['provider'] ) || $context['provider'] !== $limit['provider'] ) )
                continue;
            $resets = array(
                'conversation' => ezpI18n::tr( 'extension/expai/limits', 'when you start a new conversation' ),
                'session' => ezpI18n::tr( 'extension/expai/limits', 'when you log in again' ),
                'day' => ezpI18n::tr( 'extension/expai/limits', 'at %time', null, array( '%time' => $limit['reset'] ? date( 'Y-m-d H:i', $limit['reset'] ) : '' ) ),
                'month' => ezpI18n::tr( 'extension/expai/limits', 'at %time', null, array( '%time' => $limit['reset'] ? date( 'Y-m-d H:i', $limit['reset'] ) : '' ) ),
            );
            throw new expAiException( 'The limit %limit is reached (%used of %max). It resets %reset.', 'limit ' . $limit['setting'], 0, array(
                '%limit' => self::label( $limit['key'] ),
                '%used' => $limit['unit'] === 'cost' ? $limit['used'] . ' ' . expAiUsage::currency() : number_format( $limit['used'] ),
                '%max' => $limit['unit'] === 'cost' ? $limit['max'] . ' ' . expAiUsage::currency() : number_format( $limit['max'] ),
                '%reset' => $resets[$limit['period']] ) );
        }
    }

    /** @return string the translated name of a limit */
    public static function label( $key )
    {
        $labels = array(
            'conversation' => 'tokens per conversation', 'session' => 'tokens per session',
            'day_tokens' => 'tokens per day', 'day_cost' => 'cost per day',
            'month_tokens' => 'tokens per month', 'month_cost' => 'cost per month',
            'site_month_tokens' => 'tokens of the whole site per month', 'site_month_cost' => 'cost of the whole site per month',
        );
        if ( preg_match( '/^provider_month_(tokens|cost):(.+)$/', $key, $m ) )
        {
            if ( $m[1] === 'cost' )
                return ezpI18n::tr( 'extension/expai/limits', 'cost of the provider %provider per month', null, array( '%provider' => $m[2] ) );
            return ezpI18n::tr( 'extension/expai/limits', 'tokens of the provider %provider per month', null, array( '%provider' => $m[2] ) );
        }
        return isset( $labels[$key] ) ? ezpI18n::tr( 'extension/expai/limits', $labels[$key] ) : $key;
    }

    /**
     * The newest rate limit headers the API sent (from the usage log).
     * @return array time, values (header => value) ; empty when none were recorded
     */
    public static function latestRateLimits()
    {
        // the dashboard reads the Anthropic headers; other providers' headers stay in their log rows
        $rows = (array) eZPersistentObject::fetchObjectList( expAiUsageLog::definition(), array( 'created', 'rate_limits' ),
            array( 'rate_limits' => array( '!=', '' ), 'provider' => 'anthropic' ), array( 'id' => 'desc' ), array( 'offset' => 0, 'length' => 1 ), false );
        if ( !$rows )
            return array();
        $values = json_decode( (string) $rows[0]['rate_limits'], true );
        return is_array( $values ) ? array( 'time' => (int) $rows[0]['created'], 'values' => $values ) : array();
    }
}
