<?php
/**
 * File containing the expAiToolRegistry class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The tools of expai.ini [ToolSettings] Tools[], each [Tool_<name>] Class=<expAiTool subclass>.
 */
class expAiToolRegistry
{
    /** @var expAiTool[] name => tool */
    protected $tools = array();

    public function __construct( expAiConfig $config )
    {
        foreach ( $config->arr( 'ToolSettings', 'Tools' ) as $name )
        {
            $class = (string) $config->get( 'Tool_' . $name, 'Class', '' );
            if ( $class === '' || !class_exists( $class ) || !is_subclass_of( $class, 'expAiTool' ) )
            {
                eZDebug::writeWarning( "expai: tool $name has no usable class ($class)", __METHOD__ );
                continue;
            }
            $this->tools[$name] = new $class( $name );
        }
    }

    /** @return expAiTool[] */
    public function tools()
    {
        return $this->tools;
    }

    /** @return expAiTool|null */
    public function get( $name )
    {
        return isset( $this->tools[$name] ) ? $this->tools[$name] : null;
    }

    /** @return array the definitions for the provider */
    public function definitions()
    {
        $out = array();
        foreach ( $this->tools as $tool )
            $out[] = $tool->definition();
        return $out;
    }
}
