<?php
/**
 * File containing the expAiPackageException class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * A package that cannot be read or is not allowed. The message is English source text with placeholders (context
 * extension/expai/package); text() gives it with the placeholders filled in, translatedText() in the interface
 * language.
 */
class expAiPackageException extends expAiException
{
    public function __construct( $message, array $arguments = array(), $detail = '' )
    {
        parent::__construct( $message, $detail, 0, $arguments );
    }

    /** @return string the message with its placeholders */
    public function text()
    {
        return strtr( $this->getMessage(), $this->arguments() );
    }

    /** @return string the message in the interface language */
    public function translatedText()
    {
        return ezpI18n::tr( 'extension/expai/package', $this->getMessage(), null, $this->arguments() );
    }
}
