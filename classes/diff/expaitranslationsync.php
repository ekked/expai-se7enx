<?php
/**
 * File containing the expAiTranslationSync class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * Keeps translations in step with their source language (doc/translation-sync.md).
 *
 * After a version in a source language is published (the workflow event expaitranslationsync, the CLI or the
 * Intelligence tab), every other translation of the object gets a decision:
 *
 *   noop   nothing translatable changed since the translation's base: the base moves forward
 *   patch  only the changed blocks are sent (old source, new source, the existing translation of the block); the
 *          answer replaces those blocks, everything else of the translation stays as it is
 *   full   the whole object is translated again
 *
 * Thresholds are in expai.ini [TranslationSync]. Owner rule: a patch below PatchMaxChangedRatio, within
 * PatchMaxSegments and estimated at or below CostThreshold is queued at once (job class translation_patch); a full
 * translation, a patch between the thresholds or above the cost waits for an editor (Approve in the Intelligence
 * tab). Results are drafts of the target language, never published, logged in expai_draft_log with source
 * "translation" (the "Exponential Intelligence" label).
 *
 * The base of a translation: the newest of (a) the last version whose initial language is the target language (the
 * source text in that version is what the translator saw), (b) the source version of a draft this sync wrote and
 * that was published, (c) a base set by "Skip" or a no-op. State per object and language is kept in ezsite_data
 * (expai_tsync:<object id>:<language>), so no table of its own is needed.
 */
class expAiTranslationSync
{
    const APPLIER = 'translation_sync';
    const CHECK_JOB = 'expai.translation.check';

    /** @var expAiConfig */
    protected $config;

    public function __construct( ?expAiConfig $config = null )
    {
        $this->config = $config ?: expAiConfig::instance();
    }

    /** @return bool [TranslationSync] Enabled */
    public function enabled()
    {
        return $this->config->flag( 'TranslationSync', 'Enabled', true );
    }

    /* ------------------------------------------------------------ the decision */

    /**
     * The decision for one translation, from the diff of the source language.
     * @param array $metrics change_ratio, blocks_changed, hunks (expAiDiffResult::metrics())
     * @param bool $aligned whether the translation has the block structure of the base (patch is possible)
     * @param int $units the blocks a patch would send
     * @param int $estimateMicros the estimated cost of a patch
     * @return array decision (noop, patch, full), needs_editor, auto, reason
     */
    public static function decide( array $metrics, $aligned, $units, $estimateMicros, ?expAiConfig $config = null )
    {
        $config = $config ?: expAiConfig::instance();
        $patchMax = (float) $config->get( 'TranslationSync', 'PatchMaxChangedRatio', 0.25 );
        $fullMin = (float) $config->get( 'TranslationSync', 'FullMinChangedRatio', 0.6 );
        $maxUnits = $config->int( 'TranslationSync', 'PatchMaxSegments', 40, 1 );
        $threshold = expAiBudget::micros( (float) $config->get( 'TranslationSync', 'CostThreshold', 0.02 ) );
        $autoPatch = $config->flag( 'TranslationSync', 'AutoQueuePatch', true );
        $ratio = (float) $metrics['change_ratio'];
        if ( empty( $metrics['hunks'] ) )
            return array( 'decision' => 'noop', 'needs_editor' => false, 'auto' => true, 'reason' => 'nothing translatable changed' );
        if ( !$aligned && $config->flag( 'TranslationSync', 'FullIfStructureMismatch', true ) )
            return array( 'decision' => 'full', 'needs_editor' => true, 'auto' => false, 'reason' => 'the translation does not have the structure of its base' );
        if ( $ratio >= $fullMin )
            return array( 'decision' => 'full', 'needs_editor' => true, 'auto' => false, 'reason' => sprintf( 'changed ratio %.2F reaches FullMinChangedRatio %.2F', $ratio, $fullMin ) );
        $reasons = array();
        if ( $ratio > $patchMax )
            $reasons[] = sprintf( 'changed ratio %.2F above PatchMaxChangedRatio %.2F', $ratio, $patchMax );
        if ( (int) $units > $maxUnits )
            $reasons[] = sprintf( '%d blocks above PatchMaxSegments %d', $units, $maxUnits );
        if ( (int) $estimateMicros > $threshold )
            $reasons[] = 'estimate ' . expAiBudget::money( (int) $estimateMicros ) . ' above CostThreshold ' . expAiBudget::money( $threshold );
        if ( !$autoPatch )
            $reasons[] = 'AutoQueuePatch is disabled';
        return array( 'decision' => 'patch', 'needs_editor' => (bool) $reasons, 'auto' => !$reasons,
                      'reason' => $reasons ? implode( '; ', $reasons ) : 'small change: queued automatically' );
    }

    /* ------------------------------------------------------------ languages, base, state */

    /** @return string the source language of an object ([TranslationSync] SourceLanguages[], else its main language) */
    public function sourceLanguage( eZContentObject $object )
    {
        $languages = array_keys( $object->allLanguages() );
        foreach ( $this->config->arr( 'TranslationSync', 'SourceLanguages' ) as $language )
            if ( in_array( $language, $languages, true ) )
                return (string) $language;
        return (string) $object->attribute( 'initial_language_code' );
    }

    /** @return string[] the translations kept in step ([TranslationSync] TargetLanguages[], else all others) */
    public function targetLanguages( eZContentObject $object, $source = '' )
    {
        $source = $source !== '' ? $source : $this->sourceLanguage( $object );
        $only = $this->config->arr( 'TranslationSync', 'TargetLanguages' );
        $out = array();
        foreach ( array_keys( $object->allLanguages() ) as $language )
            if ( $language !== $source && ( !$only || in_array( $language, $only, true ) ) )
                $out[] = (string) $language;
        return $out;
    }

    /** @return string[] the datatypes translated ([TranslationSync] Datatypes[]) */
    public function datatypes()
    {
        $list = $this->config->arr( 'TranslationSync', 'Datatypes' );
        return $list ? array_values( $list ) : array( 'ezstring', 'eztext', 'ezxmltext', 'ezkeyword' );
    }

    /** @return string the ezsite_data key of an object's translation */
    public static function stateKey( $objectID, $language )
    {
        return 'expai_tsync:' . (int) $objectID . ':' . preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $language );
    }

    /** @return array the stored state of a translation (empty when none) */
    public static function state( $objectID, $language )
    {
        $row = eZSiteData::fetchByName( self::stateKey( $objectID, $language ) );
        $value = $row ? json_decode( (string) $row->attribute( 'value' ), true ) : null;
        return is_array( $value ) ? $value : array();
    }

    /** Stores (merges) the state of a translation. */
    public static function saveState( $objectID, $language, array $changes )
    {
        $name = self::stateKey( $objectID, $language );
        $row = eZSiteData::fetchByName( $name );
        $state = $row ? json_decode( (string) $row->attribute( 'value' ), true ) : array();
        $state = array_merge( is_array( $state ) ? $state : array(), $changes, array( 'updated' => time() ) );
        $json = json_encode( $state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( $row )
        {
            $row->setAttribute( 'value', $json );
            $row->store();
        }
        else
            eZSiteData::create( $name, $json )->store();
        return $state;
    }

    /** Removes the state of every translation of an object (the cleanup of tests and of removed objects). */
    public static function forget( $objectID, array $languages )
    {
        foreach ( $languages as $language )
        {
            $row = eZSiteData::fetchByName( self::stateKey( $objectID, $language ) );
            if ( $row )
                $row->remove();
        }
    }

    /**
     * The base version of a translation: the source text it was made from.
     * @return array version (0 when none), from (target_edit, ai_draft, marked, none)
     */
    public function baseFor( eZContentObject $object, $target, $source, array $state = array() )
    {
        $best = array( 'version' => 0, 'from' => 'none' );
        foreach ( (array) $object->versions() as $version )
        {
            $status = (int) $version->attribute( 'status' );
            if ( !in_array( $status, array( eZContentObjectVersion::STATUS_PUBLISHED, eZContentObjectVersion::STATUS_ARCHIVED ), true ) )
                continue;
            if ( $version->initialLanguageCode() !== $target )
                continue;
            $number = (int) $version->attribute( 'version' );
            $from = 'target_edit';
            if ( !empty( $state['draft_version'] ) && (int) $state['draft_version'] === $number && !empty( $state['draft_source_version'] ) )
            {
                $number = (int) $state['draft_source_version'];
                $from = 'ai_draft';
            }
            if ( $number > $best['version'] )
                $best = array( 'version' => $number, 'from' => $from );
        }
        if ( !empty( $state['base_version'] ) && ( empty( $state['source'] ) || $state['source'] === $source ) && (int) $state['base_version'] > $best['version'] )
            $best = array( 'version' => (int) $state['base_version'], 'from' => 'marked' );
        return $best;
    }

    /* ------------------------------------------------------------ the plan */

    /**
     * What a patch or a full translation of one language would send, and the decision.
     * @param array $options mode (patch|full; default the decision), base_version, to_version, target_version (to
     *                       recompute a queued plan exactly)
     * @return array object_id, source, target, base_version, base_from, to_version, target_version, diff
     *               (expAiDiffResult), metrics, aligned, attributes (per attribute: datatype, kind, units, texts),
     *               units (flat list), mode, request, estimate, decision, needs_editor, auto, reason, alias, lane
     */
    public function plan( eZContentObject $object, $target, array $options = array() )
    {
        $source = isset( $options['source'] ) && $options['source'] !== '' ? (string) $options['source'] : $this->sourceLanguage( $object );
        $state = self::state( (int) $object->attribute( 'id' ), $target );
        $to = !empty( $options['to_version'] ) ? (int) $options['to_version'] : (int) $object->attribute( 'current_version' );
        $targetVersion = !empty( $options['target_version'] ) ? (int) $options['target_version'] : (int) $object->attribute( 'current_version' );
        if ( !empty( $options['base_version'] ) )
            $base = array( 'version' => (int) $options['base_version'], 'from' => isset( $options['base_from'] ) ? (string) $options['base_from'] : 'given' );
        else
            $base = $this->baseFor( $object, $target, $source, $state );
        $baseVersion = $base['version'] ? min( $base['version'], $to ) : 0;
        $datatypes = $this->datatypes();
        $diff = expAiDiff::compare( $object, $object, array( 'version_a' => $baseVersion ?: $to, 'version_b' => $to, 'language' => $source,
                                                              'translatable_only' => true, 'datatypes' => $datatypes, 'config' => $this->config ) );
        if ( !$baseVersion )
        {
            // no base at all: everything counts as new
            $diff = expAiDiff::compare( array( 'values' => array(), 'language' => $source ), $object,
                                        array( 'version_b' => $to, 'language' => $source, 'translatable_only' => true, 'datatypes' => $datatypes, 'config' => $this->config ) );
        }
        $targetValues = expAiDiff::values( $object, $targetVersion, $target );
        $plan = array( 'object_id' => (int) $object->attribute( 'id' ), 'source' => $source, 'target' => (string) $target,
                       'base_version' => $baseVersion, 'base_from' => $base['from'], 'to_version' => $to, 'target_version' => $targetVersion,
                       'diff' => $diff, 'metrics' => $diff->metrics(), 'aligned' => true, 'attributes' => array(), 'units' => array(),
                       'target_hash' => self::valuesHash( $targetValues, array_keys( $diff->attributes() ) ) );
        if ( !$baseVersion )
            $plan['aligned'] = false;
        $mode = isset( $options['mode'] ) ? (string) $options['mode'] : '';
        $patch = $this->units( $diff, $targetValues, 'patch', $plan['aligned'] );
        $plan['aligned'] = $patch['aligned'];
        $patchRequest = $patch['units'] ? $this->request( array_merge( $plan, array( 'units' => $patch['units'] ) ), 'patch' ) : null;
        $patchEstimate = $patchRequest ? $this->estimate( $patchRequest, 'patch' ) : array( 'micros' => 0, 'lane' => '', 'alias' => $this->alias() );
        $decision = self::decide( $plan['metrics'], $plan['aligned'], count( $patch['units'] ), $patchEstimate['micros'], $this->config );
        $plan += $decision;
        $mode = $mode !== '' ? $mode : ( $decision['decision'] === 'noop' ? 'patch' : $decision['decision'] );
        $chosen = $mode === 'full' ? $this->units( $diff, $targetValues, 'full', $plan['aligned'] ) : $patch;
        $plan['mode'] = $mode;
        $plan['attributes'] = $chosen['attributes'];
        $plan['units'] = $chosen['units'];
        $plan['request'] = $chosen['units'] ? ( $mode === 'patch' ? $patchRequest : $this->request( $plan, 'full' ) ) : null;
        $estimate = $mode === 'patch' ? $patchEstimate : ( $plan['request'] ? $this->estimate( $plan['request'], 'full' ) : array( 'micros' => 0, 'lane' => '', 'alias' => $this->alias() ) );
        $plan['estimate'] = (int) $estimate['micros'];
        $plan['patch_estimate'] = (int) $patchEstimate['micros'];
        $plan['lane'] = $estimate['lane'];
        $plan['alias'] = $estimate['alias'];
        return $plan;
    }

    /** @return string a hash of the target values of some attributes (to notice an edit between the plan and the answer) */
    protected static function valuesHash( array $values, array $identifiers )
    {
        $parts = array();
        foreach ( $identifiers as $identifier )
            $parts[] = $identifier . '=' . ( isset( $values[$identifier] ) ? sha1( $values[$identifier]['value'] ) : '' );
        return substr( sha1( implode( '|', $parts ) ), 0, 16 );
    }

    /**
     * The blocks to translate.
     * @param string $mode patch (changed blocks only, with the existing translation) or full (every block)
     * @return array aligned, attributes (identifier => datatype, kind whole|blocks, prop_value, target_value, texts
     *               (block index => text from the translation), units (unit ids by block index)), units (flat list)
     */
    protected function units( expAiDiffResult $diff, array $targetValues, $mode, $aligned )
    {
        $attributes = array();
        $units = array();
        foreach ( $diff->attributes() as $identifier => $a )
        {
            if ( $mode === 'patch' && !$a['hunks'] )
                continue;
            if ( $mode === 'full' && !$a['prop_blocks'] )
                continue;
            $targetValue = isset( $targetValues[$identifier] ) ? (string) $targetValues[$identifier]['value'] : '';
            $whole = !in_array( $a['segmenter'], array( 'expAiSegmenterText', 'expAiSegmenterRichText' ), true )
                     && !is_subclass_of( $a['segmenter'], 'expAiSegmenterText' ) && !is_subclass_of( $a['segmenter'], 'expAiSegmenterRichText' );
            $row = array( 'datatype' => $a['datatype'], 'kind' => $whole ? 'whole' : 'blocks', 'prop_value' => $a['prop_value'],
                          'target_value' => $targetValue, 'texts' => array(), 'units' => array() );
            if ( $whole )
            {
                $id = 'u' . ( count( $units ) + 1 );
                $join = function ( array $blocks ) { return implode( ', ', array_column( $blocks, 'text' ) ); };
                $isKeyword = $a['segmenter'] === 'expAiSegmenterKeyword';
                $units[] = array( 'id' => $id, 'attribute' => $identifier, 'index' => 0, 'kind' => $isKeyword ? 'keywords' : 'text',
                                  'old' => $mode === 'patch' ? ( $isKeyword ? $join( $a['base_blocks'] ) : $a['base_value'] ) : '',
                                  'new' => $isKeyword ? $join( $a['prop_blocks'] ) : $a['prop_value'],
                                  'translation' => $mode === 'patch' ? $targetValue : '', 'before' => '', 'after' => '' );
                $row['units'][0] = $id;
                $attributes[$identifier] = $row;
                continue;
            }
            $segmenter = expAiSegmenterRegistry::forDatatype( $a['datatype'], $this->config );
            $targetBlocks = $segmenter->segment( $targetValue, array( 'language' => $diff->get( 'language' ) ) );
            if ( $mode === 'patch' )
            {
                $same = count( $targetBlocks ) === count( $a['base_blocks'] )
                        && array_column( $targetBlocks, 'type' ) === array_column( $a['base_blocks'], 'type' );
                if ( !$same )
                    $aligned = false;
            }
            $prop = $a['prop_blocks'];
            $byProp = array();
            foreach ( $a['ops'] as $op )
                if ( $op['prop'] !== null )
                    $byProp[$op['prop']] = $op;
            foreach ( $prop as $j => $block )
            {
                $op = isset( $byProp[$j] ) ? $byProp[$j] : array( 'op' => 'insert', 'base' => null );
                if ( $mode === 'patch' && $op['op'] === 'equal' )
                {
                    if ( isset( $targetBlocks[$op['base']] ) )
                        $row['texts'][$j] = array( 'text' => $targetBlocks[$op['base']]['coded'], 'from' => array( $targetValue, (int) $op['base'] ) );
                    continue;
                }
                // a moved block: its translation moves with it
                if ( $mode === 'patch' && $op['op'] === 'insert' && !empty( $op['moved'] ) )
                {
                    foreach ( $a['ops'] as $other )
                        if ( $other['op'] === 'delete' && !empty( $other['moved'] ) && $a['base_blocks'][$other['base']]['hash'] === $block['hash'] && isset( $targetBlocks[$other['base']] ) )
                        {
                            $row['texts'][$j] = array( 'text' => $targetBlocks[$other['base']]['coded'], 'from' => array( $targetValue, (int) $other['base'] ) );
                            continue 2;
                        }
                }
                $id = 'u' . ( count( $units ) + 1 );
                $hasOld = $mode === 'patch' && $op['op'] === 'modify';
                $units[] = array( 'id' => $id, 'attribute' => $identifier, 'index' => $j, 'kind' => 'block',
                                  'old' => $hasOld ? $a['base_blocks'][$op['base']]['coded'] : '',
                                  'new' => $block['coded'],
                                  'translation' => $hasOld && isset( $targetBlocks[$op['base']] ) ? $targetBlocks[$op['base']]['coded'] : '',
                                  'before' => $j > 0 ? $prop[$j - 1]['text'] : '', 'after' => isset( $prop[$j + 1] ) ? $prop[$j + 1]['text'] : '' );
                $row['units'][$j] = $id;
            }
            $attributes[$identifier] = $row;
        }
        return array( 'aligned' => $aligned, 'attributes' => $attributes, 'units' => $units );
    }

    /** @return string the model alias of the sync ([TranslationSync] Model, else the site default) */
    public function alias()
    {
        $registry = $this->config->registry();
        $alias = $registry->resolveAlias( (string) $this->config->get( 'TranslationSync', 'Model', '' ) );
        return $alias !== '' && $registry->getModel( $alias ) ? $alias : $registry->defaultAlias();
    }

    /** @return string[] the names of the languages, for the prompt */
    protected static function languageName( $code )
    {
        $language = eZContentLanguage::fetchByLocale( (string) $code );
        return $language ? $language->attribute( 'name' ) . ' (' . $code . ')' : (string) $code;
    }

    /**
     * The request of a plan: system, one user message (JSON), output_schema, max_tokens.
     * @return array
     */
    public function request( array $plan, $mode )
    {
        $units = array();
        foreach ( $plan['units'] as $unit )
        {
            $row = array( 'id' => $unit['id'], 'field' => $unit['attribute'] );
            if ( $unit['kind'] === 'keywords' )
                $row['type'] = 'keywords (a comma-separated list)';
            if ( $mode === 'patch' && $unit['old'] !== '' )
            {
                $row['old_source'] = $unit['old'];
                $row['new_source'] = $unit['new'];
                $row['current_translation'] = $unit['translation'];
            }
            else
                $row['source'] = $unit['new'];
            if ( $unit['before'] !== '' )
                $row['context_before'] = expAiText::cut( $unit['before'], 300 );
            if ( $unit['after'] !== '' )
                $row['context_after'] = expAiText::cut( $unit['after'], 300 );
            $units[] = $row;
        }
        $from = self::languageName( $plan['source'] );
        $to = self::languageName( $plan['target'] );
        $system = $mode === 'patch'
            ? "You update existing translations of website content from $from into $to. The source text of some blocks changed. "
              . 'For a block with old_source, new_source and current_translation, change current_translation only where the source changed and keep '
              . 'its wording, terms and style everywhere else. For a block with only source, translate it in the style of the surrounding translation.'
            : "You translate website content from $from into $to. Translate every block completely, naturally and consistently.";
        $system .= ' Markup is written as numbered placeholders: <1>text</1> wraps text in a link or emphasis, <2/> is an embedded object or a line break. '
                 . 'Keep every placeholder exactly once, with the same numbers, around the matching words; never add new ones. '
                 . 'Escape < and & in text as &lt; and &amp;. Do not translate names of people, products or brands unless the source does. '
                 . 'Answer with JSON only: {"units": [{"id": "...", "text": "..."}]}, one entry per block, the text in ' . $plan['target'] . '.';
        $extra = trim( (string) $this->config->get( 'TranslationSync', 'Instructions', '' ) );
        if ( $extra !== '' )
            $system .= "\n\n" . $extra;
        $content = json_encode( array( 'source_language' => $plan['source'], 'target_language' => $plan['target'], 'units' => $units ),
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        $chars = 0;
        foreach ( $plan['units'] as $unit )
            $chars += mb_strlen( $unit['new'], 'UTF-8' );
        $perToken = max( 1.0, (float) $this->config->get( 'Budgets', 'CharsPerToken', 3.5 ) );
        $maxTokens = (int) min( $this->config->int( 'TranslationSync', 'MaxOutputTokens', 8000, 256, 64000 ), max( 1024, ceil( $chars * 3 / $perToken ) + 48 * count( $units ) + 200 ) );
        return array( 'system' => $system, 'messages' => array( array( 'role' => 'user', 'content' => $content ) ), 'max_tokens' => $maxTokens,
                      'output_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'required' => array( 'units' ),
                          'properties' => array( 'units' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'additionalProperties' => false,
                              'required' => array( 'id', 'text' ), 'properties' => array( 'id' => array( 'type' => 'string' ), 'text' => array( 'type' => 'string' ) ) ) ) ) ),
                      'est_output' => (int) ceil( $chars * 1.2 / $perToken ) + 10 * count( $units ) );
    }

    /**
     * The estimated cost of a request on the lane the queue would choose.
     * @return array micros, lane, alias
     */
    public function estimate( array $request, $mode )
    {
        $alias = $this->alias();
        $budget = new expAiBudget( $this->config );
        $lane = null;
        try
        {
            $choice = ( new expAiLanePolicy( $this->config ) )->choose( $alias, array(
                'purpose' => $mode === 'full' ? 'translate_full' : 'translate', 'class' => $mode === 'full' ? 'translation_full' : 'translation_patch',
                'lane' => (string) $this->config->get( 'TranslationSync', $mode === 'full' ? 'FullLane' : 'PatchLane', 'auto' ) ) );
            $lane = $choice['lane'];
        }
        catch ( expAiException $e )
        {
            $lane = null;
        }
        $estimate = $budget->estimate( $alias, $budget->estimateInputTokens( $request ), isset( $request['est_output'] ) ? (int) $request['est_output'] : 0, $lane );
        return array( 'micros' => (int) $estimate['micros'], 'lane' => $lane ? $lane->name() : 'standard', 'alias' => $alias );
    }

    /* ------------------------------------------------------------ check, queue, skip */

    /**
     * Decides for every translation of an object; queues small patches (AutoQueuePatch), stores the decisions.
     * @param array $options languages (only these), dry_run (decide only: nothing stored or queued), trigger, user_id
     * @return array[] per language: the decision row (see stateRow())
     */
    public function check( eZContentObject $object, array $options = array() )
    {
        $source = $this->sourceLanguage( $object );
        $out = array();
        foreach ( $this->targetLanguages( $object, $source ) as $target )
        {
            if ( !empty( $options['languages'] ) && !in_array( $target, (array) $options['languages'], true ) )
                continue;
            $plan = $this->plan( $object, $target, array( 'source' => $source ) );
            $row = $this->stateRow( $plan );
            $row['trigger'] = isset( $options['trigger'] ) ? (string) $options['trigger'] : 'manual';
            if ( !empty( $options['dry_run'] ) )
            {
                $row['state'] = $plan['decision'] === 'noop' ? 'up_to_date' : ( $plan['auto'] ? 'would_queue' : 'waiting' );
                $out[$target] = $row;
                continue;
            }
            $previous = self::state( (int) $object->attribute( 'id' ), $target );
            if ( $plan['decision'] === 'noop' )
            {
                $row['state'] = 'up_to_date';
                $row['base_version'] = $plan['to_version'];
                $row['source'] = $source;
            }
            elseif ( !empty( $previous['job_uuid'] ) && (int) $previous['to_version'] === (int) $plan['to_version'] && self::jobActive( $previous['job_uuid'] ) )
            {
                $row['state'] = 'queued';
                $row['job_uuid'] = $previous['job_uuid'];
            }
            elseif ( $plan['auto'] )
            {
                $row = array_merge( $row, $this->queuePlan( $object, $plan, isset( $options['user_id'] ) ? (int) $options['user_id'] : 0 ) );
            }
            else
                $row['state'] = 'waiting';
            $out[$target] = self::saveState( (int) $object->attribute( 'id' ), $target, $row );
        }
        return $out;
    }

    /** @return array what is stored of a plan */
    protected function stateRow( array $plan )
    {
        $m = $plan['metrics'];
        return array( 'source' => $plan['source'], 'target' => $plan['target'], 'from_version' => $plan['base_version'], 'base_from' => $plan['base_from'],
                      'to_version' => $plan['to_version'], 'target_version' => $plan['target_version'], 'decision' => $plan['decision'],
                      'needs_editor' => $plan['needs_editor'], 'auto' => $plan['auto'], 'reason' => $plan['reason'], 'mode' => $plan['mode'],
                      'change_ratio' => $m['change_ratio'], 'blocks_changed' => $m['blocks_changed'], 'sentences_changed' => $m['sentences_changed'],
                      'hunks' => $m['hunks'], 'units' => count( $plan['units'] ), 'aligned' => $plan['aligned'],
                      'estimate_micros' => $plan['estimate'], 'patch_estimate_micros' => $plan['patch_estimate'], 'lane' => $plan['lane'], 'alias' => $plan['alias'],
                      'checked' => time(), 'checked_by' => (int) eZUser::currentUserID(), 'job_uuid' => '', 'error' => '' );
    }

    /** @return bool whether a queue job has not ended */
    public static function jobActive( $uuid )
    {
        if ( (string) $uuid === '' || !class_exists( 'expQueue' ) )
            return false;
        $job = expQueue::fetch( (string) $uuid );
        return $job && !in_array( (string) $job->attribute( 'state' ), array( 'done', 'dead', 'cancelled' ), true );
    }

    /**
     * An editor's approval (or a forced mode): plans again and queues.
     * @param string $mode '' (the decision), patch or full
     * @return array the stored state
     * @throws expAiException
     */
    public function approve( eZContentObject $object, $target, $mode = '', array $options = array() )
    {
        if ( !$object->canEdit( false, false, false, $target ) )
            throw new expAiException( 'You may not edit object %id in %language.', '', 0, array( '%id' => (int) $object->attribute( 'id' ), '%language' => $target ) );
        $plan = $this->plan( $object, $target, $mode !== '' ? array( 'mode' => $mode ) : array() );
        if ( !$plan['units'] )
            return self::saveState( (int) $object->attribute( 'id' ), $target, array_merge( $this->stateRow( $plan ), array( 'state' => 'up_to_date', 'base_version' => $plan['to_version'] ) ) );
        if ( !$plan['aligned'] && $plan['mode'] === 'patch' )
            throw new expAiException( 'This translation cannot be patched; translate it again in full.', 'the translation does not have the structure of its base' );
        $row = array_merge( $this->stateRow( $plan ), $this->queuePlan( $object, $plan, isset( $options['user_id'] ) ? (int) $options['user_id'] : 0 ),
                            array( 'approved_by' => (int) eZUser::currentUserID(), 'approved' => time() ) );
        return self::saveState( (int) $object->attribute( 'id' ), $target, $row );
    }

    /**
     * Queues the request of a plan (expAiQueue::request, applier translation_sync).
     * @return array state, job_uuid, held, estimate_micros, lane
     */
    protected function queuePlan( eZContentObject $object, array $plan, $userID = 0 )
    {
        $full = $plan['mode'] === 'full';
        $node = $object->attribute( 'main_node' );
        $request = $plan['request'];
        if ( !$plan['units'] || !$request )
            throw new expAiException( 'There is nothing to translate.', 'a plan without blocks is never queued' );
        $estOutput = isset( $request['est_output'] ) ? (int) $request['est_output'] : 0;
        unset( $request['est_output'] );
        $r = expAiQueue::request( $plan['alias'], $request, array(
            'purpose' => $full ? 'translate_full' : 'translate', 'class' => $full ? 'translation_full' : 'translation_patch',
            'lane' => (string) $this->config->get( 'TranslationSync', $full ? 'FullLane' : 'PatchLane', 'auto' ),
            'objects' => array( (int) $object->attribute( 'id' ) ), 'node_id' => $node ? (int) $node->attribute( 'node_id' ) : 0,
            'label' => ( $full ? 'Translation ' : 'Translation patch ' ) . $plan['source'] . ' -> ' . $plan['target'] . ' of object ' . (int) $object->attribute( 'id' ),
            'est_output' => $estOutput, 'user_id' => $userID ?: null,
            'idempotency_key' => 'tsync:' . (int) $object->attribute( 'id' ) . ':' . $plan['target'] . ':' . $plan['base_version'] . '-' . $plan['to_version'] . ':' . $plan['mode'],
            'apply' => self::APPLIER,
            'apply_params' => array( 'object_id' => (int) $object->attribute( 'id' ), 'source' => $plan['source'], 'target' => $plan['target'],
                                     'base_version' => $plan['base_version'], 'base_from' => $plan['base_from'], 'to_version' => $plan['to_version'],
                                     'target_version' => $plan['target_version'], 'target_hash' => $plan['target_hash'], 'mode' => $plan['mode'] ) ) );
        return array( 'state' => $r['held'] !== '' ? 'held' : 'queued', 'job_uuid' => (string) $r['job']->attribute( 'uuid' ), 'held' => (string) $r['held'],
                      'estimate_micros' => (int) $r['estimate'], 'lane' => $r['lane']->name(), 'mode' => $plan['mode'], 'error' => '' );
    }

    /**
     * "Skip": the translation counts as up to date with the current source version.
     * @return array the stored state
     */
    public function skip( eZContentObject $object, $target )
    {
        if ( !$object->canEdit( false, false, false, $target ) )
            throw new expAiException( 'You may not edit object %id in %language.', '', 0, array( '%id' => (int) $object->attribute( 'id' ), '%language' => $target ) );
        $previous = self::state( (int) $object->attribute( 'id' ), $target );
        if ( !empty( $previous['job_uuid'] ) && self::jobActive( $previous['job_uuid'] ) )
            expQueue::cancel( expQueue::fetch( $previous['job_uuid'] ), 'skipped by an editor' );
        return self::saveState( (int) $object->attribute( 'id' ), $target, array(
            'source' => $this->sourceLanguage( $object ), 'base_version' => (int) $object->attribute( 'current_version' ),
            'to_version' => (int) $object->attribute( 'current_version' ), 'state' => 'skipped', 'decision' => 'noop',
            'reason' => 'marked as up to date by an editor', 'change_ratio' => 0, 'blocks_changed' => 0, 'sentences_changed' => 0, 'hunks' => 0,
            'units' => 0, 'estimate_micros' => 0, 'error' => '',
            'skipped_by' => (int) eZUser::currentUserID(), 'skipped' => time(), 'job_uuid' => '' ) );
    }

    /**
     * The translations of an object with their stored decision and the live state of their job.
     * @return array source, languages (code => state row with name, job_state, job_error, draft link data)
     */
    public function status( eZContentObject $object )
    {
        $source = $this->sourceLanguage( $object );
        $out = array( 'source' => $source, 'source_name' => self::languageName( $source ), 'current_version' => (int) $object->attribute( 'current_version' ),
                      'languages' => array() );
        foreach ( $this->targetLanguages( $object, $source ) as $target )
        {
            $row = self::state( (int) $object->attribute( 'id' ), $target ) + array( 'state' => 'unchecked', 'decision' => '', 'job_uuid' => '' );
            $row['name'] = self::languageName( $target );
            $row['stale'] = isset( $row['to_version'] ) && (int) $row['to_version'] !== (int) $object->attribute( 'current_version' )
                            && !in_array( $row['state'], array( 'queued', 'held' ), true );
            $row['job_state'] = '';
            if ( $row['job_uuid'] !== '' && class_exists( 'expQueue' ) )
            {
                $job = expQueue::fetch( $row['job_uuid'] );
                if ( $job )
                {
                    $row['job_state'] = (string) $job->attribute( 'state' );
                    if ( in_array( $row['job_state'], array( 'dead', 'cancelled' ), true ) && in_array( $row['state'], array( 'queued', 'held' ), true ) )
                        $row['state'] = 'failed';
                    if ( $row['job_state'] === 'dead' )
                        $row['error'] = (string) $job->attribute( 'last_error' );
                }
            }
            $row['estimate'] = isset( $row['estimate_micros'] ) ? expAiBudget::money( (int) $row['estimate_micros'] ) : '';
            $out['languages'][$target] = $row;
        }
        return $out;
    }

    /* ------------------------------------------------------------ the answer */

    /**
     * Writes the answer of a queued request into a draft of the target language (the applier).
     * @return array draft_version, language, written, skipped, errors
     * @throws expQueuePermanentException
     */
    public function applyAnswer( expQueueJob $job, array $answer, array $params )
    {
        $uuid = (string) $job->attribute( 'uuid' );
        $object = expAiObjects::fetch( (int) $params['object_id'] );
        if ( !$object instanceof eZContentObject )
            throw new expQueuePermanentException( 'the object is gone', 'not_found' );
        $target = (string) $params['target'];
        $done = eZPersistentObject::fetchObject( expAiDraftLog::definition(), null, array( 'job_uuid' => $uuid ) );
        if ( $done instanceof expAiDraftLog )
            return array( 'draft_version' => (int) $done->attribute( 'version' ), 'language' => $target, 'again' => true );
        $fail = function ( $message, $class ) use ( $object, $target )
        {
            self::saveState( (int) $object->attribute( 'id' ), $target, array( 'state' => 'failed', 'error' => $message ) );
            throw new expQueuePermanentException( $message, $class );
        };
        $plan = $this->plan( $object, $target, array( 'mode' => (string) $params['mode'], 'source' => (string) $params['source'],
                                                     'base_version' => (int) $params['base_version'], 'base_from' => isset( $params['base_from'] ) ? (string) $params['base_from'] : '',
                                                     'to_version' => (int) $params['to_version'], 'target_version' => (int) $params['target_version'] ) );
        // the translation was edited after the plan: do not overwrite that edit
        $now = expAiDiff::values( $object, (int) $object->attribute( 'current_version' ), $target );
        if ( $params['mode'] === 'patch' && self::valuesHash( $now, array_keys( $plan['diff']->attributes() ) ) !== (string) $params['target_hash'] )
            $fail( 'the translation was changed after the patch was planned; check again', 'conflict' );
        $texts = array();
        foreach ( isset( $answer['value']['units'] ) ? (array) $answer['value']['units'] : array() as $unit )
            if ( isset( $unit['id'], $unit['text'] ) )
                $texts[(string) $unit['id']] = (string) $unit['text'];
        $values = array();
        $types = array();
        foreach ( $plan['attributes'] as $identifier => $a )
        {
            $types[$identifier] = $a['datatype'];
            $segmenter = expAiSegmenterRegistry::forDatatype( $a['datatype'], $this->config );
            $compose = $a['texts'];
            foreach ( $a['units'] as $index => $id )
            {
                if ( !isset( $texts[$id] ) )
                    $fail( 'the answer has no text for block ' . $id . ' of ' . $identifier, 'answer' );
                $compose[$index] = array( 'text' => $texts[$id], 'from' => array( $a['prop_value'], (int) $index ) );
            }
            try
            {
                if ( $a['kind'] === 'whole' )
                    $values[$identifier] = $a['datatype'] === 'ezkeyword'
                        ? $segmenter->compose( '', array( $texts[$a['units'][0]] ) )
                        : trim( html_entity_decode( $texts[$a['units'][0]], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
                else
                {
                    ksort( $compose );
                    $values[$identifier] = $segmenter->compose( $a['prop_value'], $compose );
                }
            }
            catch ( expAiException $e )
            {
                $fail( $identifier . ': ' . $e->getMessage() . ' (' . $e->detail() . ')', 'markup' );
            }
        }
        if ( !$values )
            $fail( 'nothing to write', 'answer' );
        try
        {
            $written = expAiDiff::writeDraft( $object, $target, $values, $types, array(
                'source' => 'translation', 'job_uuid' => $uuid, 'model' => isset( $answer['model'] ) ? (string) $answer['model'] : '',
                'prompt_summary' => ( $params['mode'] === 'full' ? 'Translation ' : 'Translation patch ' ) . $params['source'] . ' v' . (int) $params['base_version']
                                    . ' -> v' . (int) $params['to_version'] . ' into ' . $target ) );
        }
        catch ( expAiException $e )
        {
            $fail( $e->getMessage() . ( $e->detail() !== '' ? ' (' . $e->detail() . ')' : '' ), 'write' );
        }
        self::saveState( (int) $object->attribute( 'id' ), $target, array( 'state' => 'draft', 'draft_version' => (int) $written['version'],
                                                                            'draft_source_version' => (int) $params['to_version'], 'error' => '',
                                                                            'written' => $written['written'] ) );
        return array( 'draft_version' => (int) $written['version'], 'language' => $target, 'written' => $written['written'],
                      'skipped' => $written['skipped'], 'errors' => $written['errors'] );
    }

    /* ------------------------------------------------------------ the trigger */

    /**
     * After a publish (the workflow event, or a caller of its own): a check of the object's translations when the
     * published version is in its source language. Queued as a job (expai.translation.check, class system) when the
     * queue is there and [TranslationSync] QueueCheck is enabled, else run at once.
     * @return string what happened (queued, checked, not_source, single_language, disabled)
     */
    public function onPublish( $objectID, $versionNumber, $userID = 0 )
    {
        if ( !$this->enabled() )
            return 'disabled';
        $object = expAiObjects::fetch( (int) $objectID );
        if ( !$object instanceof eZContentObject )
            return 'not_found';
        $version = $object->version( (int) $versionNumber );
        if ( !$version || $version->initialLanguageCode() !== $this->sourceLanguage( $object ) )
            return 'not_source';
        if ( !$this->targetLanguages( $object ) )
            return 'single_language';
        if ( $this->config->flag( 'TranslationSync', 'QueueCheck', true ) && expAiQueue::available() )
        {
            expQueue::enqueue( self::CHECK_JOB, array( 'object_id' => (int) $objectID, 'version' => (int) $versionNumber ),
                               array( 'class' => 'system', 'idempotency_key' => 'tsync-check:' . (int) $objectID . ':' . (int) $versionNumber,
                                      'user_id' => $userID ?: null ) );
            return 'queued';
        }
        $this->check( $object, array( 'trigger' => 'publish' ) );
        return 'checked';
    }
}
