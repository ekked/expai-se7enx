<?php
/**
 * File containing the expAiMcpBridgeTool class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * An audited read tool of the extension expmcp, called in process as the current user (exp__<tool>). The expmcp
 * tool object is built with expmcp's own security object (its siteaccess allowlist, expmcp.ini [Security]
 * AllowedSiteAccesses, still applies; writes are off) and a logger that drops everything; no expmcp code is
 * changed. Node results the data policy keeps away from the provider are left out.
 */
class expAiMcpBridgeTool extends expAiTool
{
    /** @var string the expmcp tool name */
    protected $mcpName;

    /** @var array the entry of expAiMcp::BRIDGE_TOOLS */
    protected $spec;

    /** @return expAiMcpBridgeTool|null the bridge tool for an expmcp tool name (null: not audited or not installed) */
    public static function create( $mcpName )
    {
        $tools = expAiMcp::BRIDGE_TOOLS;
        if ( !isset( $tools[$mcpName] ) )
        {
            eZDebug::writeWarning( "expai: the expmcp tool $mcpName is not audited for the bridge and is not offered", __METHOD__ );
            return null;
        }
        if ( !class_exists( $tools[$mcpName][0] ) || !class_exists( 'expMcpSecurity' ) || !class_exists( 'Psr\\Log\\NullLogger' ) )
            return null;
        $tool = new self( 'exp__' . $mcpName );
        $tool->mcpName = $mcpName;
        $tool->spec = $tools[$mcpName];
        return $tool;
    }

    public function description()
    {
        return '[Exponential, read only] ' . call_user_func( array( $this->spec[0], $this->spec[3] ) );
    }

    public function inputSchema()
    {
        return call_user_func( array( $this->spec[0], $this->spec[4] ) );
    }

    public function stepText( array $input )
    {
        return 'Queried the content (%tool)';
    }

    /** @return object the expmcp tool, built as expmcp builds it, read-only */
    protected function instance()
    {
        $ini = eZINI::instance( 'expmcp.ini' );
        $security = new expMcpSecurity( $ini, false, 'stdio' );
        $logger = new Psr\Log\NullLogger();
        $siteAccess = isset( $GLOBALS['eZCurrentAccess']['name'] ) ? (string) $GLOBALS['eZCurrentAccess']['name'] : '';
        $class = $this->spec[0];
        return new $class( $security, $logger, $siteAccess, $ini, null );
    }

    public function execute( array $input, expAiToolContext $context )
    {
        $arguments = array();
        foreach ( $this->spec[2] as $name )
            $arguments[] = array_key_exists( $name, $input ) ? $input[$name] : null;
        try
        {
            $result = call_user_func_array( array( $this->instance(), $this->spec[1] ), $arguments );
        }
        catch ( Throwable $e )
        {
            throw new expAiException( 'The tool %tool failed: %error', get_class( $e ), 0, array( '%tool' => $this->name, '%error' => expAiText::cut( $e->getMessage(), 200 ) ) );
        }
        if ( !is_array( $result ) )
            return array( 'result' => $result );
        // the data policy: nodes it keeps away are left out
        if ( isset( $result['nodes'] ) && is_array( $result['nodes'] ) && $context->reader->policy() )
        {
            $kept = array();
            foreach ( $result['nodes'] as $row )
            {
                $object = isset( $row['object_id'] ) ? eZContentObject::fetch( (int) $row['object_id'] ) : null;
                if ( $object && !$context->reader->allowed( $object ) )
                {
                    $context->reader->withheld++;
                    continue;
                }
                $kept[] = $row;
            }
            $result['nodes'] = $kept;
            $result['returned'] = count( $kept );
        }
        return $result;
    }
}
