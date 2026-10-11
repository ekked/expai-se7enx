<?php
/**
 * The module "expai" (Exponential Intelligence).
 *
 * Policies:
 *   expai/write  the assistant: the full view panel, the edit form controls (ezjscore functions expai::*)
 *   expai/log    the log page with the drafts of every user (with expai/write a user sees their own)
 *   expai/settings  shows administrators where the site default model is set (the dashboard)
 *   expai/model_use     choosing models of providers outside [ProviderSettings] ProvidersWithoutPolicy[]
 *                       (limitation Provider)
 *   expai/model_manage  the view expai/models: providers, key presence, probe results, the local model (start,
 *                       stop, pull, remove, test prompt)
 *   expai/mcp           MCP tools in the panel (limitation Server: "exp" for the in-process bridge, or a server id)
 *
 * Views:
 *   expai/log        the requests to the model and their usage; (view)/drafts the versions written; (export)/csv
 *   expai/dashboard  the Dashboard tab page
 *   expai/models     providers, models, probe results and the local model (expai/model_manage)
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

$Module = array( 'name' => 'Exponential Intelligence', 'variable_params' => false );

$ViewList = array();
$ViewList['log'] = array(
    'default_navigation_part' => 'ezmynavigationpart',
    'script' => 'log.php',
    'functions' => array( 'write' ),
    'params' => array(),
    'unordered_params' => array( 'offset' => 'Offset', 'view' => 'View', 'export' => 'Export', 'from' => 'From', 'to' => 'To',
                                 'user' => 'User', 'node' => 'Node', 'action' => 'Action', 'model' => 'Model', 'errors' => 'Errors',
                                 'sort' => 'Sort', 'dir' => 'Dir', 'provider' => 'Provider', 'region' => 'Region' ) );

$ViewList['dashboard'] = array(
    'default_navigation_part' => 'ezmynavigationpart',
    'script' => 'dashboard.php',
    'functions' => array( 'write' ),
    'params' => array(),
    'single_post_actions' => array( 'StartWritingButton' => 'StartWriting', 'ChangeLocationButton' => 'ChangeLocation' ),
    'post_action_parameters' => array( 'StartWriting' => array( 'Prompt' => 'ExpAiPrompt', 'NodeID' => 'ExpAiNodeID' ),
                                      'ChangeLocation' => array( 'Prompt' => 'ExpAiPrompt' ) ),
    'post_actions' => array( 'BrowseActionName' ),
    'unordered_params' => array( 'offset' => 'Offset', 'node' => 'Node', 'period' => 'Period', 'scope' => 'Scope', 'chart' => 'Chart',
                                 'again' => 'Again' ) );

$ViewList['models'] = array(
    'default_navigation_part' => 'ezsetupnavigationpart',
    'script' => 'models.php',
    'functions' => array( 'model_manage' ),
    'params' => array(),
    'single_post_actions' => array( 'ExpAiLocalActionButton' => 'LocalAction', 'ExpAiTestPromptButton' => 'TestPrompt' ),
    'post_action_parameters' => array( 'LocalAction' => array( 'Action' => 'ExpAiLocalAction', 'Model' => 'ExpAiLocalModel' ),
                                      'TestPrompt' => array( 'Model' => 'ExpAiTestModel' ) ),
    'unordered_params' => array( 'provider' => 'Provider' ) );

$Provider = array( 'name' => 'Provider', 'values' => array(), 'class' => 'expAiPolicy', 'function' => 'providers', 'parameter' => array() );
$Server = array( 'name' => 'Server', 'values' => array(), 'class' => 'expAiPolicy', 'function' => 'mcpServers', 'parameter' => array() );

$FunctionList = array();
$FunctionList['write'] = array();
$FunctionList['log'] = array();
$FunctionList['settings'] = array();
$FunctionList['model_use'] = array( 'Provider' => $Provider );
$FunctionList['model_manage'] = array();
$FunctionList['mcp'] = array( 'Server' => $Server );

?>
