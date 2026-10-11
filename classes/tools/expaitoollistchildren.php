<?php
/**
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/** list_children: the children of a node the current user may read */
class expAiToolListChildren extends expAiTool
{
    public function description()
    {
        return 'Lists the children of a node (name, class, node and object id, last change), in the node\'s sort order. '
             . 'Use it to see what exists already and to read examples of a class for style. Without node_id: the open node.';
    }

    public function inputSchema()
    {
        return array( 'type' => 'object', 'properties' => array(
            'node_id' => array( 'type' => 'integer', 'description' => 'The parent node (default: the open node)' ),
            'class_identifier' => array( 'type' => 'string', 'description' => 'Only children of this class' ),
            'offset' => array( 'type' => 'integer', 'description' => 'Skip this many (paging)' ),
            'limit' => array( 'type' => 'integer', 'description' => 'At most this many (capped by the server)' ),
        ), 'additionalProperties' => false );
    }

    public function execute( array $input, expAiToolContext $context )
    {
        $node = $context->reader->readableNode( $this->nodeID( $input, $context ) );
        $max = $context->config->int( 'ToolSettings', 'ListLimit', 25, 1, 200 );
        $limit = isset( $input['limit'] ) ? max( 1, min( $max, (int) $input['limit'] ) ) : $max;
        $offset = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;
        $class = isset( $input['class_identifier'] ) ? (string) $input['class_identifier'] : '';
        $children = $context->reader->children( $node, $limit, $offset, $class );
        return array( 'parent_node_id' => (int) $node->attribute( 'node_id' ),
                      'total' => (int) $node->subTreeCount( array( 'Depth' => 1, 'DepthOperator' => 'eq' ) ),
                      'offset' => $offset, 'children' => $children );
    }

    public function stepText( array $input )
    {
        return 'Listed the children of node %id';
    }

    public function stepArguments( array $input, $result )
    {
        return array( '%id' => isset( $result['parent_node_id'] ) ? $result['parent_node_id'] : '' );
    }
}
