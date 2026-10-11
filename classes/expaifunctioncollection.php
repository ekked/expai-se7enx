<?php
/**
 * File containing the expAiFunctionCollection class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The template fetch functions of expai (modules/expai/function_definition.php). Each returns nothing for users
 * without the policy expai/write.
 */
class expAiFunctionCollection
{
    public function fetchMyDrafts( $offset, $limit )
    {
        if ( !expAiServerFunctions::hasPolicy() )
            return array( 'result' => array() );
        return array( 'result' => expAiDashboard::myDrafts( max( 0, (int) $offset ), max( 1, min( 100, (int) $limit ) ) ) );
    }

    public function fetchMyDraftCount()
    {
        return array( 'result' => expAiServerFunctions::hasPolicy() ? expAiDashboard::myDraftCount() : 0 );
    }

    public function fetchWaitingForMyApproval()
    {
        return array( 'result' => expAiServerFunctions::hasPolicy() ? expAiDashboard::waitingForMyApproval() : array() );
    }

    /** @return array allowed (id => label), default, current, choice (bool), refused (bool: a stored choice is no longer allowed), pricing */
    public function fetchModels()
    {
        $config = expAiConfig::instance();
        return array( 'result' => array( 'allowed' => $config->allowedModels(), 'default' => $config->model(),
                                         'current' => $config->effectiveModel(), 'choice' => $config->modelChoiceEnabled(),
                                         'refused' => $config->preferredModelRefused(),
                                         'placement' => expAiServerFunctions::hasPolicy( 'settings' ) ? $config->modelPlacement() : false,
                                         'pricing' => expAiUsage::pricingInfo() ) );
    }
}
