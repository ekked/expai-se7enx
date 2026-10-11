<?php
/**
 * File containing the expAiPackageNullContext class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * An import context that resolves every reference and writes nothing: rich text is checked with it (by the
 * validator) without registering URLs or looking objects up.
 */
class expAiPackageNullContext
{
    public function objectId( $remoteID )
    {
        return 1;
    }

    public function nodeId( $remoteID )
    {
        return 1;
    }

    public function urlId( $url )
    {
        return 1;
    }

    public function warn( $text )
    {
    }
}
