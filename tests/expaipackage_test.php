<?php
/**
 * Test of content packages: a round trip (a test subtree with a translation, rich text with a link and an embed, an
 * image, a file, keywords, a relation and a nested object is exported, validated, imported into another folder as
 * copies, as drafts, published, imported again for the nested object, published, and the diff shows no difference),
 * conflicts (an object changed after the export; a package that expects a new object; a remote id of another class),
 * dry runs that write nothing, and refused packages (checksum, file hash, path traversal and foreign names in a zip,
 * script, DOCTYPE, an unsupported datatype). The content is made below a test folder and removed by the cleanup
 * script of the runner.
 *
 *   EXPAI_TEST_PARENT=<node id>      a hidden test folder (two folders A and B are made below it)
 *   EXPAI_PACKAGE_DIR=<dir>          where the test writes its packages
 *   EXPAI_TEST_LOGIN=<login>         the user
 *   php bin/php/ezexec.php extension/expai/tests/expaipackage_test.php -s <admin siteaccess>
 *
 * With EXPAI_BULK=queue it only builds and queues the 200-object package (prints "GROUP <uuid> PACKAGE <uuid>"); with
 * EXPAI_BULK=check:<package uuid> it checks that queued import after the queue's workers ran it.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

$parentID = (int) getenv( 'EXPAI_TEST_PARENT' );
$dir = rtrim( (string) getenv( 'EXPAI_PACKAGE_DIR' ), '/' );
$login = (string) getenv( 'EXPAI_TEST_LOGIN' );
$bulk = (string) getenv( 'EXPAI_BULK' );
$failures = 0;
$checks = 0;
$check = function ( $label, $actual, $expected ) use ( &$failures, &$checks )
{
    ++$checks;
    $ok = $actual === $expected;
    if ( !$ok )
        ++$failures;
    echo ( $ok ? 'ok    ' : 'FAIL  ' ) . $label . ( $ok ? '' : ' => ' . var_export( $actual, true ) . ' (expected ' . var_export( $expected, true ) . ')' ) . "\n";
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

$publish = function ( $objectID, $version )
{
    $result = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => (int) $objectID, 'version' => (int) $version ) );
    eZContentObject::clearCache();
    return isset( $result['status'] ) ? (int) $result['status'] : -1;
};
$create = function ( $parentNodeID, $class, array $attributes, $remoteID = '' ) use ( $userID )
{
    $params = array( 'parent_node_id' => (int) $parentNodeID, 'class_identifier' => $class, 'creator_id' => $userID, 'attributes' => $attributes );
    if ( $remoteID !== '' )
        $params['remote_id'] = $remoteID;
    $object = eZContentFunctions::createAndPublishObject( $params, false );
    return $object ?: null;
};
$xml = function ( $inner )
{
    return '<?xml version="1.0" encoding="utf-8"?>' . "\n" . '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"'
         . ' xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/">' . $inner . '</section>' . "\n";
};
$service = 'expAiPackageService';

// =============================================================================================== bulk mode
if ( $bulk === 'queue' || strpos( $bulk, 'check:' ) === 0 )
{
    if ( $bulk === 'queue' )
    {
        $target = $create( $parentID, 'folder', array( 'name' => "Bulk target $run" ) );
        $objects = array();
        for ( $i = 1; $i <= 200; $i++ )
            $objects[] = array( 'remote_id' => "t359b-bulk-$run-$i", 'class_identifier' => 'folder', 'initial_language' => 'eng-US',
                                'locations' => array( array( 'main' => true, 'parent' => array( 'node_remote_id' => (string) $target->mainNode()->attribute( 'remote_id' ) ) ) ),
                                'translations' => array( 'eng-US' => array( 'attributes' => array(
                                    'name' => array( 'datatype' => 'ezstring', 'value' => "Bulk folder $i" ),
                                    'short_description' => array( 'datatype' => 'ezxmltext', 'format' => 'html', 'value' => "<p>Folder <strong>$i</strong> of 200.</p>" ),
                                    'tags' => array( 'datatype' => 'ezkeyword', 'value' => array( 'bulk', "n$i" ) ) ) ) ),
                                'expected' => array( 'exists' => false ) );
        $package = expAiPackage::fromArray( array( 'format' => 'expai-package', 'format_version' => '1.0',
            'package' => array( 'uuid' => expQueue::uuid(), 'name' => "Bulk test $run", 'created' => date( 'c' ), 'generator' => array( 'tool' => 'expaipackage_test', 'version' => '1' ) ),
            'provenance' => array( 'ai_generated' => true, 'models' => array( 'none (test)' ), 'human_reviewed' => false ),
            'objects' => $objects, 'files' => array() ) );
        $package->save( "$dir/bulk-$run" );
        $record = $service::keep( "$dir/bulk-$run", 'cli', $userID );
        $report = $service::import( $record, array( 'queue' => true ) );
        echo 'GROUP ' . $report['group'] . ' PACKAGE ' . $record->attribute( 'uuid' ) . ' JOBS ' . $report['jobs'] . "\n";
        $script->shutdown( 0 );
    }
    $record = expAiPackageRecord::fetchByUUID( substr( $bulk, 6 ) );
    $report = $service::queuedReport( $record );
    $check( 'bulk: every job of the group ended', $report['progress']['active'], 0 );
    $check( 'bulk: 200 objects created as drafts', isset( $report['counts']['create'] ) ? $report['counts']['create'] : 0, 200 );
    $drafts = 0;
    foreach ( $report['results'] as $r )
        foreach ( $r['drafts'] as $d )
            if ( $d['version'] && ( $o = eZContentObject::fetch( $d['object_id'] ) ) && (int) $o->attribute( 'status' ) === eZContentObject::STATUS_DRAFT )
                ++$drafts;
    $check( 'bulk: 200 draft objects exist (none published)', $drafts, 200 );
    $check( 'bulk: the package is imported', expAiPackageRecord::fetchByUUID( $record->attribute( 'uuid' ) )->attribute( 'state' ), 'imported' );
    $logged = (int) eZPersistentObject::count( expAiDraftLog::definition(), array( 'package_uuid' => (string) $record->attribute( 'uuid' ) ) );
    $check( 'bulk: every draft is in the draft log with the package', $logged, 200 );
    echo ( $failures ? 'FAIL' : 'PASS' ) . " $checks checks, $failures failed\n";
    $script->shutdown( $failures ? 1 : 0 );
}

// =============================================================================================== content of A
$a = $create( $parentID, 'folder', array( 'name' => "A source $run" ) );
$b = $create( $parentID, 'folder', array( 'name' => "B target $run" ) );
$aNode = $a->mainNode();
$png = eZSys::varDirectory() . "/tmp-t359b-$run.png";
$im = imagecreatetruecolor( 40, 30 );
imagefill( $im, 0, 0, imagecolorallocate( $im, 200, 80, 20 ) );
imagepng( $im, $png );
$image = $create( $aNode->attribute( 'node_id' ), 'image', array( 'name' => 'Test image', 'image' => $png . '|An orange square' ) );
@unlink( $png );
$urlID = eZURL::registerURL( 'https://example.org/expai-package-test' );
$folder = $create( $aNode->attribute( 'node_id' ), 'folder', array(
    'name' => 'Folder one', 'short_name' => 'One',
    'short_description' => $xml( '<paragraph>Short with a <link url_id="' . $urlID . '">link</link> and <strong>bold</strong>.</paragraph>' ),
    'description' => $xml( '<paragraph>An embedded image:<embed view="embed" size="medium" object_id="' . (int) $image->attribute( 'id' ) . '"/></paragraph>' ),
    'show_children' => '1', 'tags' => 'alpha, beta', 'publish_date' => (string) mktime( 10, 0, 0, 10, 1, 2026 ) ) );
// the German translation of the folder
$fv = $folder->createNewVersionIn( 'ger-DE', 'eng-US', false, true, eZContentObjectVersion::STATUS_DRAFT );
$dm = $folder->fetchDataMap( (int) $fv->attribute( 'version' ), 'ger-DE' );
$dm['name']->fromString( 'Ordner eins' );
$dm['name']->store();
$dm['short_description']->setAttribute( 'data_text', $xml( '<paragraph>Kurz mit <emphasize>Betonung</emphasize>.</paragraph>' ) );
$dm['short_description']->store();
$dm['tags']->fromString( 'alpha, beta, gamma' );
$dm['tags']->store();
$check( 'setup: the German translation is published', $publish( $folder->attribute( 'id' ), $fv->attribute( 'version' ) ), eZModuleOperationInfo::STATUS_CONTINUE );
$article = $create( $aNode->attribute( 'node_id' ), 'article', array( 'title' => 'Article one',
    'intro' => $xml( '<paragraph>Intro of the article.</paragraph>' ),
    'body' => $xml( '<paragraph>See <link object_id="' . (int) $folder->attribute( 'id' ) . '">the folder</link>.</paragraph>' ),
    'enable_comments' => '0', 'image' => (string) $image->attribute( 'id' ) ) );
$txt = eZSys::varDirectory() . "/notes-$run.txt";
file_put_contents( $txt, "Notes of the package test $run\n" );
$file = $create( $aNode->attribute( 'node_id' ), 'file', array( 'name' => 'Test file', 'file' => $txt ) );
@unlink( $txt );
$nested = $create( $folder->mainNode()->attribute( 'node_id' ), 'article', array( 'title' => 'Nested article',
    'intro' => $xml( '<paragraph>Below folder one.</paragraph>' ) ) );
$check( 'setup: test content created', (bool) ( $image && $folder && $article && $file && $nested ), true );

// =============================================================================================== export, validate
$objects = expAiPackageExporter::subtreeObjects( (int) $aNode->attribute( 'node_id' ), true );
$check( 'export: the subtree has 5 objects, parents first', count( $objects ), 5 );
$exporter = new expAiPackageExporter( array( 'name' => "Round trip $run", 'description' => 'expai package test' ) );
$package = $exporter->export( $objects );
$package->save( "$dir/a-$run" );
$check( 'export: 2 files (image, text file)', count( $package->files() ), 2 );
$check( 'export: the unsupported ezauthor is reported, not silently skipped', (bool) array_filter( $exporter->warnings(), function ( $w ) { return strpos( $w, 'ezauthor' ) !== false; } ), true );
$data = json_decode( file_get_contents( "$dir/a-$run/package.json" ), true );
$byRid = array();
foreach ( $data['objects'] as $o )
    $byRid[$o['remote_id']] = $o;
$fo = $byRid[$folder->attribute( 'remote_id' )];
$check( 'export: the folder has eng-US and ger-DE', array_keys( $fo['translations'] ), array( 'eng-US', 'ger-DE' ) );
$check( 'export: links are written as href', strpos( $fo['translations']['eng-US']['attributes']['short_description']['value'], 'href="https://example.org/expai-package-test"' ) !== false, true );
$check( 'export: embeds name the object remote id', strpos( $fo['translations']['eng-US']['attributes']['description']['value'], 'object_remote_id="' . $image->attribute( 'remote_id' ) . '"' ) !== false, true );
$check( 'export: no numeric object id left in rich text', strpos( json_encode( $data['objects'] ), 'object_id=\"' ) === false, true );
$ao = $byRid[$article->attribute( 'remote_id' )];
$check( 'export: the relation by remote id', $ao['translations']['eng-US']['attributes']['image']['remote_id'], (string) $image->attribute( 'remote_id' ) );
$check( 'export: the nested article\'s parent is the folder by remote id', $byRid[$nested->attribute( 'remote_id' )]['locations'][0]['parent'], array( 'object_remote_id' => (string) $folder->attribute( 'remote_id' ) ) );
$check( 'export: expected carries the content hash', strlen( $fo['expected']['content_hash'] ), 64 );
$check( 'export: the manifest checksum matches', expAiPackage::checksumOf( file_get_contents( "$dir/a-$run/package.json" ) ), $data['checksum'] );
$v = ( new expAiPackageValidator( expAiPackage::open( "$dir/a-$run" ) ) )->validate();
$check( 'validate: the export is valid', $v['valid'], true );
if ( !$v['valid'] )
    echo '      ' . implode( "\n      ", $v['errors'] ) . "\n";

// =============================================================================================== refused packages
$tampered = $data;
$tampered['objects'][0]['translations']['eng-US']['attributes']['name']['value'] = 'changed';
eZDir::mkdir( "$dir/t1-$run", 0770, true );
file_put_contents( "$dir/t1-$run/package.json", json_encode( $tampered ) );
$v = ( new expAiPackageValidator( expAiPackage::open( "$dir/t1-$run" ) ) )->validate();
$check( 'refused: a package changed after its checksum', (bool) array_filter( $v['errors'], function ( $e ) { return strpos( $e, 'checksum' ) === 0; } ), true );
$copy = "$dir/t2-$run";
eZDir::mkdir( "$copy/files", 0770, true );
copy( "$dir/a-$run/package.json", "$copy/package.json" );
foreach ( $package->files() as $rel => $f )
    file_put_contents( "$copy/$rel", 'not the original bytes' );
$v = ( new expAiPackageValidator( expAiPackage::open( $copy ) ) )->validate();
$check( 'refused: files whose sha256 does not match', (bool) array_filter( $v['errors'], function ( $e ) { return strpos( $e, 'sha256 does not match' ) !== false; } ), true );
$evil = function ( $name, $entries ) use ( $dir, $run )
{
    $zipPath = "$dir/$name-$run.exppkg";
    $zip = new ZipArchive();
    $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE );
    foreach ( $entries as $entry => $content )
        $zip->addFromString( $entry, $content );
    $zip->close();
    try
    {
        expAiPackage::open( $zipPath );
        return 'opened';
    }
    catch ( expAiPackageException $e )
    {
        return $e->text();
    }
};
$check( 'refused: a zip with ../ in a name', strpos( $evil( 'traversal', array( 'package.json' => '{}', '../evil.php' => '<?php' ) ), 'only package.json and files/' ) !== false, true );
$check( 'refused: a zip with an SVG under files/', strpos( $evil( 'svg', array( 'package.json' => '{}', 'files/evil.svg' => '<svg/>' ) ), 'only package.json and files/' ) !== false, true );
$check( 'refused: a zip without package.json', $evil( 'nojson', array( 'files/' . str_repeat( 'a', 64 ) . '.txt' => 'x' ) ), 'The package has no package.json.' );
$bad = function ( $mutate ) use ( $data )
{
    $d = $data;
    unset( $d['checksum'] );
    $mutate( $d );
    $p = expAiPackage::fromArray( $d, '' );
    $p = expAiPackage::fromJson( $p->encode( true ), '' );
    return ( new expAiPackageValidator( $p ) )->validate();
};
$fi = array_search( $fo, $data['objects'], true );
$v = $bad( function ( &$d ) use ( $fi ) { $d['objects'][$fi]['translations']['eng-US']['attributes']['short_description']['value'] = '<?xml version="1.0"?><section><paragraph>x</paragraph><script>alert(1)</script></section>'; } );
$check( 'refused: script in ezxml', $v['valid'], false );
$v = $bad( function ( &$d ) use ( $fi ) { $d['objects'][$fi]['translations']['eng-US']['attributes']['short_description']['value'] = '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><section><paragraph>&e;</paragraph></section>'; } );
$check( 'refused: a DOCTYPE with an external entity', $v['valid'], false );
$ai = array_search( $ao, $data['objects'], true );
$v = $bad( function ( &$d ) use ( $ai ) { $d['objects'][$ai]['translations']['eng-US']['attributes']['author'] = array( 'datatype' => 'ezauthor', 'value' => 'x' ); } );
$check( 'refused: an unsupported datatype', (bool) array_filter( $v['errors'], function ( $e ) { return strpos( $e, 'ezauthor cannot be imported' ) !== false; } ), true );
$v = $bad( function ( &$d ) { $d['objects'][0]['class_identifier'] = 'no_such_class'; } );
$check( 'refused: an unknown class', $v['valid'], false );

// =============================================================================================== dry run, import as copies
$record = $service::keep( "$dir/a-$run", 'cli', $userID );
$check( 'store: the record is valid', $record->attribute( 'state' ), 'valid' );
$options = array( 'remap' => "copy-$run", 'parent' => (string) $b->mainNode()->attribute( 'node_id' ) );
$before = (int) eZPersistentObject::count( eZContentObject::definition() );
$dry = $service::dryRun( $record, $options );
$check( 'dry run: 4 created, the nested object waits for its parent', array( isset( $dry['counts']['create'] ) ? $dry['counts']['create'] : 0, isset( $dry['counts']['wait_parent'] ) ? $dry['counts']['wait_parent'] : 0 ), array( 4, 1 ) );
$check( 'dry run: nothing written', (int) eZPersistentObject::count( eZContentObject::definition() ), $before );
$check( 'dry run: the diff counts 5 new objects', $dry['diff']['totals']['new'], 5 );
$report = $service::import( $record, $options );
$check( 'import: 4 drafts, 1 waiting', array( isset( $report['counts']['create'] ) ? $report['counts']['create'] : 0, isset( $report['counts']['wait_parent'] ) ? $report['counts']['wait_parent'] : 0 ), array( 4, 1 ) );
$drafts = array();
foreach ( $report['results'] as $r )
{
    foreach ( $r['errors'] as $e )
        echo "      error $e\n";
    foreach ( $r['drafts'] as $d )
        if ( $d['version'] )
            $drafts[] = $d;
}
$check( 'import: every new object is a draft (not published)', count( array_filter( $drafts, function ( $d ) {
    $o = eZContentObject::fetch( $d['object_id'] ); return $o && (int) $o->attribute( 'status' ) === eZContentObject::STATUS_DRAFT; } ) ), 4 );
$copyFolder = eZContentObject::fetchByRemoteID( md5( "copy-$run:" . $folder->attribute( 'remote_id' ) ) );
$check( 'import: the copy has the remapped remote id', (bool) $copyFolder, true );
$check( 'import: the copy has both translations in its draft', $copyFolder ? array_values( array_intersect( array( 'eng-US', 'ger-DE' ), $copyFolder->version( 1 )->translationList( false, false ) ) ) : array(), array( 'eng-US', 'ger-DE' ) );
$logged = (int) eZPersistentObject::count( expAiDraftLog::definition(), array( 'package_uuid' => (string) $record->attribute( 'uuid' ) ) );
$check( 'import: the drafts are in the draft log with the package', $logged, 4 );
// a person publishes the drafts
$published = 0;
foreach ( $drafts as $d )
    $published += $publish( $d['object_id'], $d['version'] ) === eZModuleOperationInfo::STATUS_CONTINUE ? 1 : 0;
$check( 'publish: the 4 drafts', $published, 4 );
$report = $service::import( $record, $options );
$check( 'import again: the nested object is created now; the others exist', array( isset( $report['counts']['create'] ) ? $report['counts']['create'] : 0,
        isset( $report['counts']['update'] ) ? $report['counts']['update'] : 0 ), array( 1, 4 ) );
foreach ( $report['results'] as $r )
    foreach ( $r['drafts'] as $d )
        if ( $d['version'] )
            $publish( $d['object_id'], $d['version'] );
$diff = ( new expAiPackageDiff( expAiPackage::open( "$dir/a-$run" ), $options ) )->diff();
$check( 'diff after publishing: 5 objects the same', $diff['totals']['same'], 5 );
$check( 'diff after publishing: 0 differences', $diff['totals']['differences'], 0 );
if ( $diff['totals']['differences'] )
    foreach ( $diff['objects'] as $o )
        foreach ( $o['languages'] as $lang => $l )
            foreach ( $l['differences'] as $x )
                echo "      $lang {$x['attribute']}: live {$x['live']} | package {$x['package']}\n";
$copyNested = eZContentObject::fetchByRemoteID( md5( "copy-$run:" . $nested->attribute( 'remote_id' ) ) );
$copyFolder = eZContentObject::fetchByRemoteID( md5( "copy-$run:" . $folder->attribute( 'remote_id' ) ) );
$check( 'round trip: the nested copy is below the folder copy', $copyNested && $copyFolder ? (int) $copyNested->mainNode()->attribute( 'parent_node_id' ) : 0,
        $copyFolder ? (int) $copyFolder->attribute( 'main_node_id' ) : -1 );
$copyArticle = eZContentObject::fetchByRemoteID( md5( "copy-$run:" . $article->attribute( 'remote_id' ) ) );
$rel = $copyArticle ? $copyArticle->dataMap()['image']->attribute( 'data_int' ) : 0;
$copyImage = eZContentObject::fetchByRemoteID( md5( "copy-$run:" . $image->attribute( 'remote_id' ) ) );
$check( 'round trip: the relation points at the copy of the image', (int) $rel, $copyImage ? (int) $copyImage->attribute( 'id' ) : -1 );
$alias = $copyImage ? $copyImage->dataMap()['image']->content()->attribute( 'original' ) : array();
$check( 'round trip: the image file arrived (same bytes)', isset( $alias['full_path'] ) && is_file( $alias['full_path'] )
        && hash_file( 'sha256', $alias['full_path'] ) === substr( $byRid[$image->attribute( 'remote_id' )]['translations']['eng-US']['attributes']['image']['file'], 6, 64 ), true );

// =============================================================================================== conflicts
$original = new expAiPackageImporter( expAiPackage::open( "$dir/a-$run" ), array( 'dry_run' => true ) );
$plan = $original->planObject( $fi );
$check( 'conflict: none while the original is unchanged', $plan['action'], 'update' );
sleep( 1 );
$ov = $folder->createNewVersionIn( 'eng-US', false, false, true, eZContentObjectVersion::STATUS_DRAFT );
$odm = $folder->fetchDataMap( (int) $ov->attribute( 'version' ), 'eng-US' );
$odm['short_name']->fromString( 'Changed after the export' );
$odm['short_name']->store();
$publish( $folder->attribute( 'id' ), $ov->attribute( 'version' ) );
$plan = $original->planObject( $fi );
$check( 'conflict: the original changed after the export', $plan['action'], 'conflict' );
$skip = new expAiPackageImporter( expAiPackage::open( "$dir/a-$run" ), array( 'on_conflict' => 'skip' ) );
$result = $skip->importObject( $fi );
$check( 'conflict: skipped (no draft)', array( $result['action'], count( $result['drafts'] ) ), array( 'skipped', 0 ) );
$asDraft = new expAiPackageImporter( expAiPackage::open( "$dir/a-$run" ), array( 'on_conflict' => 'draft', 'languages' => array( 'eng-US' ) ) );
$result = $asDraft->importObject( $fi );
$check( 'conflict: on_conflict=draft makes a draft marked as a conflict', array( $result['conflict'], count( $result['drafts'] ), (int) eZContentObject::fetch( (int) $folder->attribute( 'id' ) )->attribute( 'status' ) ),
        array( true, 1, eZContentObject::STATUS_PUBLISHED ) );
foreach ( $result['drafts'] as $d )
    if ( $d['version'] && ( $dv = eZContentObjectVersion::fetchVersion( $d['version'], $d['object_id'] ) ) )
        $dv->removeThis();
$fail = new expAiPackageImporter( expAiPackage::open( "$dir/a-$run" ), array( 'on_conflict' => 'fail' ) );
$r = $fail->run();
$check( 'conflict: on_conflict=fail imports nothing', $r['imported'], false );
$new = $data;
unset( $new['checksum'] );
$new['objects'] = array( $fo );
$new['objects'][0]['expected'] = array( 'exists' => false );
$p = expAiPackage::fromJson( expAiPackage::fromArray( $new )->encode( true ) );
$plan = ( new expAiPackageImporter( $p, array( 'dry_run' => true ) ) )->planObject( 0 );
$check( 'conflict: a package that expects a new object whose remote id exists', array( $plan['action'], strpos( implode( ' ', $plan['reasons'] ), 'expected a new object' ) !== false ), array( 'conflict', true ) );
$new['objects'][0]['class_identifier'] = 'article';
$new['objects'][0]['translations'] = array( 'eng-US' => array( 'attributes' => array( 'title' => array( 'datatype' => 'ezstring', 'value' => 'x' ) ) ) );
$p = expAiPackage::fromJson( expAiPackage::fromArray( $new )->encode( true ) );
$plan = ( new expAiPackageImporter( $p, array( 'dry_run' => true ) ) )->planObject( 0 );
$check( 'error: the remote id belongs to another class', $plan['action'], 'error' );

// =============================================================================================== html rich text is cleaned
$html = $data;
unset( $html['checksum'] );
$html['objects'] = array( array( 'remote_id' => "t359b-html-$run", 'class_identifier' => 'folder', 'initial_language' => 'eng-US',
    'locations' => array( array( 'main' => true, 'parent' => array( 'node_remote_id' => (string) $b->mainNode()->attribute( 'remote_id' ) ) ) ),
    'translations' => array( 'eng-US' => array( 'attributes' => array( 'name' => array( 'datatype' => 'ezstring', 'value' => 'HTML folder' ),
        'short_description' => array( 'datatype' => 'ezxmltext', 'format' => 'html', 'value' => '<p onclick="x()">Safe <b>text</b><script>alert(1)</script></p>' ) ) ) ) ) );
$html['files'] = array();
$p = expAiPackage::fromJson( expAiPackage::fromArray( $html )->encode( true ) );
$r = ( new expAiPackageImporter( $p, array() ) )->importObject( 0 );
$ho = eZContentObject::fetchByRemoteID( "t359b-html-$run" );
$stored = $ho ? (string) $ho->fetchDataMap( 1, 'eng-US' )['short_description']->attribute( 'data_text' ) : '';
$check( 'html: imported as a draft', $r['action'], 'create' );
$check( 'html: script and event handlers are gone, the text stays', strpos( $stored, 'script' ) === false && strpos( $stored, 'onclick' ) === false && strpos( $stored, 'Safe' ) !== false, true );

echo "RECORD " . $record->attribute( 'uuid' ) . "\n";
echo ( $failures ? 'FAIL' : 'PASS' ) . " $checks checks, $failures failed\n";
$script->shutdown( $failures ? 1 : 0 );
