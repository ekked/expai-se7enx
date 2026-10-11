<?php
/**
 * File containing the expAiTemplateOperators class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * {$amount|expai_money( $approx )}  "$0.06", "< $0.01", with "≈" for an approximate cost (expAiCost::money())
 * {$tokens|expai_tokens}            "950", "21.6k", "1.2M" (expAiCost::tokens())
 * {$value|expai_number( 2 )}        the locale's separators (expAiCost::number())
 */
class expAiTemplateOperators
{
    public function operatorList()
    {
        return array( 'expai_money', 'expai_tokens', 'expai_number' );
    }

    public function namedParameterPerOperator()
    {
        return true;
    }

    public function namedParameterList()
    {
        return array(
            'expai_money' => array( 'approx' => array( 'type' => 'boolean', 'required' => false, 'default' => false ) ),
            'expai_tokens' => array(),
            'expai_number' => array( 'decimals' => array( 'type' => 'integer', 'required' => false, 'default' => 0 ) ),
        );
    }

    public function modify( $tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, &$operatorValue, $namedParameters )
    {
        switch ( $operatorName )
        {
            case 'expai_money':
                $operatorValue = expAiCost::money( $operatorValue, !empty( $namedParameters['approx'] ) );
                break;
            case 'expai_tokens':
                $operatorValue = expAiCost::tokens( $operatorValue );
                break;
            case 'expai_number':
                $operatorValue = expAiCost::number( $operatorValue, (int) $namedParameters['decimals'] );
                break;
        }
    }
}
