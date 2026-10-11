<?php
/**
 * File containing the expAiConfig class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The settings of expai.ini, read fresh on every call (no static state: Velocity's persistent workers serve many
 * requests and siteaccesses with one process).
 */
class expAiConfig
{
    /** @var eZINI */
    protected $ini;

    public function __construct( $ini = null )
    {
        $this->ini = $ini ?: eZINI::instance( 'expai.ini' );
    }

    /** @return expAiConfig */
    public static function instance()
    {
        return new self();
    }

    /** @return eZINI */
    public function ini()
    {
        return $this->ini;
    }

    /**
     * @param string $group
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get( $group, $name, $default = null )
    {
        if ( !$this->ini->hasVariable( $group, $name ) )
            return $default;
        return $this->ini->variable( $group, $name );
    }

    /** @return int */
    public function int( $group, $name, $default, $min = null, $max = null )
    {
        $value = $this->get( $group, $name, $default );
        $value = is_numeric( $value ) ? (int) $value : (int) $default;
        if ( $min !== null )
            $value = max( $min, $value );
        if ( $max !== null )
            $value = min( $max, $value );
        return $value;
    }

    /** @return bool true for enabled / true / 1 / yes */
    public function flag( $group, $name, $default = false )
    {
        $value = $this->get( $group, $name, null );
        if ( $value === null )
            return $default;
        return in_array( strtolower( trim( (string) $value ) ), array( 'enabled', 'true', '1', 'yes', 'on' ), true );
    }

    /** @return array */
    public function arr( $group, $name )
    {
        $value = $this->get( $group, $name, array() );
        if ( !is_array( $value ) )
            return $value === '' || $value === null ? array() : array( $value );
        return array_values( array_filter( $value, static function ( $v ) { return $v !== '' && $v !== null; } ) );
    }

    /** @return bool the assistant is switched on (it may still lack a key, see isConfigured()) */
    public function isEnabled()
    {
        return $this->flag( 'AISettings', 'Enabled', false );
    }

    /** @return bool a provider can be called: enabled, a model, and a key (for anthropic) or a custom class */
    public function isConfigured()
    {
        if ( !$this->isEnabled() || trim( (string) $this->get( 'AISettings', 'Model', '' ) ) === '' )
            return false;
        $provider = (string) $this->get( 'AISettings', 'Provider', 'anthropic' );
        if ( $provider === 'custom' )
            return trim( (string) $this->get( 'AISettings', 'ProviderClass', '' ) ) !== '';
        return trim( (string) $this->get( 'AISettings', 'ApiKey', '' ) ) !== '';
    }

    /** @return string the configured model name */
    public function model()
    {
        return trim( (string) $this->get( 'AISettings', 'Model', '' ) );
    }

    /**
     * The [AISettings] values for a provider, with the model the current user chose (the key included: never log or
     * output this array).
     * @return array
     */
    public function providerSettings()
    {
        $settings = array();
        foreach ( array( 'Endpoint', 'AnthropicVersion', 'Model', 'ApiKey', 'AnthropicWorkspaceId', 'MaxTokens', 'Effort',
                         'RefusalFallback', 'PromptCaching', 'Timeout', 'ConnectTimeout', 'MaxResponseBytes' ) as $name )
            $settings[$name] = $this->get( 'AISettings', $name, '' );
        $settings['Model'] = $this->effectiveModel();
        return $settings;
    }

    /** @return bool users may choose a model ([AISettings] AllowUserModelChoice) */
    public function modelChoiceEnabled()
    {
        return $this->flag( 'AISettings', 'AllowUserModelChoice', false );
    }

    /**
     * The models users may choose: [AISettings] AllowedModels[] (and always the site default), with their labels
     * from ModelLabels[<id>].
     * @return array id => label
     */
    public function allowedModels()
    {
        $labels = $this->get( 'AISettings', 'ModelLabels', array() );
        $labels = is_array( $labels ) ? $labels : array();
        $out = array();
        foreach ( array_merge( array( $this->model() ), $this->arr( 'AISettings', 'AllowedModels' ) ) as $id )
        {
            $id = trim( (string) $id );
            if ( $id !== '' && !isset( $out[$id] ) )
                $out[$id] = isset( $labels[$id] ) && $labels[$id] !== '' ? (string) $labels[$id] : $id;
        }
        return $out;
    }

    /** @return string the model the current user chose (preference expai_model), '' when none */
    public function preferredModel()
    {
        $user = eZUser::currentUser();
        if ( !$user instanceof eZUser || !$user->isRegistered() )
            return '';
        $value = eZPreferences::value( 'expai_model', $user );
        return is_string( $value ) ? trim( $value ) : '';
    }

    /** @return bool the user chose a model that is no longer allowed (the default is used instead) */
    public function preferredModelRefused()
    {
        $preferred = $this->preferredModel();
        return $preferred !== '' && ( !$this->modelChoiceEnabled() || !isset( $this->allowedModels()[$preferred] ) );
    }

    /** @return string the model requests use: the user's allowed choice, else the site default */
    public function effectiveModel()
    {
        $preferred = $this->preferredModel();
        if ( $preferred !== '' && $this->modelChoiceEnabled() && isset( $this->allowedModels()[$preferred] ) )
            return $preferred;
        return $this->model();
    }

    /**
     * Where the site default model is set, for administrators.
     * @return array placement (default, extension:<name>, siteaccess, override ...), file
     */
    public function modelPlacement()
    {
        // loadPlacement() with its default $reset = true empties the values of the shared instance: keep them
        $this->ini->loadPlacement( false );
        $placements = $this->ini->groupPlacements();
        $path = isset( $placements['AISettings']['Model'] ) ? $placements['AISettings']['Model'] : '';
        $file = is_array( $path ) ? ( isset( $path[0] ) ? $path[0] : '' ) : (string) $path;
        return array( 'placement' => $path ? $this->ini->findSettingPlacement( $path ) : '',
                      'file' => ltrim( str_replace( eZSys::rootDir(), '', $file ), '/' ) );
    }

    /** @return string[] datatype strings the tools and field actions may write */
    public function writableDatatypes()
    {
        return $this->arr( 'ToolSettings', 'WritableDatatypes' );
    }
}
