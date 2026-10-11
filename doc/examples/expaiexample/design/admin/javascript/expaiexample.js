/* expaiexample: the JavaScript hooks of doc/extending.md.
   BackendJavaScriptList may put this file before or after expai.js (it follows the extension order), so the hooks
   are registered on DOMContentLoaded: expai.js defines window.ExpAi while the page is parsed and starts the panel
   and the edit form on DOMContentLoaded, after the listeners registered before it. */
document.addEventListener( 'DOMContentLoaded', function () {
    'use strict';
    if ( !window.ExpAi ) {
        return;
    }
    // a panel section: runs once when the panel starts
    window.ExpAi.panelSections.register( 'tips', function ( panel ) {
        var box = panel.querySelector( '.exp-ai-tips-count' );
        window.ExpAi.on( 'drafts:changed', function ( e ) {
            if ( box ) {
                box.textContent = e.drafts.length + ' draft(s) in this panel';
            }
        } );
    } );
    // a field action hook: change the request before it is sent, or the answer before it is filled in
    window.ExpAi.fieldActions.register( 'headline', {
        after: function ( field, result ) {
            result.value = result.value.replace( /[.!]+$/, '' );
        }
    } );
    // an event: every field filled by Exponential Intelligence
    window.ExpAi.on( 'field:filled', function ( e ) {
        document.documentElement.setAttribute( 'data-exp-ai-last-filled', e.field.attribute.identifier );
    } );
} );
