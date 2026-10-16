<?php
/**
 * File containing the expAiContentReader class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * Nodes, objects, fields and classes as small arrays for the assistant to read. Every fetch goes through the
 * kernel's permission checks of the current user (canRead, subTree with the user's limitations), and, with a data
 * policy set (setPolicy()), through the policy of the provider the content goes to: a node it keeps away is refused,
 * lists leave such objects out and say how many.
 */
class expAiContentReader
{
    /** @var expAiConfig */
    protected $config;

    /** @var expAiDataPolicy|null */
    protected $policy;

    /** @var int objects the policy left out of lists */
    public $withheld = 0;

    /** @var int[] the objects this reader let through (object id => id): their content may reach the provider */
    public $read = array();

    public function __construct( ?expAiConfig $config = null, ?expAiDataPolicy $policy = null )
    {
        $this->config = $config ?: expAiConfig::instance();
        $this->policy = $policy;
    }

    public function setPolicy( ?expAiDataPolicy $policy )
    {
        $this->policy = $policy;
    }

    /** @return expAiDataPolicy|null */
    public function policy()
    {
        return $this->policy;
    }

    /** @throws expAiException when the data policy keeps the object away from the provider */
    public function assertAllowed( eZContentObject $object )
    {
        if ( $this->policy )
            $this->policy->assertObject( $object );
        $this->noteRead( $object );
    }

    /** @return bool the data policy lets the object go to the provider */
    public function allowed( eZContentObject $object )
    {
        $allowed = !$this->policy || $this->policy->allowsObject( $object );
        if ( $allowed )
            $this->noteRead( $object );
        return $allowed;
    }

    /** Notes an object whose content may reach the provider (see $read) */
    public function noteRead( $object )
    {
        $id = is_object( $object ) && method_exists( $object, 'attribute' ) ? (int) $object->attribute( 'id' ) : (int) $object;
        if ( $id > 0 )
            $this->read[$id] = $id;
    }

    /** @return int[] the objects noted since the last call, and forgets them */
    public function takeRead()
    {
        $ids = array_values( $this->read );
        $this->read = array();
        return $ids;
    }

    /** @return int */
    protected function maxValue()
    {
        return $this->config->int( 'ToolSettings', 'FieldValueMaxLength', 3000, 100, 50000 );
    }

    /**
     * @return eZContentObjectTreeNode
     * @throws expAiException when missing or not readable
     */
    public function readableNode( $nodeID )
    {
        $node = eZContentObjectTreeNode::fetch( (int) $nodeID );
        if ( !$node instanceof eZContentObjectTreeNode || !$node->canRead() )
            throw new expAiException( 'Node %id does not exist or you may not read it.', '', 0, array( '%id' => (int) $nodeID ) );
        $object = $node->object();
        if ( $object )
            $this->assertAllowed( $object );
        return $node;
    }

    /** @return array the facts of a node */
    public function nodeSummary( eZContentObjectTreeNode $node )
    {
        $object = $node->object();
        return array(
            'node_id' => (int) $node->attribute( 'node_id' ),
            'object_id' => (int) $node->attribute( 'contentobject_id' ),
            'name' => (string) $node->attribute( 'name' ),
            'class_identifier' => (string) $node->attribute( 'class_identifier' ),
            'class_name' => (string) $object->attribute( 'class_name' ),
            'parent_node_id' => (int) $node->attribute( 'parent_node_id' ),
            'url' => '/' . ltrim( (string) $node->attribute( 'url_alias' ), '/' ),
            'section_id' => (int) $object->attribute( 'section_id' ),
            'language' => (string) $object->attribute( 'initial_language_code' ),
            'languages' => array_keys( $object->allLanguages() ),
            'published' => date( 'c', (int) $object->attribute( 'published' ) ),
            'modified' => date( 'c', (int) $object->attribute( 'modified' ) ),
            'hidden' => (bool) $node->attribute( 'is_hidden' ) || (bool) $node->attribute( 'is_invisible' ),
            'children_count' => (int) $node->subTreeCount( array( 'Depth' => 1, 'DepthOperator' => 'eq' ) ),
            'you_can_edit' => (bool) $node->canEdit(),
            'you_can_create_here' => (bool) $node->canCreate(),
        );
    }

    /**
     * The fields of a version of an object.
     * @return array list of { identifier, name, datatype, required, writable, value }
     */
    public function fields( eZContentObject $object, $version = false, $language = false )
    {
        $writable = $this->config->writableDatatypes();
        $out = array();
        foreach ( $object->fetchDataMap( $version, $language ) as $identifier => $attribute )
        {
            $classAttribute = $attribute->contentClassAttribute();
            $datatype = (string) $attribute->attribute( 'data_type_string' );
            $out[] = array(
                'identifier' => (string) $identifier,
                'name' => $classAttribute ? (string) $classAttribute->attribute( 'name' ) : (string) $identifier,
                'datatype' => $datatype,
                'required' => $classAttribute ? (bool) $classAttribute->attribute( 'is_required' ) : false,
                'writable' => in_array( $datatype, $writable, true ),
                'value' => $this->value( $attribute ),
            );
        }
        return $out;
    }

    /** @return string|bool|int|float|null the value of a field, readable, shortened */
    public function value( eZContentObjectAttribute $attribute )
    {
        $max = $this->maxValue();
        if ( !$attribute->hasContent() )
            return null;
        switch ( $attribute->attribute( 'data_type_string' ) )
        {
            case 'ezxmltext':
                return expAiText::cut( expAiText::xmlTextToHtml( $attribute->attribute( 'data_text' ) ), $max );
            case 'ezboolean':
                return (bool) $attribute->attribute( 'data_int' );
            case 'ezinteger':
                return (int) $attribute->attribute( 'data_int' );
            case 'ezdate':
                return date( 'Y-m-d', (int) $attribute->attribute( 'data_int' ) );
            case 'ezdatetime':
                return date( 'c', (int) $attribute->attribute( 'data_int' ) );
            case 'ezimage':
                $content = $attribute->content();
                $alt = $content ? (string) $content->attribute( 'alternative_text' ) : '';
                return '[image' . ( $alt !== '' ? ': ' . $alt : '' ) . ']';
            case 'ezbinaryfile':
            case 'ezmedia':
                return '[file]';
            case 'ezobjectrelation':
            case 'ezobjectrelationlist':
                $names = array();
                foreach ( array_filter( explode( '-', (string) $attribute->toString() ) ) as $id )
                {
                    $related = eZContentObject::fetch( (int) $id );
                    if ( $related && $related->canRead() )
                        $names[] = $related->attribute( 'name' ) . ' (object ' . (int) $id . ')';
                }
                return expAiText::cut( implode( '; ', $names ), $max );
            case 'ezuser':
            case 'ezpassword':
                return '[account data]';
            default:
                return expAiText::cut( (string) $attribute->toString(), $max );
        }
    }

    /**
     * The definition of a class for the assistant.
     * @return array
     * @throws expAiException
     */
    public function classDefinition( $identifier )
    {
        $class = eZContentClass::fetchByIdentifier( (string) $identifier );
        if ( !$class instanceof eZContentClass )
            throw new expAiException( 'There is no content class "%identifier".', '', 0, array( '%identifier' => (string) $identifier ) );
        $writable = $this->config->writableDatatypes();
        $attributes = array();
        foreach ( $class->fetchAttributes() as $attribute )
        {
            $datatype = (string) $attribute->attribute( 'data_type_string' );
            $row = array(
                'identifier' => (string) $attribute->attribute( 'identifier' ),
                'name' => (string) $attribute->attribute( 'name' ),
                'datatype' => $datatype,
                'required' => (bool) $attribute->attribute( 'is_required' ),
                'writable' => in_array( $datatype, $writable, true ),
                'format' => $this->formatHint( $datatype ),
            );
            $description = trim( (string) $attribute->attribute( 'description' ) );
            if ( $description !== '' )
                $row['description'] = expAiText::cut( $description, 300 );
            if ( $datatype === 'ezstring' && (int) $attribute->attribute( 'data_int1' ) > 0 )
                $row['max_length'] = (int) $attribute->attribute( 'data_int1' );
            $attributes[] = $row;
        }
        return array(
            'identifier' => (string) $class->attribute( 'identifier' ),
            'name' => (string) $class->attribute( 'name' ),
            'description' => expAiText::cut( (string) $class->attribute( 'description' ), 300 ),
            'is_container' => (bool) $class->attribute( 'is_container' ),
            'object_name_pattern' => (string) $class->attribute( 'contentobject_name' ),
            'attributes' => $attributes,
        );
    }

    /** @return string how the assistant writes a value of the datatype */
    public function formatHint( $datatype )
    {
        $hints = array(
            'ezstring' => 'one line of plain text',
            'eztext' => 'plain text, paragraphs separated by blank lines',
            'ezxmltext' => 'rich text as HTML using only p, h2, h3, h4, strong, em, ul, ol, li, a href',
            'ezkeyword' => 'comma separated keywords',
            'ezboolean' => 'true or false',
            'ezinteger' => 'an integer',
            'ezfloat' => 'a number',
            'ezdate' => 'a date YYYY-MM-DD',
            'ezdatetime' => 'a date and time YYYY-MM-DD HH:MM',
            'ezemail' => 'an e-mail address',
            'ezurl' => 'a URL, optionally followed by | and the link text',
        );
        return isset( $hints[$datatype] ) ? $hints[$datatype] : 'not writable by the assistant';
    }

    /**
     * The classes the current user may create under a node.
     * @return array list of { identifier, name }
     */
    public function creatableClasses( eZContentObjectTreeNode $node )
    {
        $out = array();
        foreach ( $node->canCreateClassList( true ) as $class )
        {
            if ( $class instanceof eZContentClass )
                $out[] = array( 'identifier' => (string) $class->attribute( 'identifier' ), 'name' => (string) $class->attribute( 'name' ) );
            elseif ( is_array( $class ) && isset( $class['id'] ) )
            {
                $object = eZContentClass::fetch( (int) $class['id'] );
                if ( $object )
                    $out[] = array( 'identifier' => (string) $object->attribute( 'identifier' ), 'name' => (string) $object->attribute( 'name' ) );
            }
        }
        return $out;
    }

    /**
     * Children of a node the current user may read.
     * @return array list of node summaries (short)
     */
    public function children( eZContentObjectTreeNode $node, $limit, $offset = 0, $classIdentifier = '' )
    {
        $params = array( 'Depth' => 1, 'DepthOperator' => 'eq', 'Limit' => (int) $limit, 'Offset' => (int) $offset,
                         'SortBy' => $node->sortArray() );
        if ( $classIdentifier !== '' )
        {
            $params['ClassFilterType'] = 'include';
            $params['ClassFilterArray'] = array( (string) $classIdentifier );
        }
        $out = array();
        foreach ( $node->subTree( $params ) as $child )
        {
            $object = $child->object();
            if ( $object && !$this->allowed( $object ) )
            {
                $this->withheld++;
                continue;
            }
            $out[] = $this->shortNode( $child );
        }
        return $out;
    }

    /** @return array the node in a few fields; only its id when the data policy keeps it away */
    public function shortNode( eZContentObjectTreeNode $node )
    {
        $object = $node->object();
        if ( $object && !$this->allowed( $object ) )
            return array( 'node_id' => (int) $node->attribute( 'node_id' ), 'withheld' => 'kept away by the data policy' );
        return array(
            'node_id' => (int) $node->attribute( 'node_id' ),
            'object_id' => (int) $node->attribute( 'contentobject_id' ),
            'name' => (string) $node->attribute( 'name' ),
            'class_identifier' => (string) $node->attribute( 'class_identifier' ),
            'modified' => date( 'Y-m-d', (int) $node->object()->attribute( 'modified' ) ),
        );
    }
}
