<?php
/**
 * Test of content packages, the thin layer on the kernel's packages (doc/packages.md): a test subtree with a
 * translation, rich text with a link and an embed, an image, a file, keywords, a relation and a nested object is
 * exported with provenance in the kernel's JSON format, validated, kept (imported into the package repository; the
 * report says update for every object), imported as drafts of the existing objects (nothing published changes, every
 * draft in the draft log with the package), removed and imported again as new drafts (children wait for their parent),
 * through the job queue as well; the minimal package of doc/package-authoring-for-agents.md is written by hand,
 * packed and imported; refused archives (a file whose checksum does not match, a path outside the package).
 * The content is made below a test folder and removed again; the packages are removed from the repository.
 *
 *   EXPAI_TEST_PARENT=<node id>      a (hidden) test folder; the test makes its content below it
 *   EXPAI_PACKAGE_DIR=<dir>          where the test writes its packages
 *   EXPAI_TEST_LOGIN=<login>         the user
 *   php bin/php/ezexec.php extension/expai/tests/expaipackage_test.php -s <admin siteaccess>
 *
 * The queued import runs the one job of its group in this process (a worker of this test only), so no other job of
 * the queue is touched.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

$parentID = (int) getenv( 'EXPAI_TEST_PARENT' );
$dir = rtrim( (string) getenv( 'EXPAI_PACKAGE_DIR' ), '/' );
$login = (string) getenv( 'EXPAI_TEST_LOGIN' );
$failures = 0;
$checks = 0;
$check = function ( $label, $actual, $expected ) use ( &$failures, &$checks )
{
    ++$checks;
    $ok = $actual === $expected;
    if ( !$ok )
        ++$failures;
    echo ( $ok ? 'ok    ' : 'FAIL  ' ) . $label . ( $ok ? '' : ' => ' . substr( var_export( $actual, true ), 0, 500 ) . ' (expected ' . substr( var_export( $expected, true ), 0, 200 ) . ')' ) . "\n";
};
$script = eZScript::instance();
$user = $login !== '' ? eZUser::fetchByName( $login ) : null;
$parent = eZContentObjectTreeNode::fetch( $parentID );
if ( !$user || !$parent || $dir === '' )
{
    echo "FAIL set EXPAI_TEST_PARENT, EXPAI_PACKAGE_DIR and EXPAI_TEST_LOGIN\n";
    $script->shutdown( 1 );
}
eZUser::setCurrentlyLoggedInUser( $user, (int) $user->attribute( 'contentobject_id' ), eZUser::NO_SESSION_REGENERATE );
$userID = (int) $user->attribute( 'contentobject_id' );
eZDir::mkdir( $dir, 0770, true );
$run = substr( md5( (string) microtime( true ) ), 0, 6 );
$service = 'expAiPackageService';

$create = function ( $parentNodeID, $class, array $attributes, $remoteID ) use ( $userID )
{
    return eZContentFunctions::createAndPublishObject( array( 'parent_node_id' => (int) $parentNodeID, 'class_identifier' => $class, 'creator_id' => $userID,
                                                              'attributes' => $attributes, 'remote_id' => $remoteID ), false ) ?: null;
};
$xml = function ( $inner )
{
    return '<?xml version="1.0" encoding="utf-8"?>' . "\n" . '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"'
         . ' xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/">' . $inner . '</section>' . "\n";
};
$records = array();
$purge = function ( array $remoteIDs )
{
    foreach ( array_reverse( $remoteIDs ) as $rid )
        if ( $object = eZContentObject::fetchByRemoteID( $rid ) )
            eZContentObjectOperations::remove( (int) $object->attribute( 'id' ) );
    eZContentObject::clearCache();
};

// =============================================================================================== content
$a = $create( $parentID, 'folder', array( 'name' => "Source $run" ), "t372x-a-$run" );
$aNode = $a->mainNode();
$png = eZSys::varDirectory() . "/tmp-t372x-$run.png";
$im = imagecreatetruecolor( 40, 30 );
imagefill( $im, 0, 0, imagecolorallocate( $im, 200, 80, 20 ) );
imagepng( $im, $png );
$image = $create( $aNode->attribute( 'node_id' ), 'image', array( 'name' => 'Test image', 'image' => $png . '|An orange square' ), "t372x-image-$run" );
@unlink( $png );
$urlID = eZURL::registerURL( 'https://example.org/expai-package-test' );
$folder = $create( $aNode->attribute( 'node_id' ), 'folder', array(
    'name' => 'Folder one', 'short_name' => 'One',
    'short_description' => $xml( '<paragraph>Short with a <link url_id="' . $urlID . '">link</link> and <strong>bold</strong>.</paragraph>' ),
    'description' => $xml( '<paragraph>An embedded image:<embed view="embed" size="medium" object_id="' . (int) $image->attribute( 'id' ) . '"/></paragraph>' ),
    'tags' => 'alpha, beta' ), "t372x-folder-$run" );
$fv = $folder->createNewVersionIn( 'ger-DE', 'eng-US', false, true, eZContentObjectVersion::STATUS_DRAFT );
$dm = $folder->fetchDataMap( (int) $fv->attribute( 'version' ), 'ger-DE' );
$dm['name']->fromString( 'Ordner eins' );
$dm['name']->store();
eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => (int) $folder->attribute( 'id' ), 'version' => (int) $fv->attribute( 'version' ) ) );
$article = $create( $aNode->attribute( 'node_id' ), 'article', array( 'title' => 'Article one', 'intro' => $xml( '<paragraph>Intro.</paragraph>' ),
    'body' => $xml( '<paragraph>See <link object_id="' . (int) $folder->attribute( 'id' ) . '">the folder</link>.</paragraph>' ),
    'image' => (string) $image->attribute( 'id' ) ), "t372x-article-$run" );
$txt = eZSys::varDirectory() . "/notes-$run.txt";
file_put_contents( $txt, "Notes of the package test $run\n" );
$file = $create( $aNode->attribute( 'node_id' ), 'file', array( 'name' => 'Test file', 'file' => $txt ), "t372x-file-$run" );
@unlink( $txt );
$nested = $create( $folder->mainNode()->attribute( 'node_id' ), 'article', array( 'title' => 'Nested article', 'intro' => $xml( '<paragraph>Below.</paragraph>' ) ), "t372x-nested-$run" );
eZContentObject::clearCache();
$remoteIDs = array( "t372x-a-$run", "t372x-image-$run", "t372x-folder-$run", "t372x-article-$run", "t372x-file-$run", "t372x-nested-$run" );
$check( 'setup: test content created', (bool) ( $image && $folder && $article && $file && $nested ), true );

// =============================================================================================== export
$export = $service::export( (int) $aNode->attribute( 'node_id' ), $dir, array( 'name' => "t372x_$run", 'languages' => array( 'eng-US', 'ger-DE' ),
    'provenance' => array( 'ai_generated' => true, 'models' => array( 'model-a' ), 'tools' => array( 'expaipackage_test' ), 'human_reviewed' => false ) ) );
$check( 'export: a JSON package of 6 objects', array( $export['format'], $export['objects'], substr( $export['file'], -11 ) ), array( 'json', 6, '.json.ezpkg' ) );
$work = expPackageJson::workDirectory( 't372x' );
expPackageJson::extractArchive( $export['file'], "$work/pkg" );
$definition = json_decode( file_get_contents( "$work/pkg/package.json" ), true );
$check( 'export: the provenance is package metadata', strpos( json_encode( $definition['root'] ), '["entry",{"key":"expai.ai-generated"},"true"]' ) !== false, true );
$check( 'export: content only (no class, no template items)', strpos( file_get_contents( "$work/pkg/package.json" ), 'ezcontentclass' ) === false, true );
$check( 'export: valid for the kernel', expPackageJson::validateDirectory( "$work/pkg" )['valid'], true );

// =============================================================================================== keep, check
$record = $service::keep( $export['file'], 'cli', $userID );
$records[] = $record;
$check( 'keep: valid, imported into the package repository', array( $record->attribute( 'state' ), $record->package() instanceof eZPackage ), array( 'valid', true ) );
$check( 'keep: 6 objects, JSON', array( (int) $record->attribute( 'object_count' ), $record->attribute( 'format_version' ) ), array( 6, 'json' ) );
$provenance = $service::provenance( $record->package() );
$check( 'keep: the provenance is read back', array( $provenance['ai_generated'], $provenance['models'], $provenance['human_reviewed'] ), array( true, array( 'model-a' ), false ) );
$dry = $service::dryRun( $record );
$check( 'dry run: 6 existing objects (update)', $dry['counts'], array( 'create' => 0, 'update' => 6, 'conflict' => 0, 'error' => 0 ) );

// =============================================================================================== drafts of existing objects
$published = array();
foreach ( $remoteIDs as $rid )
    $published[$rid] = (int) eZContentObject::fetchByRemoteID( $rid )->attribute( 'current_version' );
$report = $service::import( $record, array( 'existing' => 'update' ) );
$check( 'import: in the request, imported', array( $report['queued'], $report['imported'] ), array( false, true ) );
$check( 'import: 6 drafts of existing objects', $report['counts'], array( 'draft_of_existing' => 6 ) );
$same = 0;
foreach ( $remoteIDs as $rid )
    if ( (int) eZContentObject::fetchByRemoteID( $rid )->attribute( 'current_version' ) === $published[$rid] )
        ++$same;
$check( 'import: nothing published changed', $same, 6 );
$logged = (int) eZPersistentObject::count( expAiDraftLog::definition(), array( 'package_uuid' => (string) $record->attribute( 'uuid' ), 'source' => 'package' ) );
$check( 'import: every draft is in the draft log with the package', $logged, 6 );
$check( 'import: the record is imported', expAiPackageRecord::fetchByUUID( $record->attribute( 'uuid' ) )->attribute( 'state' ), 'imported' );
$report = $service::import( $record, array( 'existing' => 'skip' ) );
$check( 'import, existing=skip: every object left alone', $report['counts'], array( 'skipped' => 6 ) );

// =============================================================================================== new drafts
$purge( $remoteIDs );
$report = $service::import( $record, array( 'parent' => (string) $parentID ) );
$check( 'new drafts: 6 new draft objects', $report['counts'], array( 'new_draft' => 6 ) );
$check( 'new drafts: 5 wait for a parent of the package', count( $report['waiting'] ), 5 );
$nodes = 0;
foreach ( $remoteIDs as $rid )
    if ( ( $o = eZContentObject::fetchByRemoteID( $rid ) ) && $o->mainNode() )
        ++$nodes;
$check( 'new drafts: nothing published', $nodes, 0 );
$top = eZContentObject::fetchByRemoteID( $remoteIDs[0] );
$assignment = eZPersistentObject::fetchObject( eZNodeAssignment::definition(), null, array( 'contentobject_id' => (int) $top->attribute( 'id' ) ), true );
$check( 'new drafts: the top object goes below the parent given', $assignment ? (int) $assignment->attribute( 'parent_node' ) : 0, $parentID );

// =============================================================================================== through the queue
$purge( $remoteIDs );
$report = $service::import( $record, array( 'parent' => (string) $parentID, 'queue' => true ) );
$check( 'queue: queued as one job', array( $report['queued'], $report['jobs'] ), array( true, 1 ) );
$group = expQueue::resolveGroup( $report['group'] );
$jobs = expQueue::listJobs( array( 'group_id' => (int) $group->attribute( 'id' ) ), 0, 5 );
$job = $jobs[0];
expQueue::casUpdate( (int) $job->attribute( 'id' ), array( 'state' => (string) $job->attribute( 'state' ) ),
                     array( 'state' => expQueueJob::STATE_RUNNING, 'lease_owner' => 't372x', 'lease_until' => expQueue::now() + 600, 'attempts' => 1 ) );
( new expQueueWorker() )->runJob( expQueueJob::fetchByID( (int) $job->attribute( 'id' ) ), 't372x' );
eZUser::setCurrentlyLoggedInUser( $user, $userID, eZUser::NO_SESSION_REGENERATE );
$queued = $service::queuedReport( expAiPackageRecord::fetchByUUID( $record->attribute( 'uuid' ) ) );
$check( 'queue: the job ended and wrote 6 new drafts', array( $queued['imported'], $queued['counts'] ), array( true, array( 'new_draft' => 6 ) ) );
$purge( $remoteIDs );

// =============================================================================================== the hand-written minimal package
$hand = "$work/hand";
eZDir::mkdir( "$hand/ezcontentobject", false, true );
$definitionJSON = array( 'format' => 'exponential-package', 'format-version' => '1.0', 'kind' => 'definition', 'source' => 'package.xml',
    'root' => array( 'package', array( 'version' => '3.5.2', 'development' => 'false', 'install_type' => 'install' ),
        array( 'name', "t372x_hand_$run" ), array( 'summary', 'Autumn articles' ),
        array( 'ezpublish', array( 'version', '6.0.15' ), array( 'named-version', '6.0' ) ),
        array( 'packaging', array( 'timestamp', '1791763200' ), array( 'host', 'localhost' ) ),
        array( 'version', array( 'number', '1.0' ), array( 'release', '1' ) ), array( 'licence', 'GPL-2.0-or-later' ), array( 'state', 'stable' ),
        array( 'install', array( 'item', array( 'type' => 'ezcontentobject', 'filename' => 'contentobjects', 'sub-directory' => 'ezcontentobject' ) ) ),
        array( 'metadata', array( 'entry', array( 'key' => 'expai.ai-generated' ), 'true' ), array( 'entry', array( 'key' => 'expai.models' ), 'claude-opus-5-5' ) ) ) );
$folderClass = eZContentClass::fetchByIdentifier( 'folder' );
$objects = array( 'content-object', array( 'xmlns:ezremote' => 'http://ez.no/ezobject', 'xmlns:ezobject' => 'http://ez.no/object/' ),
    array( 'object-list',
        array( 'ezremote:object', array( 'name' => 'Leaves and light', 'remote_id' => "t372x-hand-$run", 'class_remote_id' => (string) $folderClass->attribute( 'remote_id' ),
                                         'ezremote:class_identifier' => 'folder', 'ezremote:section_id' => '1', 'ezremote:always_available' => '0', 'initial_language' => 'eng-US' ),
            array( 'ezobject:version-list', array( 'active_version' => '1' ),
                array( 'ezobject:version', array( 'ezremote:version' => '1', 'ezremote:status' => '1', 'initial_language' => 'eng-US' ),
                    array( 'ezobject:object-translation', array( 'language' => 'eng-US', 'object_name' => 'Leaves and light' ),
                        array( 'ezobject:attribute', array( 'ezremote:identifier' => 'name', 'type' => 'ezstring' ), array( 'text', 'Leaves and light' ) ),
                        array( 'ezobject:attribute', array( 'ezremote:identifier' => 'short_description', 'type' => 'ezxmltext' ),
                               array( '#xml', '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>The first cold <strong>morning</strong>.</paragraph></section>' ) ) ),
                    array( 'ezobject:node-assignment-list',
                        array( 'node-assignment', array( 'is-main-node' => '1', 'parent-node-remote-id' => (string) $parent->attribute( 'remote_id' ),
                                                         'remote-id' => "t372x-hand-$run-node", 'sort-field' => 'path', 'sort-order' => '1', 'priority' => '0' ) ) ) ) ) ) ) );
file_put_contents( "$hand/package.json", json_encode( $definitionJSON ) );
file_put_contents( "$hand/ezcontentobject/contentobjects.json", json_encode( array( 'format' => 'exponential-package', 'format-version' => '1.0', 'kind' => 'document',
                                                                                     'source' => 'ezcontentobject/contentobjects.xml', 'root' => $objects ) ) );
$check( 'hand-written: valid without checksums', expPackageJson::validateDirectory( $hand )['valid'], true );
$handArchive = "$dir/t372x_hand_$run-1.0-1.json.ezpkg";
$converted = expPackageJson::convert( $hand, $handArchive, 'json' );
$check( 'hand-written: packed with checksums', $converted['ok'], true );
$handRecord = $service::keep( $handArchive, 'cli', $userID );
$records[] = $handRecord;
$check( 'hand-written: kept', $handRecord->attribute( 'state' ), 'valid' );
$report = $service::import( $handRecord );
$check( 'hand-written: one new draft below the parent named by its node remote id', $report['counts'], array( 'new_draft' => 1 ) );
$handObject = eZContentObject::fetchByRemoteID( "t372x-hand-$run" );
$handVersion = $handObject ? $handObject->version( (int) $handObject->attribute( 'current_version' ) ) : null;
$handData = $handObject ? $handObject->fetchDataMap( (int) $handObject->attribute( 'current_version' ), 'eng-US' ) : array();
$check( 'hand-written: the draft has the name and the rich text', array( isset( $handData['name'] ) ? $handData['name']->toString() : '',
                                                                       isset( $handData['short_description'] ) && strpos( $handData['short_description']->attribute( 'data_text' ), '<strong>morning</strong>' ) !== false ),
        array( 'Leaves and light', true ) );
$check( 'hand-written: the draft is placed below the test folder', $handVersion && ( $na = $handVersion->nodeAssignments() ) ? (int) $na[0]->attribute( 'parent_node' ) : 0, $parentID );
$purge( array( "t372x-hand-$run" ) );

// =============================================================================================== refused archives
$tampered = "$work/tampered";
expPackageJson::convertDirectory( "$work/pkg", $tampered, 'json' );
$someFile = current( array_filter( expPackageJson::fileList( $tampered ), function ( $f ) { return strpos( $f, 'simplefiles/' ) === 0; } ) );
file_put_contents( "$tampered/$someFile", 'changed' );
eZPackage::archiveDirectory( $tampered, "$dir/tampered-$run.ezpkg", 'tar.gz' );
$bad = $service::keep( "$dir/tampered-$run.ezpkg", 'cli', $userID );
$records[] = $bad;
$check( 'refused: a changed file is found (invalid, not imported)', array( $bad->attribute( 'state' ), $bad->package() ), array( 'invalid', null ) );
// a tar written by hand: archive tools refuse to write such a name
$tarEntry = function ( $name, $data )
{
    $header = str_pad( $name, 100, "\0" ) . str_pad( '0000644', 8, "\0" ) . str_pad( '0000000', 8, "\0" ) . str_pad( '0000000', 8, "\0" )
            . str_pad( sprintf( '%011o', strlen( $data ) ), 12, "\0" ) . str_pad( sprintf( '%011o', time() ), 12, "\0" ) . '        ' . '0'
            . str_repeat( "\0", 100 ) . "ustar\0" . '00' . str_repeat( "\0", 247 );
    $sum = 0;
    for ( $i = 0; $i < 512; $i++ )
        $sum += ord( $header[$i] );
    $header = substr_replace( $header, sprintf( "%06o\0 ", $sum ), 148, 8 );
    return $header . $data . str_repeat( "\0", ( 512 - strlen( $data ) % 512 ) % 512 );
};
file_put_contents( "$work/evil.ezpkg", gzencode( $tarEntry( 'package.json', file_get_contents( "$work/pkg/package.json" ) )
                                               . $tarEntry( '../outside.txt', 'x' ) . str_repeat( "\0", 1024 ) ) );
$refused = false;
try
{
    $records[] = $service::keep( "$work/evil.ezpkg", 'cli', $userID );
}
catch ( expAiPackageException $e )
{
    $refused = true;
}
$check( 'refused: a path outside the package', $refused, true );

// =============================================================================================== clean up
$purge( $remoteIDs );
foreach ( $records as $r )
{
    if ( $p = $r->package() )
        $p->remove();
    foreach ( eZPersistentObject::fetchObjectList( expAiDraftLog::definition(), null, array( 'package_uuid' => (string) $r->attribute( 'uuid' ) ) ) as $log )
        $log->remove();
    $r->remove();
}
eZDir::recursiveDelete( $work );
echo ( $failures ? 'FAIL' : 'PASS' ) . " $checks checks, $failures failed\n";
$script->shutdown( $failures ? 1 : 0 );
