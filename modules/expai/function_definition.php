<?php
/**
 * Template fetch functions of expai:
 *
 *   fetch( 'expai', 'my_drafts', hash( 'limit', 5 ) )        the current user's log rows (expAiDashboard::myDrafts())
 *   fetch( 'expai', 'my_draft_count' )                       how many there are
 *   fetch( 'expai', 'waiting_for_my_approval' )              AI-written versions waiting for the user's approval
 *   fetch( 'expai', 'models' )                               the models the user may choose, the default and the current choice
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

$FunctionList = array();
$FunctionList['my_drafts'] = array( 'name' => 'my_drafts',
                                    'operation_types' => array( 'read' ),
                                    'call_method' => array( 'class' => 'expAiFunctionCollection', 'method' => 'fetchMyDrafts' ),
                                    'parameter_type' => 'standard',
                                    'parameters' => array( array( 'name' => 'offset', 'type' => 'integer', 'required' => false, 'default' => 0 ),
                                                           array( 'name' => 'limit', 'type' => 'integer', 'required' => false, 'default' => 10 ) ) );
$FunctionList['my_draft_count'] = array( 'name' => 'my_draft_count',
                                         'operation_types' => array( 'read' ),
                                         'call_method' => array( 'class' => 'expAiFunctionCollection', 'method' => 'fetchMyDraftCount' ),
                                         'parameter_type' => 'standard',
                                         'parameters' => array() );
$FunctionList['waiting_for_my_approval'] = array( 'name' => 'waiting_for_my_approval',
                                                  'operation_types' => array( 'read' ),
                                                  'call_method' => array( 'class' => 'expAiFunctionCollection', 'method' => 'fetchWaitingForMyApproval' ),
                                                  'parameter_type' => 'standard',
                                                  'parameters' => array() );
$FunctionList['models'] = array( 'name' => 'models',
                                 'operation_types' => array( 'read' ),
                                 'call_method' => array( 'class' => 'expAiFunctionCollection', 'method' => 'fetchModels' ),
                                 'parameter_type' => 'standard',
                                 'parameters' => array() );
